<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesMediaUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Agent artistique. Ses coordonnées sont publiques : l'annuaire sert
 * précisément à joindre un agent pour booker un talent.
 */
class AgentResource extends JsonResource
{
    use ResolvesMediaUrls;

    private bool $detailed = false;

    public function detailed(): static
    {
        $this->detailed = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $base = [
            'id' => (int) $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'agency' => $this->agency,
            'represents' => $this->represents,
            'city' => $this->city,
            'country_code' => $this->country_code,
            'photo_url' => $this->absoluteUrl($this->photo_path),
            'email' => $this->email,
            'phone' => $this->phone,
            'website_url' => $this->website_url,
            'talents_count' => (int) ($this->talents_count
                ?? ($this->relationLoaded('talents') ? $this->talents->count() : 0)),
        ];

        if (! $this->detailed) {
            return $base;
        }

        return $base + [
            'bio' => $this->t('bio'),
            'talents' => TalentResource::collection(
                $this->talents->where('is_published', true)->values()
            )->resolve($request),
        ];
    }
}
