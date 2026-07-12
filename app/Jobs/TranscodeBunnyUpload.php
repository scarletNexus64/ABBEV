<?php

namespace App\Jobs;

use App\Models\BunnyUpload;
use App\Models\Episode;
use App\Models\Media;
use App\Services\VideoTranscoder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Convertit la copie LOCALE d'un upload en MP4 (H.264/AAC, lisible iPhone/Safari)
 * si elle est dans un format non universel (.webm, .mkv…).
 *
 * DÉCOUPLAGE VOLONTAIRE : ce job tourne sur sa PROPRE file « transcode », séparée
 * de la file « bunny » (transferts réseau). Raison : un encodage ffmpeg dure
 * plusieurs minutes et sature le CPU ; s'il partageait la file des transferts, un
 * seul encodage bloquerait tous les autres uploads (head-of-line blocking).
 * Ici, l'assemblage rend la vidéo « dispo en local » IMMÉDIATEMENT ; la version
 * MP4 iPhone arrive ensuite, sans bloquer les transferts ni les autres users.
 *
 *   php artisan queue:work transcode --queue=transcode --timeout=0
 *
 * Idempotent : si déjà en MP4, fichier absent, ou déjà passé chez Bunny, ne fait
 * rien. Écrit le .mp4 sous un NOUVEAU nom (ffmpeg), puis ne supprime l'original
 * qu'à la fin — l'éventuel PUT Bunny en cours (sur la file bunny) n'est pas gêné.
 */
class TranscodeBunnyUpload implements ShouldQueue
{
    use Queueable;

    /** Encodage potentiellement long → aucune borne côté worker. */
    public int $timeout = 0;

    /** Un seul essai : un échec ffmpeg n'est pas transitoire. */
    public int $tries = 1;

    public function __construct(public int $uploadId)
    {
        $this->onConnection('transcode');
        $this->onQueue('transcode');
    }

    public function handle(VideoTranscoder $transcoder): void
    {
        $upload = BunnyUpload::find($this->uploadId);
        if (! $upload || ! $upload->local_path) {
            return;
        }

        // Bunny a déjà pris le relais et le fichier local a été purgé : rien à faire.
        if (! VideoTranscoder::needsTranscode((string) $upload->local_path)) {
            return; // déjà MP4
        }

        $source = $upload->localFilePath();
        if (! $source) {
            Log::warning('[TranscodeBunnyUpload] Fichier local absent', [
                'upload_id' => $this->uploadId,
                'local_path' => $upload->local_path,
            ]);

            return;
        }

        if (! $transcoder->isAvailable()) {
            Log::error('[TranscodeBunnyUpload] ffmpeg indisponible — conversion différée', [
                'upload_id' => $this->uploadId,
            ]);

            return;
        }

        Log::info('[TranscodeBunnyUpload] Conversion démarrée', [
            'upload_id' => $this->uploadId,
            'from' => $upload->local_path,
        ]);

        $newAbsolute = $transcoder->toMp4($source);
        if ($newAbsolute === null || $newAbsolute === $source) {
            // Échec (voir logs ffmpeg) ou déjà MP4 : on laisse le fichier d'origine.
            return;
        }

        // Le fichier a pu être purgé entre-temps (Bunny prêt) : on n'écrase rien.
        $upload->refresh();
        if (! $upload->local_path || $upload->status === 'ready') {
            @unlink($newAbsolute);

            return;
        }

        $oldRelative = $upload->local_path;
        $newRelative = 'videos/' . basename($newAbsolute);

        // Réattribue tout Media/Episode encore attaché à l'ancien fichier local.
        foreach ([Media::class, Episode::class] as $modelClass) {
            $modelClass::where('video_provider', 'local')
                ->where('video_path', $oldRelative)
                ->update(['video_path' => $newRelative]);
        }

        $upload->update(['local_path' => $newRelative, 'temp_path' => $newAbsolute]);

        // Supprime l'original non-MP4 (remplacé par le .mp4).
        if ($source !== $newAbsolute && is_file($source)) {
            @unlink($source);
        }

        Log::info('[TranscodeBunnyUpload] Conversion terminée', [
            'upload_id' => $this->uploadId,
            'to' => $newRelative,
        ]);
    }
}
