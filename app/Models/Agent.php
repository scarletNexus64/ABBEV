<?php

namespace App\Models;

use App\Concerns\HasObfuscatedRouteKey;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agent artistique (cat.md : « agent d'acteur », « agent technicien »).
 *
 * Un agent peut représenter des comédiens, des techniciens, ou les deux : les
 * deux sous-catégories de cat.md sont portées par `represents` plutôt que par
 * deux tables, puisqu'un même agent travaille souvent avec les deux publics.
 */
class Agent extends Model
{
    use HasObfuscatedRouteKey, HasSlug, HasTranslations;

    public array $translatable = ['bio'];

    public const REPRESENTS = [
        'acteurs' => "Agent d'acteurs",
        'techniciens' => 'Agent de techniciens',
        'mixte' => 'Acteurs & techniciens',
    ];

    protected $fillable = [
        'name', 'slug', 'agency', 'represents', 'email', 'phone', 'website_url',
        'country_code', 'city', 'photo_path', 'bio', 'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    protected function slugSource(): string
    {
        return (string) $this->name;
    }

    public function talents(): HasMany
    {
        return $this->hasMany(Talent::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Agents d'une sous-catégorie : un agent « mixte » apparaît à la fois
     * parmi les agents d'acteurs et parmi les agents de techniciens.
     */
    public function scopeRepresenting(Builder $query, ?string $public): Builder
    {
        if (! in_array($public, ['acteurs', 'techniciens'], true)) {
            return $query;
        }

        return $query->whereIn('represents', [$public, 'mixte']);
    }

    public function representsLabel(): string
    {
        return self::REPRESENTS[$this->represents] ?? $this->represents;
    }
}
