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
}
