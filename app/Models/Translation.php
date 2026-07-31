<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Une valeur traduite : (modèle, langue, champ) → texte.
 *
 * Jamais manipulée directement par les contrôleurs : passer par le trait
 * [\App\Models\Concerns\HasTranslations] posé sur les modèles métier.
 */
class Translation extends Model
{
    protected $fillable = ['translatable_type', 'translatable_id', 'locale', 'field', 'value'];

    public function translatable(): MorphTo
    {
        return $this->morphTo();
    }
}
