<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesMediaUrls;
use App\Services\BunnyStreamService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réponse JSON pour un Media de type "series".
 * Calquée sur SerieModel côté Flutter.
 */
class SerieResource extends JsonResource
{
    use ResolvesMediaUrls;

    public function toArray(Request $request): array
    {
        $seasonsCount = $this->whenLoaded('seasonsRelation', fn () => $this->seasonsRelation->count(), $this->seasons ?? 0);
        $episodesCount = $this->whenLoaded('seasonsRelation', fn () => $this->seasonsRelation->sum(fn ($s) => $s->episodes_count ?? $s->episodes->count()), 0);

        $bunny = app(BunnyStreamService::class);
        $bunnyThumb = ($this->video_provider === 'bunny' && $this->video_id && $bunny->isConfigured())
            ? $bunny->thumbnailUrl($this->video_id) : null;

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
            'rating' => 0.0,
            'voteCount' => (int) ($this->views_count ?? 0),
            'firstAirDate' => $this->release_year ? sprintf('%04d-01-01', $this->release_year) : null,
            'lastAirDate' => null,
            'numberOfSeasons' => $seasonsCount,
            'numberOfEpisodes' => $episodesCount,
            'genres' => $this->category ? [$this->category->name] : [],
            'category' => $this->whenLoaded('category', fn () => [
                'id' => (string) $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug ?? null,
            ]),
            'cast' => [],
            'creators' => [],
            'language' => 'fr',
            'isAdult' => false,
            'popularity' => (int) ($this->views_count ?? 0),
            'status' => 'En cours',
            'requiresSubscription' => true,
            'isFeatured' => (bool) $this->is_featured,
            'publishedAt' => optional($this->published_at)?->toIso8601String(),
            'seasons' => $this->whenLoaded('seasonsRelation', fn () => SeasonResource::collection($this->seasonsRelation)),
        ];
    }
}
