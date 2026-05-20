<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesMediaUrls;
use App\Services\BunnyStreamService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EpisodeResource extends JsonResource
{
    use ResolvesMediaUrls;

    public function toArray(Request $request): array
    {
        $bunny = app(BunnyStreamService::class);
        // videoUrl / embedUrl délivrés uniquement par WatchApiController
        // (abonnement payant actif requis). Ici on ne calcule que la miniature.
        $bunnyThumb = null;
        if ($this->video_provider === 'bunny' && $this->video_id && $bunny->isConfigured()) {
            $bunnyThumb = $bunny->thumbnailUrl($this->video_id);
        }

        return [
            'id' => (string) $this->id,
            'seasonId' => (string) $this->season_id,
            'episodeNumber' => (int) $this->episode_number,
            'title' => $this->title,
            'description' => $this->description,
            'duration' => (int) ($this->duration ?? 0),
            'videoUrl' => null, // → GET /watch/episode/{id} (abonnés)
            'embedUrl' => null, // → GET /watch/episode/{id} (abonnés)
            'videoProvider' => $this->video_provider,
            'thumbnailUrl' => $this->absoluteUrl($this->thumbnail_path) ?? $bunnyThumb,
            'publishedAt' => optional($this->published_at)?->toIso8601String(),
        ];
    }
}
