<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesMediaUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Talent de l'annuaire — carte (liste) ou fiche complète.
 *
 * Les codes (`kind`, `tier`, `profession`, `gender`) partent bruts : c'est
 * l'app qui les traduit, pour que la fiche change de langue sans nouvel
 * appel réseau.
 */
class TalentResource extends JsonResource
{
    use ResolvesMediaUrls;

    /** Fiche complète (bio, filmographie, agent) plutôt que carte de liste. */
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
            'kind' => $this->kind,
            'name' => $this->displayName(),
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'stage_name' => $this->stage_name,
            'tier' => $this->tier,
            'profession' => $this->profession,
            'gender' => $this->gender,
            'age' => $this->age(),
            'city' => $this->city,
            'country_code' => $this->country_code,
            'headline' => $this->t('headline'),
            'photo_url' => $this->absoluteUrl($this->photo_path),
            'is_featured' => (bool) $this->is_featured,
        ];

        if (! $this->detailed) {
            return $base;
        }

        return $base + [
            'bio' => $this->t('bio'),
            'playing_age_min' => $this->playing_age_min,
            'playing_age_max' => $this->playing_age_max,
            'height_cm' => $this->height_cm,
            'languages' => array_values($this->languages ?? []),
            'skills' => array_values($this->skills ?? []),
            'showreel_url' => $this->showreel_url,
            'agent' => $this->agent && $this->agent->is_published
                ? (new AgentResource($this->agent))->resolve($request)
                : null,
            'credits' => $this->credits->map(fn ($c) => [
                'id' => (int) $c->id,
                'title' => $c->media ? $c->media->t('title') : $c->title,
                'year' => $c->year,
                'role' => $c->role,
                'media_id' => $c->media_id ? (string) $c->media_id : null,
                'media_type' => $c->media?->type,
                'poster_url' => $c->media
                    ? $this->absoluteUrl($c->media->cover_path ?: $c->media->thumbnail_path)
                    : null,
            ])->values(),
        ];
    }
}
