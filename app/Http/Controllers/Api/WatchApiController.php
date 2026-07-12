<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Configuration;
use App\Models\Episode;
use App\Models\Media;
use App\Services\BunnyStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Délivre les URLs de lecture (videoUrl / embedUrl) UNIQUEMENT aux
 * utilisateurs connectés disposant d'un abonnement payant actif.
 *
 * C'est le seul point d'entrée qui expose les URLs vidéo : les resources
 * publiques (MovieResource, etc.) renvoient désormais `null` pour ces champs,
 * ce qui rend la restriction réellement effective (non contournable).
 */
class WatchApiController extends Controller
{
    /**
     * URL de lecture d'un film.
     * GET /api/v1/watch/movie/{movie}
     */
    public function movie(Request $request, Media $movie): JsonResponse
    {
        if ($movie->type !== 'movie') {
            return response()->json(['message' => 'Contenu introuvable.'], 404);
        }

        if ($denied = $this->guard($request, 'movie', $movie->id)) {
            return $denied;
        }

        $urls = $this->resolveUrls($movie);

        Log::info('[Watch] Access granted', [
            'user_id' => $request->user()->id,
            'type' => 'movie',
            'media_id' => $movie->id,
        ]);

        return response()->json(['data' => $urls]);
    }

    /**
     * URL de lecture d'un épisode de série.
     * GET /api/v1/watch/episode/{episode}
     */
    public function episode(Request $request, Episode $episode): JsonResponse
    {
        if ($denied = $this->guard($request, 'episode', $episode->id)) {
            return $denied;
        }

        $urls = $this->resolveUrls($episode);

        Log::info('[Watch] Access granted', [
            'user_id' => $request->user()->id,
            'type' => 'episode',
            'episode_id' => $episode->id,
        ]);

        return response()->json(['data' => $urls]);
    }

    /**
     * URL de téléchargement MP4 signée d'un film, pour le mode hors-ligne
     * côté app mobile. Mêmes conditions d'accès que /watch (abonnement
     * payant actif). L'URL renvoyée est valable ~1h (cf. token_ttl),
     * pointe vers le MP4 progressif Bunny, et inclut la taille pour que
     * l'app puisse afficher la progression / vérifier la capacité disque
     * avant de lancer le téléchargement.
     *
     * GET /api/v1/watch/movie/{movie}/download
     */
    public function movieDownload(Request $request, Media $movie): JsonResponse
    {
        Log::info('[Download] ▶ movieDownload entry', [
            'user_id'  => optional($request->user())->id,
            'media_id' => $movie->id,
            'type'     => $movie->type,
            'provider' => $movie->video_provider,
            'video_id' => $movie->video_id,
            'ip'       => $request->ip(),
            'ua'       => $request->userAgent(),
        ]);

        if ($movie->type !== 'movie') {
            Log::warning('[Download] ✗ wrong media type', [
                'media_id' => $movie->id,
                'type'     => $movie->type,
            ]);
            return response()->json(['message' => 'Contenu introuvable.'], 404);
        }

        if ($denied = $this->guard($request, 'movie', $movie->id)) {
            return $denied;
        }

        return $this->buildDownloadResponse($request, $movie, 'movie', $movie->title);
    }

    /**
     * URL de téléchargement MP4 signée d'un épisode, pour le mode hors-ligne.
     *
     * GET /api/v1/watch/episode/{episode}/download
     */
    public function episodeDownload(Request $request, Episode $episode): JsonResponse
    {
        Log::info('[Download] ▶ episodeDownload entry', [
            'user_id'    => optional($request->user())->id,
            'episode_id' => $episode->id,
            'provider'   => $episode->video_provider,
            'video_id'   => $episode->video_id,
            'ip'         => $request->ip(),
            'ua'         => $request->userAgent(),
        ]);

        if ($denied = $this->guard($request, 'episode', $episode->id)) {
            return $denied;
        }

        // Nom de fichier explicite : "{Série} S{NN}E{NN} - {titre épisode}"
        $serieTitle = $episode->season?->media?->title ?? 'Série';
        $seasonNum  = str_pad((string) ($episode->season?->number ?? 0), 2, '0', STR_PAD_LEFT);
        $episodeNum = str_pad((string) ($episode->number ?? 0), 2, '0', STR_PAD_LEFT);
        $label = "{$serieTitle} S{$seasonNum}E{$episodeNum} - " . ($episode->title ?? '');

        return $this->buildDownloadResponse($request, $episode, 'episode', $label);
    }

