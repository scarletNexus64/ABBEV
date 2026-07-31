<?php

namespace App\Http\Resources;

use App\Http\Resources\Concerns\ResolvesMediaUrls;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Contrat consommé par `OeuvreModel.fromJson()` côté Flutter.
 */
class OeuvreResource extends JsonResource
{
    use ResolvesMediaUrls;

    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'title' => $this->title,
            'author' => $this->author,
            'description' => $this->description,
            'pages' => $this->pages !== null ? (int) $this->pages : null,
            'cover_url' => $this->absoluteUrl($this->cover_path),
            'file_url' => $this->absoluteUrl($this->file_path),
        ];
    }
}
