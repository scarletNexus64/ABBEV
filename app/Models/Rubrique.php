<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\HasTranslations;

/**
 * Section thématique mise en avant dans l'app mobile (chips d'accueil).
 *
 * `content_type` détermine ce que la rubrique contient :
 *  - `oeuvre` : des documents à lire (relation `oeuvres`) ;
 *  - `media`  : des films/séries du catalogue (relation `media`).
 */
class Rubrique extends Model
{
    use HasTranslations;

    /** Champs exposés à l'app et traduits via la table `translations`. */
    public array $translatable = ['name', 'description'];

    /** Du moins au plus permissif : l'index sert à comparer deux tiers. */
    public const TIERS = ['classique', 'standard', 'premium'];

    protected $fillable = [
        'name', 'slug', 'content_type', 'description', 'cover_path',
        'required_tier', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function oeuvres(): HasMany
    {
        return $this->hasMany(Oeuvre::class)->orderBy('sort_order');
    }

    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'media_rubrique')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderBy('media_rubrique.sort_order');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function isOeuvre(): bool
    {
        return $this->content_type === 'oeuvre';
    }

    /**
     * L'utilisateur a-t-il le droit d'ouvrir cette rubrique ?
     *
     * Une rubrique sans `required_tier` est publique. Sinon il faut un
     * abonnement ACTIF dont le tier est au moins celui exigé (premium ouvre
     * les rubriques standard et classique, etc.).
     */
    public function isAccessibleBy(?User $user): bool
    {
        if ($this->required_tier === null) {
            return true;
        }

        if (! $user) {
            return false;
        }

        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('expires_at', '>', now())
            ->with('plan')
            ->orderByDesc('expires_at')
            ->first();

        $userTier = $subscription?->plan?->tier;

        if (! $userTier) {
            return false;
        }

        return array_search($userTier, self::TIERS, true)
            >= array_search($this->required_tier, self::TIERS, true);
    }
}
