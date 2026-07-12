<?php

namespace App\Jobs;

use App\Models\BunnyUpload;
use App\Services\BunnyStreamService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Transfère un fichier déjà reçu côté serveur vers la Bunny Library, de façon
 * autonome (le navigateur peut être fermé). Deux responsabilités séparées dans
 * le temps :
 *
 *   1. Création + PUT du binaire (une seule exécution, longue) :
 *        createVideo → uploadVideoStream → status = processing.
 *   2. Polling du transcodage Bunny (pattern « tick », façon
 *      ReconcileKpayTransaction) : re-dispatch avec délai tant que Bunny n'a
 *      pas fini d'encoder (status < 4). Aucun worker n'est bloqué pendant l'attente.
 *
 * Idempotent :
 *   - createVideo() n'est appelé que si bunny_guid est vide ⇒ pas de doublon au retry.
 *   - garde précoce sur les états terminaux (ready/failed).
 *
 * Tourne sur la connexion/queue dédiée « bunny » dont le retry_after est très
 * élevé (cf. config/queue.php) pour ne pas être redispatché en double pendant
 * un PUT de plusieurs Go.
 */
class DispatchTransferToBunny implements ShouldQueue
{
    use Queueable;

    /** Délai entre deux ticks de polling du transcodage (secondes). */
    private const POLL_INTERVAL_SEC = 20;

    /** Pas de borne de durée côté worker : un PUT de plusieurs Go est légitime. */
    public int $timeout = 0;

