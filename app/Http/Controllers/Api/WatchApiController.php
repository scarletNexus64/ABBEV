<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Media;
use App\Services\BunnyStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
        if ($movie->type !== 'movie') {
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

        if (! $user->hasActiveSubscription()) {
            Log::warning('[Watch] Access denied — no active subscription', [
                'user_id' => $user->id,
                'type' => $type,
                'id' => $id,
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
        $bunny = app(BunnyStreamService::class);

        if ($model->video_provider === 'bunny' && $model->video_id && $bunny->isConfigured()) {
            return [
                'videoUrl' => $bunny->hlsUrl($model->video_id),
                'embedUrl' => $bunny->embedUrl($model->video_id),
                'videoProvider' => 'bunny',
            ];
        }

        return [
            'videoUrl' => $model->video_path
                ? asset('storage/' . ltrim($model->video_path, '/'))
                : null,
            'embedUrl' => null,
            'videoProvider' => $model->video_provider,
        ];
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

        try {
            // On récupère les hauteurs MP4 réellement encodées par Bunny
            // pour cette vidéo. Évite de proposer un 1080p qui n'existe pas
            // (404 au download). Si l'appel échoue, on retombe sur 720p.
            $available = $this->availableMp4Heights($bunny, $model->video_id);
            $maxHeight = (int) config('services.bunny.download_max_height', 720);
            $chosen    = $this->pickHeight($available, $maxHeight);

            $expiresInSeconds = (int) config('services.bunny.download_token_ttl', 3600);
            $expiresAt        = time() + $expiresInSeconds;
            $downloadUrl      = $bunny->mp4Url($model->video_id, $chosen, $expiresInSeconds);

            // Taille du fichier : Bunny ne l'expose pas par hauteur dans
            // l'API publique. On fait un HEAD sur l'URL signée pour la
            // récupérer (Content-Length). Best-effort : si ça échoue,
            // l'app saura faire sans (et lira la taille au démarrage du
            // téléchargement via flutter_downloader).
            $sizeBytes = $this->probeSize($downloadUrl);

            Log::info('[Download] URL issued', [
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
            Log::error('[Download] Failed to build URL', [
                'type'    => $type,
                'id'      => $model->id,
                'message' => $e->getMessage(),
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
    private function availableMp4Heights(BunnyStreamService $bunny, string $guid): array
    {
        $video = $bunny->getVideo($guid);
        $csv   = (string) ($video['availableResolutions'] ?? '');
        if ($csv === '') {
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
     */
    private function probeSize(string $url): ?int
    {
        try {
            $resp = \Illuminate\Support\Facades\Http::timeout(5)
                ->withOptions(['allow_redirects' => true])
                ->head($url);

            if (! $resp->successful()) {
                return null;
            }
            $len = $resp->header('Content-Length');

            return $len === null || $len === '' ? null : (int) $len;
        } catch (\Throwable) {
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
