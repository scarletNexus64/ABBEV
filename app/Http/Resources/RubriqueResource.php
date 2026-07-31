<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesMediaUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contrat consommé par `RubriqueModel.fromJson()` côté Flutter.
 * Les clés sont en snake_case, conformément à ce que le mobile lit déjà
 * (`content_type`, `cover_url`).
 */
class RubriqueResource extends JsonResource
{
    use ResolvesMediaUrls;

    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->t('name'),
            'slug' => $this->slug,
            'content_type' => $this->content_type,
            'description' => $this->t('description'),
            'cover_url' => $this->absoluteUrl($this->cover_path),
        ];
    }
}
