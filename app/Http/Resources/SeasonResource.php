<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeasonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'seasonNumber' => (int) $this->season_number,
            'title' => $this->title,
            'description' => $this->description,
            'releaseYear' => $this->release_year,
            'episodesCount' => (int) ($this->episodes_count ?? ($this->episodes?->count() ?? 0)),
            'episodes' => $this->whenLoaded('episodes', fn () => EpisodeResource::collection($this->episodes)),
        ];
    }
}