    /**
     * Vérifie l'abonnement. Retourne une réponse 403 si refusé, null sinon.
     * (Le 401 non-connecté est déjà géré par le middleware auth:sanctum.)
     */
    private function guard(Request $request, string $type, int $id): ?JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            // En théorie inatteignable (auth:sanctum filtre avant), mais
            // si jamais ça tombe ici on saura pourquoi côté logs.
            Log::warning('[Watch/Download] ✗ guard sans user (auth manquante)', [
                'type' => $type,
                'id'   => $id,
            ]);
            return response()->json(['message' => 'Non authentifié.'], 401);
        }

        if (! $user->hasActiveSubscription()) {
            Log::warning('[Watch/Download] ✗ Access denied — no active subscription', [
                'user_id' => $user->id,
                'type'    => $type,
                'id'      => $id,
            ]);

            return response()->json([
                'error' => 'subscription_required',
                'message' => 'Un abonnement actif est requis pour visionner ce contenu.',
            ], 403);
        }

        return null;
    }

    /**
     * Construit videoUrl/embedUrl selon le provider (Bunny ou fichier local).
     * Même logique que les *Resource, centralisée ici.
     */
    private function resolveUrls(Media|Episode $model): array
    {
        // Vidéo locale : toujours servie directement, même en mode test
        // (le mode test ne remplace que les vidéos Bunny par un échantillon).
        if ($model->video_provider === 'local' && $model->video_path) {
            return [
                'videoUrl' => $this->signedLocalUrl($model),
                'embedUrl' => null,
                'videoProvider' => 'local',
            ];
        }

        // Mode test/dev : on sert la même vidéo d'échantillon publique pour
        // les vidéos Bunny, sans toucher au quota (cf. /admin/configuration →
        // « Mode Vidéo »). Les vidéos locales ci-dessus sont exclues.
        if ($this->isTestMode()) {
            return [
                'videoUrl' => $this->sampleHls(),
                'embedUrl' => null,
                'videoProvider' => 'sample',
            ];
        }

        $bunny = app(BunnyStreamService::class);

        if ($model->video_provider === 'bunny' && $model->video_id && $bunny->isConfigured()) {
            return [
                'videoUrl' => $bunny->hlsUrl($model->video_id),
                'embedUrl' => $bunny->embedUrl($model->video_id),
                'videoProvider' => 'bunny',
            ];
        }

        return [
            'videoUrl' => null,
            'embedUrl' => null,
            'videoProvider' => $model->video_provider,
        ];
    }

    /**
     * URL SIGNÉE et expirante vers le streaming d'une vidéo locale.
     * Générée seulement après vérification de l'abonnement : remplace l'ancien
     * lien public permanent (partageable/téléchargeable à l'infini).
     */
    private function signedLocalUrl(Media|Episode $model, ?\DateTimeInterface $expiresAt = null, bool $download = false): string
    {
        $type = $model instanceof Episode ? 'episode' : 'movie';
        $expiresAt ??= now()->addSeconds((int) config('services.bunny.token_ttl', 3600));

        $params = ['type' => $type, 'id' => $model->getKey()];
        if ($download) {
            $params['dl'] = 1;
        }

        return URL::temporarySignedRoute('api.watch.local', $expiresAt, $params);
    }

    /**
     * Mode vidéo « test/dev » activé dans /admin/configuration ?
     * Quand actif, tout le catalogue lit une vidéo d'échantillon publique
     * au lieu de Bunny. Par défaut (clé absente) → production.
     */
    private function isTestMode(): bool
    {
        return Configuration::getValue('video_mode', 'production') === 'test';
    }

    /** URL HLS d'échantillon servie en mode test. */
    private function sampleHls(): string
    {
        return (string) Configuration::getValue(
            'video_test_sample_hls',
            'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8',
        );
    }

    /** URL MP4 d'échantillon (téléchargement) servie en mode test. */
    private function sampleMp4(): string
    {
        return (string) Configuration::getValue(
            'video_test_sample_mp4',
            'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/BigBuckBunny.mp4',
        );
    }

    /**
     * Construit la réponse de téléchargement : URL MP4 signée + métadonnées
     * utiles côté app (taille, expiration, filename).
     *
     * Choix de la qualité : on prend la plus haute hauteur disponible
     * dans la liste retournée par Bunny (filtrée à 720p max par défaut
     * pour ne pas servir un 4K de 50 GB sans le vouloir). Configurable
     * via `services.bunny.download_max_height`.
     */
    private function buildDownloadResponse(
        Request $request,
        Media|Episode $model,
        string $type,
        string $label,
    ): JsonResponse {
        // Vidéo locale : URL de téléchargement SIGNÉE et expirante (plus de
        // lien public permanent). Le fichier est servi par LocalVideoStreamController.
        if ($model->video_provider === 'local' && $model->video_path) {
            $localFile  = \Illuminate\Support\Facades\Storage::disk('local')->path($model->video_path);
            $sizeBytes  = is_file($localFile) ? filesize($localFile) : null;
            $ttl        = (int) config('services.bunny.download_token_ttl', 21600);
            $expiresAt  = now()->addSeconds($ttl);
            $localUrl   = $this->signedLocalUrl($model, $expiresAt, download: true);

            Log::info('[Download] ✓ local file served (signed)', [
                'user_id'    => $request->user()->id,
                'type'       => $type,
                'id'         => $model->id,
                'size_bytes' => $sizeBytes,
            ]);

            return response()->json([
                'data' => [
                    'downloadUrl' => $localUrl,
                    'expiresAt'   => $expiresAt->toIso8601String(),
                    'sizeBytes'   => $sizeBytes,
                    'contentType' => 'video/mp4',
                    'height'      => null,
                    'filename'    => $this->safeFilename($label) . '.' . pathinfo($model->video_path, PATHINFO_EXTENSION),
                ],
            ]);
        }

        // Mode test/dev : on renvoie directement l'URL MP4 d'échantillon
        // publique (pas de signature, pas de HEAD Bunny). Le HEAD reste
        // best-effort pour récupérer la taille si possible.
        if ($this->isTestMode()) {
            $downloadUrl = $this->sampleMp4();
            $sizeBytes   = $this->probeSize($downloadUrl, $type, $model->id);

            Log::info('[Download] ✓ URL issued (test mode sample)', [
                'user_id'    => $request->user()->id,
                'type'       => $type,
                'id'         => $model->id,
                'size_bytes' => $sizeBytes,
            ]);

            return response()->json([
                'data' => [
                    'downloadUrl' => $downloadUrl,
                    'expiresAt'   => null,
                    'sizeBytes'   => $sizeBytes,
                    'contentType' => 'video/mp4',
                    'height'      => null,
                    'filename'    => $this->safeFilename($label) . '.mp4',
                ],
            ]);
        }

        $bunny = app(BunnyStreamService::class);

        if ($model->video_provider !== 'bunny'
            || empty($model->video_id)
            || ! $bunny->isConfigured()
        ) {
            Log::warning('[Download] No downloadable source', [
                'type' => $type,
                'id'   => $model->id,
                'provider' => $model->video_provider,
            ]);

            return response()->json([
                'error'   => 'no_downloadable_source',
                'message' => 'Ce contenu n\'est pas disponible en téléchargement.',
            ], 422);
        }

        // Garde-fou : si Token Authentication est OFF côté Laravel (ou
        // si le token_key est vide), `mp4Url()` retourne une URL non
        // signée. Sur une library Bunny qui a la hotlink protection
        // activée — c'est notre cas, vu que le streaming repose dessus —
        // l'URL non signée renvoie 403 et l'app affiche "Échec".
        // On préfère échouer ici avec un message explicite plutôt que
        // de filer une URL qu'on sait condamnée.
        $signingOn = (bool) config('services.bunny.signed_urls');
        $tokenKey  = (string) config('services.bunny.token_key');
        if (! $signingOn || $tokenKey === '') {
            Log::error('[Download] ✗ refus : Bunny URL signing désactivé '
                . '(signed_urls=' . var_export($signingOn, true)
                . ', token_key_len=' . strlen($tokenKey) . '). '
                . 'Activer BUNNY_STREAM_SIGNED_URLS=true et fournir '
                . 'BUNNY_STREAM_TOKEN_KEY (Token Authentication Key '
                . 'du dashboard Bunny).', [
                'type' => $type,
                'id'   => $model->id,
            ]);
            return response()->json([
                'error'   => 'download_signing_disabled',
                'message' => 'Le téléchargement n\'est pas disponible '
                    . '(configuration serveur incomplète).',
            ], 503);
        }

        try {
            // On récupère les hauteurs MP4 réellement encodées par Bunny
            // pour cette vidéo. Évite de proposer un 1080p qui n'existe pas
            // (404 au download). Si l'appel échoue, on retombe sur 720p.
            $available = $this->availableMp4Heights($bunny, $model->video_id, $type, $model->id);
            $maxHeight = (int) config('services.bunny.download_max_height', 720);
            $chosen    = $this->pickHeight($available, $maxHeight);
            Log::info('[Download] resolution chosen', [
                'type'      => $type,
                'id'        => $model->id,
                'available' => $available,
                'max'       => $maxHeight,
                'chosen'    => $chosen,
            ]);

            $expiresInSeconds = (int) config('services.bunny.download_token_ttl', 3600);
            $expiresAt        = time() + $expiresInSeconds;
            $downloadUrl      = $bunny->mp4Url($model->video_id, $chosen, $expiresInSeconds);

            // DEBUG — composants utilisés pour signer. Permet de
            // recalculer le token à la main et de vérifier que la clé
            // dans .env correspond bien à celle du dashboard Bunny.
            // À retirer une fois le download stable.
            $path     = "/{$model->video_id}/play_{$chosen}p.mp4";
            $keyLen   = strlen((string) config('services.bunny.token_key'));
            $keyHead  = substr((string) config('services.bunny.token_key'), 0, 4);
            $keyTail  = substr((string) config('services.bunny.token_key'), -4);
            Log::info('[Download][debug] signed URL built', [
                'type'              => $type,
                'id'                => $model->id,
                'height'            => $chosen,
                'expires_in_sec'    => $expiresInSeconds,
                'expires_unix'      => $expiresAt,
                'signed_urls_on'    => (bool) config('services.bunny.signed_urls'),
                'token_key_len'     => $keyLen,
                'token_key_hint'    => $keyHead . '…' . $keyTail,
                'signed_path'       => $path,
                // URL complète pour repro hors-app (curl -I). À retirer
                // si les logs de prod ne doivent pas contenir de signed URLs.
                'url'               => $downloadUrl,
            ]);

            // Taille du fichier : Bunny ne l'expose pas par hauteur dans
            // l'API publique. On fait un HEAD sur l'URL signée pour la
            // récupérer (Content-Length). Best-effort : si ça échoue,
            // l'app saura faire sans (et lira la taille au démarrage du
            // téléchargement via flutter_downloader).
            $sizeBytes = $this->probeSize($downloadUrl, $type, $model->id);

            Log::info('[Download] ✓ URL issued', [
                'user_id'    => $request->user()->id,
                'type'       => $type,
                'id'         => $model->id,
                'height'     => $chosen,
                'size_bytes' => $sizeBytes,
                'expires_at' => $expiresAt,
            ]);

            return response()->json([
                'data' => [
                    'downloadUrl' => $downloadUrl,
                    'expiresAt'   => $expiresAt,
                    'sizeBytes'   => $sizeBytes,
                    'contentType' => 'video/mp4',
                    'height'      => $chosen,
                    'filename'    => $this->safeFilename($label) . '.mp4',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('[Download] ✗ Failed to build URL', [
                'type'      => $type,
                'id'        => $model->id,
                'exception' => get_class($e),
                'message'   => $e->getMessage(),
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json([
                'error'   => 'download_unavailable',
                'message' => 'Le téléchargement n\'est pas disponible pour le moment.',
            ], 500);
        }
    }

    /**
     * Hauteurs MP4 réellement disponibles sur Bunny pour ce GUID, lues
     * depuis le champ `availableResolutions` (CSV "240,360,480,720,1080").
     * Retourne un tableau d'entiers, vide si rien d'exploitable.
     */
    private function availableMp4Heights(
        BunnyStreamService $bunny,
        string $guid,
        ?string $type = null,
        $modelId = null,
    ): array {
        try {
            $video = $bunny->getVideo($guid);
        } catch (\Throwable $e) {
            // L'appel à /library/{id}/videos/{guid} peut 404 (vidéo supprimée
            // côté Bunny) ou 401 (clé API tournée). On logue précisément :
            // sans ça on voit juste "download_unavailable" côté app.
            Log::error('[Download] ✗ Bunny getVideo failed', [
                'type'     => $type,
                'id'       => $modelId,
                'guid'     => $guid,
                'exception'=> get_class($e),
                'message'  => $e->getMessage(),
            ]);
            return [];
        }

        $csv    = (string) ($video['availableResolutions'] ?? '');
        $status = (string) ($video['status'] ?? '?');
        $length = (int)    ($video['length'] ?? 0);
        Log::info('[Download] Bunny getVideo OK', [
            'type'                 => $type,
            'id'                   => $modelId,
            'guid'                 => $guid,
            'bunny_status'         => $status,
            'length_seconds'       => $length,
            'available_resolutions'=> $csv,
        ]);
        if ($csv === '') {
            Log::warning('[Download] no availableResolutions for video — '
                . 'encoding incomplete or Bunny library has MP4 fallback OFF', [
                'type' => $type,
                'id'   => $modelId,
                'guid' => $guid,
            ]);
            return [];
        }

        return collect(explode(',', $csv))
            ->map(fn ($h) => (int) trim($h))
            ->filter(fn ($h) => $h > 0)
            ->values()
            ->all();
    }

    /**
     * Choisit la meilleure hauteur ≤ $maxHeight. Si rien ne convient
     * (liste vide ou tout trop grand), retombe sur $maxHeight (Bunny
     * fait souvent un fallback transparent vers la plus proche).
     */
    private function pickHeight(array $available, int $maxHeight): int
    {
        $candidates = array_filter($available, fn ($h) => $h <= $maxHeight);
        if (empty($candidates)) {
            return $maxHeight;
        }

        return max($candidates);
    }

    /**
     * Récupère le Content-Length du MP4 via HEAD. Renvoie null si
     * indisponible (timeout, 4xx, header absent). Ne lève pas.
     *
     * Logue toujours le résultat — un HEAD qui répond 404 ici signifie
     * que l'URL signée renverra aussi 404 au téléchargement côté app,
     * c'est précieux pour diagnostiquer "Échec — réessayer".
     */
    private function probeSize(string $url, ?string $type = null, $modelId = null): ?int
    {
        try {
            $resp = \Illuminate\Support\Facades\Http::timeout(5)
                ->withOptions(['allow_redirects' => true])
                ->head($url);

            $status = $resp->status();
            $len    = $resp->header('Content-Length');
            $ctype  = $resp->header('Content-Type');
            Log::info('[Download] probe HEAD', [
                'type'         => $type,
                'id'           => $modelId,
                'status'       => $status,
                'content_type' => $ctype,
                'content_len'  => $len,
            ]);

            if (! $resp->successful()) {
                // HEAD ne renvoie pas de body — pour avoir le message
                // d'erreur réel de Bunny (token expired / IP mismatch /
                // referer block…) on refait un GET Range 0-0 (1 octet)
                // qui retourne le body d'erreur sans télécharger.
                $bodySnippet = '(HEAD has no body)';
                try {
                    $get = \Illuminate\Support\Facades\Http::timeout(5)
                        ->withHeaders(['Range' => 'bytes=0-0'])
                        ->withOptions(['allow_redirects' => false])
                        ->get($url);
                    $bodySnippet = mb_substr((string) $get->body(), 0, 400);
                } catch (\Throwable $eGet) {
                    $bodySnippet = '(GET probe failed: ' . $eGet->getMessage() . ')';
                }
                Log::warning('[Download] ✗ probe HEAD non-2xx — '
                    . 'le téléchargement côté app va probablement échouer pareil', [
                    'type'         => $type,
                    'id'           => $modelId,
                    'status'       => $status,
                    'bunny_error'  => $bodySnippet,
                ]);
                return null;
            }

            return $len === null || $len === '' ? null : (int) $len;
        } catch (\Throwable $e) {
            Log::warning('[Download] probe HEAD threw', [
                'type'    => $type,
                'id'      => $modelId,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Filename safe pour Content-Disposition : ASCII alphanum + tirets,
     * tronqué à 80 caractères. Évite les surprises côté file system
     * Android/iOS (caractères interdits sur FAT32 SD cards).
     */
    private function safeFilename(string $label): string
    {
        $ascii = preg_replace('/[^A-Za-z0-9._-]+/', '_', $label) ?? '';
        $ascii = trim($ascii, '_');
        if ($ascii === '') {
            $ascii = 'abbev-download';
        }

        return mb_substr($ascii, 0, 80);
    }
}
