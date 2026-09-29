<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use App\Casts\BusinessDateTime;
use App\Concerns\HasObfuscatedRouteKey;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Annonce de casting (cat.md : « annonce casting »).
 *
 * Reprend la structure d'un « breakdown » professionnel : le projet (titre,
 * type, production, réalisation, lieu, dates de tournage, rémunération),
 * puis ses rôles à pourvoir, chacun avec son profil recherché. Les
 * candidatures se font par rôle, depuis l'app.
 */
class CastingCall extends Model
{
    use BelongsToWorkspace, HasObfuscatedRouteKey, HasSlug, HasTranslations;

    public array $translatable = ['description'];

    public const PROJECT_TYPES = [
        'film' => 'Long métrage',
        'court-metrage' => 'Court métrage',
        'serie' => 'Série / feuilleton',
        'documentaire' => 'Documentaire',
        'publicite' => 'Publicité',
        'clip' => 'Clip musical',
        'theatre' => 'Théâtre',
        'autre' => 'Autre',
    ];

    public const COMPENSATIONS = [
        'remunere' => 'Rémunéré',
        'non-remunere' => 'Non rémunéré',
        'a-negocier' => 'À négocier',
    ];

    public const STATUSES = [
        'draft' => 'Brouillon',
        'open' => 'Ouverte',
        'closed' => 'Clôturée',
    ];

    protected $fillable = [
        'title', 'slug', 'project_title', 'project_type', 'production_company',
        'director', 'description', 'city', 'country_code', 'shooting_starts_on',
        'shooting_ends_on', 'deadline_at', 'compensation', 'compensation_details',
        'cover_path', 'contact_email', 'status', 'is_featured', 'published_at',
    ];

    protected $casts = [
        'shooting_starts_on' => 'date',
        'shooting_ends_on' => 'date',
        'deadline_at' => BusinessDateTime::class, // heure locale, stockée en UTC
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
    ];

    protected function slugSource(): string
    {
        return (string) $this->title;
    }

    public function roles(): HasMany
    {
        return $this->hasMany(CastingRole::class)->orderBy('sort_order')->orderBy('id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CastingApplication::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_code', 'code');
    }

    /** Annonces visibles dans l'app : ouvertes OU clôturées, jamais les brouillons. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'closed']);
    }

    /** Annonces qui acceptent encore des candidatures. */
    public function scopeAcceptingApplications(Builder $query): Builder
    {
        return $query->where('status', 'open')
            ->where(fn ($q) => $q->whereNull('deadline_at')->orWhere('deadline_at', '>', now()));
    }

    /**
     * Candidatures possibles : annonce ouverte et date limite non dépassée.
     * La date limite fait foi même si personne n'a clôturé l'annonce à la
     * main — aucune tâche planifiée n'est nécessaire.
     */
    public function isOpen(): bool
    {
        return $this->status === 'open'
            && ($this->deadline_at === null || $this->deadline_at->isFuture());
    }

    public function projectTypeLabel(): string
    {
        return self::PROJECT_TYPES[$this->project_type] ?? $this->project_type;
    }

    public function compensationLabel(): string
    {
        return self::COMPENSATIONS[$this->compensation] ?? $this->compensation;
    }
}