    /** Retries sur échec TRANSITOIRE du PUT (timeout/réseau/5xx). Les erreurs 4xx échouent tout de suite. */
    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $uploadId)
    {
        $this->onConnection('bunny');
        $this->onQueue('bunny');
    }

    public function handle(BunnyStreamService $bunny): void
    {
        $upload = BunnyUpload::find($this->uploadId);

        if (! $upload) {
            Log::warning('[DispatchTransferToBunny] Upload introuvable', ['upload_id' => $this->uploadId]);

            return;
        }

        // --- Idempotence : garde précoce sur états terminaux ---
        if ($upload->isTerminal()) {
            return;
        }

        // --- Phase 2 : polling du transcodage (binaire déjà chez Bunny) ---
        if ($upload->status === 'processing' && $upload->bunny_guid) {
            $this->pollTranscoding($bunny, $upload);

            return;
        }

        // --- Assemblage des morceaux (déporté ici pour ne pas bloquer la requête web).
        //     Une fois assemblé, la vidéo est lisible en local, même sans Bunny.
        if (! $upload->local_path) {
            $this->assembleChunks($upload);
            $upload->refresh();
            if ($upload->isTerminal()) { // assemblage échoué
                return;
            }
        }

        // Bunny non configuré : on n'essaie pas (la vidéo reste lisible en local).
        if (! $bunny->isConfigured()) {
            $upload->markFailed('Bunny non configuré — vidéo conservée en local. Configurez Bunny puis « Relancer ».');

            return;
        }

        // --- Phase 1 : création de la vidéo + PUT du binaire ---
        try {
            if (! $upload->bunny_guid) {
                $guid = $bunny->createVideo($upload->title);
                $upload->update([
                    'bunny_guid' => $guid,
                    'status'     => 'transferring',
                    'progress'   => 0,
                ]);
            } elseif ($upload->status === 'queued') {
                $upload->update(['status' => 'transferring']);
            }

            $source = $upload->localFilePath();
            if (! $source) {
                throw new \RuntimeException("Fichier local absent (temp_path et copie publique introuvables).");
            }

            $lastPersist = 0;
            $bunny->uploadVideoStream(
                $upload->bunny_guid,
                $source,
                function (int $sent, int $total) use ($upload, &$lastPersist) {
                    $now = time();
                    if ($now - $lastPersist >= 2) { // throttle : 1 écriture / 2 s max
                        $lastPersist = $now;
                        $upload->update([
                            'bytes_sent' => $sent,
                            'progress'   => (int) floor($sent / max(1, $total) * 100),
                        ]);
                    }
                }
            );

            // Binaire envoyé → Bunny transcode (asynchrone).
            // On GARDE le fichier local jusqu'à ce que Bunny soit "prêt" : la vidéo
            // reste lisible en local sans interruption pendant l'encodage.
            $upload->update([
                'status'         => 'processing',
                'progress'       => 0,
                'bytes_sent'     => $upload->size_bytes,
                'transferred_at' => now(),
            ]);

            Cache::forget('bunny.videos.all'); // la library admin verra la nouvelle vidéo

            // Enclenche le polling de transcodage (tick séparé, non bloquant).
            self::dispatch($this->uploadId)->delay(now()->addSeconds(15));
        } catch (\Throwable $e) {
            $httpStatus = $this->httpStatusOf($e);

            Log::error('[DispatchTransferToBunny] Transfert échoué', [
                'upload_id'   => $this->uploadId,
                'http_status' => $httpStatus,
                'exception'   => $e->getMessage(),
            ]);

            // Erreur permanente (auth/clé invalide, requête refusée) → échec immédiat,
            // inutile de réessayer. On conserve le fichier local (filet de secours).
            if ($httpStatus !== null && $httpStatus >= 400 && $httpStatus < 500) {
                $upload->markFailed($this->humanError($httpStatus, $e));

                return;
            }

            // Erreur transitoire (timeout, réseau, 5xx) → on laisse Laravel retenter (tries).
            throw $e;
        }
    }

    /** Extrait le code HTTP d'une exception client (Laravel Http ou Guzzle), sinon null. */
    protected function httpStatusOf(\Throwable $e): ?int
    {
        if ($e instanceof \Illuminate\Http\Client\RequestException && $e->response) {
            return $e->response->status();
        }
        if ($e instanceof \GuzzleHttp\Exception\RequestException && $e->hasResponse()) {
            return $e->getResponse()->getStatusCode();
        }

        return null;
    }

    /** Message lisible pour l'admin selon le code HTTP renvoyé par Bunny. */
    protected function humanError(int $httpStatus, \Throwable $e): string
    {
        return match ($httpStatus) {
            401, 403 => "Bunny a refusé l'accès (HTTP {$httpStatus}). Vérifiez BUNNY_STREAM_API_KEY et la library dans le .env.",
            404      => "Library ou vidéo introuvable côté Bunny (HTTP 404). Vérifiez BUNNY_STREAM_LIBRARY_ID.",
            default  => "Bunny a refusé la requête (HTTP {$httpStatus}).",
        };
    }

    /**
     * Vérifie une fois le statut d'encodage Bunny ; re-tick tant que non terminé.
     */
    protected function pollTranscoding(BunnyStreamService $bunny, BunnyUpload $upload): void
    {
        try {
            $status = $bunny->getVideoStatus($upload->bunny_guid); // 0..5
        } catch (\Throwable $e) {
            // Erreur réseau ponctuelle : on re-tente au tick suivant sans échouer le Job.
            self::dispatch($this->uploadId)->delay(now()->addSeconds(self::POLL_INTERVAL_SEC));

            return;
        }

        $upload->update(['bunny_status' => $status]);

        if ($status >= 4 && $status !== 5) { // 4 = finished/ready
            // Bunny a la vidéo prête : on bascule les médias attachés en local vers
            // Bunny, puis on supprime le fichier du serveur. Tout en arrière-plan.
            $this->migrateLocalMediaToBunny($upload);
            $this->deleteServerFile($upload);

            $upload->update([
                'status'   => 'ready',
                'progress' => 100,
                'ready_at' => now(),
            ]);
            Cache::forget('bunny.videos.all');

            return;
        }

        if ($status === 5) { // 5 = erreur d'encodage Bunny
            $upload->markFailed('Bunny a échoué à encoder la vidéo (status 5).');

            return;
        }

        // Encodage en cours : estimation grossière + tick suivant.
        $upload->update([
            'progress' => match ($status) {
                0 => 5,
                1 => 25,
                2 => 50,
                3 => 80,
                default => 90,
            },
        ]);

        self::dispatch($this->uploadId)->delay(now()->addSeconds(self::POLL_INTERVAL_SEC));
    }

    public function failed(\Throwable $e): void
    {
        $upload = BunnyUpload::find($this->uploadId);

        if ($upload && ! $upload->isTerminal()) {
            // On NE supprime PAS temp_path ici : permet un re-dispatch manuel.
            $upload->markFailed($e->getMessage());
        }
    }

    /**
     * Concatène les morceaux reçus (storage/app/chunks/{id}/{n}.part) en un seul
     * fichier sur le disque PUBLIC (lisible en local), en streaming (empreinte
     * mémoire constante même pour 5 Go), puis purge le dossier de morceaux.
     * Exécuté par le worker → ne bloque jamais une requête web.
     */
    protected function assembleChunks(BunnyUpload $upload): void
    {
        $chunkDir = storage_path('app/chunks/' . $upload->id);
        $parts    = glob($chunkDir . '/*.part') ?: [];

        if (empty($parts)) {
            $upload->markFailed('Morceaux introuvables pour l’assemblage.');

            return;
        }

        // Tri numérique (1.part, 2.part, …, 10.part) — pas alphabétique.
        usort($parts, fn ($a, $b) => ((int) basename($a)) <=> ((int) basename($b)));

        $ext  = strtolower(pathinfo($upload->original_filename, PATHINFO_EXTENSION)) ?: 'mp4';
        $name = $upload->id . '_' . Str::random(8) . '.' . $ext;

        // Disque PRIVÉ (storage/app/private) : jamais accessible directement via
        // le web. Les vidéos locales ne sont servies que par une URL signée
        // (LocalVideoStreamController) après vérification de l'abonnement.
        $destDir      = Storage::disk('local')->path('videos');
        $relativePath = 'videos/' . $name;
        $absolutePath = $destDir . DIRECTORY_SEPARATOR . $name;

        if (! is_dir($destDir)) {
            @mkdir($destDir, 0775, true);
        }

        $out = fopen($absolutePath, 'wb');
        try {
            foreach ($parts as $part) {
                $in = fopen($part, 'rb');
                stream_copy_to_stream($in, $out);
                fclose($in);
            }
        } finally {
            fclose($out);
        }

        File::deleteDirectory($chunkDir);

        $upload->update([
            'temp_path'      => $absolutePath,
            'local_path'     => $relativePath,
            'bytes_received' => $upload->size_bytes,
            'progress'       => 100,
        ]);

        Cache::forget('bunny.videos.all'); // dispo immédiate dans le picker (local)

        // Conversion MP4 (H.264/AAC) DÉCOUPLÉE : une vidéo .webm ne se lit ni sur
        // iOS ni sur Safari. On la convertit sur une file séparée « transcode »
        // pour ne PAS bloquer ce worker (ni les transferts des autres users)
        // pendant les minutes d'encodage ffmpeg. La vidéo est déjà « dispo en
        // local » ci-dessus ; le MP4 iPhone la remplacera dès l'encodage fini.
        // ffmpeg écrit un nouveau fichier et ne supprime l'original qu'à la fin :
        // un PUT Bunny en cours (file bunny) n'est pas gêné.
        if (\App\Services\VideoTranscoder::needsTranscode($absolutePath)) {
            TranscodeBunnyUpload::dispatch($upload->id);
        }
    }

    /**
     * Bascule vers Bunny tout Media/Episode encore attaché à la copie locale de
     * cet upload (video_provider='local' + video_path = local_path) : il pointera
     * désormais sur la vidéo Bunny, de façon transparente.
     */
    protected function migrateLocalMediaToBunny(BunnyUpload $upload): void
    {
        if (! $upload->local_path || ! $upload->bunny_guid) {
            return;
        }

        $toBunny = [
            'video_provider'   => 'bunny',
            'video_id'         => $upload->bunny_guid,
            'video_library_id' => (string) config('services.bunny.library_id'),
            'video_path'       => null,
        ];

        \App\Models\Media::query()
            ->where('video_provider', 'local')
            ->where('video_path', $upload->local_path)
            ->update($toBunny);

        \App\Models\Episode::query()
            ->where('video_provider', 'local')
            ->where('video_path', $upload->local_path)
            ->update($toBunny);
    }

    /**
     * Supprime le fichier vidéo du serveur (la vidéo vit désormais chez Bunny).
     */
    protected function deleteServerFile(BunnyUpload $upload): void
    {
        foreach ([$upload->temp_path, $upload->local_path ? Storage::disk('local')->path($upload->local_path) : null] as $path) {
            if ($path && is_file($path)) {
                @unlink($path);
            }
        }
        $upload->update(['temp_path' => null, 'local_path' => null]);
    }
}
