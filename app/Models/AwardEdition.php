<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Casts\BusinessDateTime;
use App\Concerns\HasObfuscatedRouteKey;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Édition des Lions Head Awards (une par an).
 *
 * Son statut se DÉDUIT des dates, sans tâche planifiée :
 *   upcoming → voting → closed → results
 * (à venir, vote ouvert, vote clos en attente des résultats, résultats
 * publiés). L'admin n'a qu'à fixer la période de vote puis publier.
 */
class AwardEdition extends Model
{
    use BelongsToWorkspace, HasObfuscatedRouteKey, HasSlug, HasTranslations;

    public array $translatable = ['tagline', 'description'];

    public const STATUS_LABELS = [
        'draft' => 'Brouillon',
        'upcoming' => 'Vote à venir',
        'voting' => 'Vote ouvert',
        'closed' => 'Vote clos',
        'results' => 'Résultats publiés',
    ];

    protected $fillable = [
        'name', 'slug', 'year', 'tagline', 'description', 'cover_path',
        'voting_starts_at', 'voting_ends_at', 'ceremony_at', 'ceremony_venue',
        'results_published_at', 'is_current',
    ];

    protected $casts = [
        'year' => 'integer',
        // Heures locales saisies dans l'admin, stockées en UTC.
        'voting_starts_at' => BusinessDateTime::class,
        'voting_ends_at' => BusinessDateTime::class,
        'ceremony_at' => BusinessDateTime::class,
        'results_published_at' => 'datetime',
        'is_current' => 'boolean',
    ];

    protected function slugSource(): string
    {
        return (string) $this->name;
    }

    public function categories(): HasMany
    {
        return $this->hasMany(AwardCategory::class)->orderBy('sort_order')->orderBy('id');
    }

    public function nominees(): HasManyThrough
    {
        return $this->hasManyThrough(AwardNominee::class, AwardCategory::class);
    }

    public function votes(): HasManyThrough
    {
        return $this->hasManyThrough(AwardVote::class, AwardCategory::class);
    }

    public function status(): string
    {
        if ($this->results_published_at !== null) {
            return 'results';
        }
        if ($this->voting_starts_at === null || $this->voting_ends_at === null) {
            return 'draft';
        }
        if (now()->lt($this->voting_starts_at)) {
            return 'upcoming';
        }
        if (now()->lt($this->voting_ends_at)) {
            return 'voting';
        }

        return 'closed';
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status()] ?? $this->status();
    }

    public function isVotingOpen(): bool
    {
        return $this->status() === 'voting';
    }

    public function resultsArePublic(): bool
    {
        return $this->results_published_at !== null;
    }

    /** Marque cette édition comme celle de l'app, et elle seule. */
    public function makeCurrent(): void
    {
        // Une seule édition affichée dans l'app, tous producteurs confondus.
        static::query()->withoutGlobalScope('workspace')
            ->whereKeyNot($this->id)->update(['is_current' => false]);
        $this->forceFill(['is_current' => true])->save();
    }
}
