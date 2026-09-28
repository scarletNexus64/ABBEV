<?php

namespace App\Models;

use App\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasTranslations;

/**
 * GENRE d'un film ou d'une série (`media.category_id`).
 *
 * Depuis septembre 2026, le référentiel est celui de cat.md : exactement 15
 * genres. Les autres familles de cat.md (formats, casting, cours, appels,
 * awards, billetterie, sélections) ont chacune leur propre module et ne
 * vivent plus dans cette table. La colonne `family` subsiste pour les
 * données historiques ; toute ligne active vaut `genre`.
 */
class Category extends Model
{
    use HasTranslations;

    /** Champs exposés à l'app et traduits via la table `translations`. */
    public array $translatable = ['name', 'description'];

    use HasObfuscatedRouteKey {
        resolveRouteBinding as resolveObfuscatedRouteBinding;
    }

    public const GENRE = 'genre';

    /** Les 15 genres de cat.md, dans leur ordre d'affichage (slug → nom). */
    public const REFERENCE_GENRES = [
        'drame' => 'Drame',
        'romance' => 'Romance',
        'aventure' => 'Aventure',
        'comedie' => 'Comédie',
        'famille' => 'Famille',
        'fantastique' => 'Fantastique',
        'documentaire' => 'Documentaire',
        'docu-fiction' => 'Docu-fiction',
        'policier' => 'Policier',
        'historique' => 'Historique',
        'mystere' => 'Mystère',
        'science-fiction' => 'Science-fiction',
        'western' => 'Western',
        'animation' => 'Animation',
        'horreur' => 'Horreur',
    ];

    protected $fillable = [
        'name',
        'slug',
        'family',
        'sort_order',
        'description',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function media()
    {
        return $this->hasMany(Media::class);
    }

    /** Genres actifs, dans l'ordre éditorial puis alphabétique. */
    public function scopeGenres(Builder $query): Builder
    {
        return $query->where('family', self::GENRE)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /** Genre ajouté hors du référentiel de cat.md (signalé dans l'admin). */
    /**
     * Id encodé (web) ou brut (API), comme les autres modèles — et, en
     * dernier recours, le slug : hors ligne, l'app ne connaît les 15 genres
     * que par leur slug (« drame », « docu-fiction »…).
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->resolveObfuscatedRouteBinding($value, $field)
            ?? ($field === null ? $this->where('slug', (string) $value)->first() : null);
    }

    public function isOutsideReference(): bool
    {
        return ! array_key_exists((string) $this->slug, self::REFERENCE_GENRES);
    }
}
