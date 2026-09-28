<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->t('name'),
            'slug' => $this->slug ?? null,
            // Famille d'appartenance (genre, cours, award…) : c'est elle qui
            // pilote le regroupement en sections côté app.
            'family' => $this->family ?? 'genre',
            'sortOrder' => (int) ($this->sort_order ?? 0),
            'description' => $this->t('description') ?? '',
            'mediaCount' => (int) ($this->media_count ?? 0),
        ];
    }
}
