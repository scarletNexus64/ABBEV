<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesMediaUrls;
use App\Models\CastingRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Annonce de casting : carte de liste, ou fiche avec ses rôles.
 *
 * `my_applications` (fiche seulement) indique, rôle par rôle, où en est la
 * candidature de l'utilisateur connecté — l'app remplace alors le bouton
 * « Postuler » par l'état de sa candidature.
 */
class CastingCallResource extends JsonResource
{
    use ResolvesMediaUrls;

    private bool $detailed = false;

    /** @var array<int, string> id du rôle => statut de la candidature */
    private array $myApplications = [];

    public function detailed(array $myApplications = []): static
    {
        $this->detailed = true;
        $this->myApplications = $myApplications;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $base = [
            'id' => (int) $this->id,
            'slug' => $this->slug,
            'title' => $this->title,
            'project_title' => $this->project_title,
            'project_type' => $this->project_type,
            'production_company' => $this->production_company,
            'director' => $this->director,
            'city' => $this->city,
            'country_code' => $this->country_code,
            'shooting_starts_on' => $this->shooting_starts_on?->toDateString(),
            'shooting_ends_on' => $this->shooting_ends_on?->toDateString(),
            'deadline_at' => $this->deadline_at?->toIso8601String(),
            'compensation' => $this->compensation,
            'compensation_details' => $this->compensation_details,
            'cover_url' => $this->absoluteUrl($this->cover_path),
            'is_open' => $this->isOpen(),
            'is_featured' => (bool) $this->is_featured,
            'roles_count' => (int) ($this->roles_count
                ?? ($this->relationLoaded('roles') ? $this->roles->count() : 0)),
            'published_at' => $this->published_at?->toIso8601String(),
        ];

        if (! $this->detailed) {
            return $base;
        }

        return $base + [
            'description' => $this->t('description'),
            'roles' => $this->roles->map(fn (CastingRole $role) => [
                'id' => (int) $role->id,
                'name' => $role->name,
                'kind' => $role->kind,
                'profession' => $role->profession,
                'importance' => $role->importance,
                'gender' => $role->gender,
                'age_min' => $role->age_min,
                'age_max' => $role->age_max,
                'min_tier' => $role->min_tier,
                'description' => $role->t('description'),
                'requirements' => $role->requirements,
                'positions' => (int) $role->positions,
                'my_application_status' => $this->myApplications[$role->id] ?? null,
            ])->values(),
        ];
    }
}
