<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesMediaUrls;
use App\Services\BunnyStreamService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réponse JSON pour un Media de type "movie", calquée sur ce que l'app
 * Flutter attend dans MovieModel (champs camelCase prêts à consommer
 * directement, plus le `videoUrl` complet pour la lecture).
 */
class MovieResource extends JsonResource
{
    use ResolvesMediaUrls;

    public function toArray(Request $request): array
    {
        $bunny = app(BunnyStreamService::class);
        // videoUrl / embedUrl ne sont JAMAIS exposés ici : ils sont délivrés
        // uniquement par WatchApiController (abonnement payant actif requis).
        // On ne calcule que la miniature Bunny pour l'affichage du catalogue.
        $bunnyThumb = null;
        if ($this->video_provider === 'bunny' && $this->video_id && $bunny->isConfigured()) {
            $bunnyThumb = $bunny->thumbnailUrl($this->video_id);
        }

        return [
            'id' => (string) $this->id,
            'slug' => $this->slug,
            'type' => $this->type,
            'title' => $this->title,
            'originalTitle' => $this->title,
            'description' => (string) $this->description,
            'posterUrl' => $this->absoluteUrl($this->cover_path ?: $this->thumbnail_path) ?? $bunnyThumb,
            'backdropUrl' => $this->absoluteUrl($this->banner_path ?: $this->cover_path ?: $this->thumbnail_path) ?? $bunnyThumb,
            'thumbnailUrl' => $this->absoluteUrl($this->thumbnail_path) ?? $bunnyThumb,
            'trailerUrl' => null,
            'videoUrl' => null, // → GET /watch/movie/{id} (abonnés)
            'embedUrl' => null, // → GET /watch/movie/{id} (abonnés)
            'videoProvider' => $this->video_provider,
            'rating' => 0.0,
            'voteCount' => (int) ($this->views_count ?? 0),
            'releaseDate' => $this->release_year ? sprintf('%04d-01-01', $this->release_year) : null,
            'duration' => $this->duration ? intval(round($this->duration / 60)) : 0, // minutes
            'genres' => $this->category ? [$this->category->name] : [],
            'category' => $this->whenLoaded('category', fn () => [
                'id' => (string) $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug ?? null,
            ]),
            'cast' => [],
            'director' => null,
            'language' => 'fr',
            'isAdult' => false,
            'popularity' => (int) ($this->views_count ?? 0),
            'requiresSubscription' => true,
            'isFeatured' => (bool) $this->is_featured,
            'publishedAt' => optional($this->published_at)?->toIso8601String(),
        ];
    }
}
