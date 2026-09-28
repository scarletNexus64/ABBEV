<?php

namespace App\Models;

use App\Concerns\HasObfuscatedRouteKey;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Prix d'une édition (« Meilleure actrice — Cinéma »…). */
class AwardCategory extends Model
{
    use HasObfuscatedRouteKey, HasTranslations;

    public array $translatable = ['name', 'description'];

    public const SCOPES = [
        'cinema' => 'Cinéma',
        'television' => 'Télévision',
        'metiers' => 'Métiers & artisans',
    ];

    public const NOMINEE_TYPES = [
        'person' => 'Une personne (talent)',
        'media' => 'Une œuvre (film ou série)',
    ];

    protected $fillable = [
        'award_edition_id', 'name', 'slug', 'scope', 'nominee_type',
        'description', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function edition(): BelongsTo
    {
        return $this->belongsTo(AwardEdition::class, 'award_edition_id');
    }

    public function nominees(): HasMany
    {
        return $this->hasMany(AwardNominee::class)->orderBy('sort_order')->orderBy('id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(AwardVote::class);
    }

    /**
     * Libellé du volet (Cinéma, Télévision, Métiers). Volontairement pas
     * nommé `scopeLabel` : Eloquent prendrait tout `scope…` pour un scope de
     * requête.
     */
    public function sectionLabel(): string
    {
        return self::SCOPES[$this->scope] ?? $this->scope;
    }

    public function totalVotes(): int
    {
        // Hors relation (qui porte un ORDER BY, refusé par PostgreSQL dans
        // un agrégat) quand les nommés ne sont pas déjà chargés.
        return (int) ($this->relationLoaded('nominees')
            ? $this->nominees->sum('votes_count')
            : AwardNominee::where('award_category_id', $this->id)->sum('votes_count'));
    }
}
