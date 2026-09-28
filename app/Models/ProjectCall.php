<?php

namespace App\Models;

use App\Casts\BusinessDateTime;
use App\Concerns\HasObfuscatedRouteKey;
use App\Models\Concerns\HasSlug;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Appel à projets : financement, écriture de scénario ou musique (cat.md).
 *
 * Les trois familles partagent le cycle de vie (brouillon → ouvert →
 * clôturé → terminé) et la présentation ; seuls les champs spécifiques
 * diffèrent (objectif et contreparties pour un financement, cahier des
 * charges et dotation pour l'écriture et la musique).
 */
class ProjectCall extends Model
{
    use HasObfuscatedRouteKey, HasSlug, HasTranslations;

    public array $translatable = ['title', 'summary', 'description', 'requirements'];

    public const TYPES = [
        'financement' => 'Appel à financement',
        'ecriture' => 'Appel à écriture de scénario',
        'musique' => 'Appel à musique',
    ];

    /** Sous-catégories de chaque famille d'appel, telles que cat.md les liste. */
    public const TARGETS = [
        'financement' => [
            'film' => 'Film',
            'serie' => 'Série & feuilleton',
            'documentaire' => 'Documentaire',
        ],
        'ecriture' => [
            'film' => 'Film',
            'serie' => 'Série & feuilleton',
            'documentaire' => 'Documentaire',
        ],
        'musique' => [
            'cinema' => 'Cinéma',
            'television' => 'Télévision',
        ],
    ];

    /** Icône Font Awesome et teinte de chaque famille, pour l'admin. */
    public const LOOK = [
        'financement' => ['hand-holding-dollar', 'emerald'],
        'ecriture' => ['feather-pointed', 'violet'],
        'musique' => ['music', 'sky'],
    ];

    public const STATUSES = [
        'draft' => 'Brouillon',
        'open' => 'Ouvert',
        'closed' => 'Clôturé',
        'completed' => 'Terminé',
    ];

    protected $fillable = [
        'type', 'target', 'title', 'slug', 'summary', 'description', 'cover_path',
        'organizer', 'opens_at', 'closes_at', 'status', 'goal_amount', 'currency',
        'min_pledge', 'raised_offline', 'rewards', 'requirements', 'prize', 'genre',
        'max_pages', 'music_style', 'max_duration_minutes', 'rules_url',
        'contact_email', 'is_featured', 'published_at',
    ];

    protected $casts = [
        // Heures locales saisies dans l'admin, stockées en UTC.
        'opens_at' => BusinessDateTime::class,
        'closes_at' => BusinessDateTime::class,
        'published_at' => 'datetime',
        'goal_amount' => 'decimal:2',
        'min_pledge' => 'decimal:2',
        'raised_offline' => 'decimal:2',
        'rewards' => 'array',
        'is_featured' => 'boolean',
        'max_pages' => 'integer',
        'max_duration_minutes' => 'integer',
    ];

    protected function slugSource(): string
    {
        return (string) $this->title;
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(ProjectSubmission::class);
    }

    public function pledges(): HasMany
    {
        return $this->hasMany(ProjectPledge::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->whereIn('status', ['open', 'closed', 'completed']);
    }

    public function scopeOfType(Builder $query, ?string $type): Builder
    {
        return array_key_exists((string) $type, self::TYPES) ? $query->where('type', $type) : $query;
    }

    public function isFunding(): bool
    {
        return $this->type === 'financement';
    }

    /** Participations acceptées : ouvert, commencé, et avant la date limite. */
    public function isOpen(): bool
    {
        return $this->status === 'open'
            && ($this->opens_at === null || $this->opens_at->isPast())
            && ($this->closes_at === null || $this->closes_at->isFuture());
    }

    /** Montant réuni : promesses confirmées + fonds déclarés hors app. */
    public function raisedAmount(): float
    {
        $confirmed = $this->relationLoaded('pledges')
            ? $this->pledges->where('status', 'confirmed')->sum('amount')
            : $this->pledges()->where('status', 'confirmed')->sum('amount');

        return (float) $confirmed + (float) $this->raised_offline;
    }

    public function backersCount(): int
    {
        return $this->relationLoaded('pledges')
            ? $this->pledges->where('status', 'confirmed')->count()
            : $this->pledges()->where('status', 'confirmed')->count();
    }

    /** Progression vers l'objectif, en pourcentage entier (peut dépasser 100). */
    public function progressPercent(): ?int
    {
        $goal = (float) $this->goal_amount;

        return $goal > 0 ? (int) floor($this->raisedAmount() * 100 / $goal) : null;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** Libellé court d'une famille (« Financement », « Écriture de scénario »…). */
    public static function shortTypeLabel(string $type): string
    {
        return Str::ucfirst(Str::after(self::TYPES[$type] ?? $type, 'Appel à '));
    }

    public function targetLabel(): string
    {
        return self::TARGETS[$this->type][$this->target] ?? $this->target;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
