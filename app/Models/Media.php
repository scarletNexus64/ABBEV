<?php

namespace App\Models;

use App\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Media extends Model
{
    use HasObfuscatedRouteKey;

    /** Tiers de rémunération/classification du contenu. */
    public const TIERS = ['classique', 'standard', 'premium'];

    protected $fillable = [
        'user_id',
        'category_id',
        'tier',
        'moderation_status',
        'reviewed_by',
        'reviewed_at',
        'rejection_reason',
        'type',
        'title',
        'slug',
        'description',
        'duration',
        'release_year',
        'seasons',
        'video_path',
        'video_provider',
        'video_id',
        'video_library_id',
        'video_metadata',
        'thumbnail_path',
        'banner_path',
        'cover_path',
        'published_at',
        'is_featured',
        'views_count',
        'producer_views',
    ];

    protected $casts = [
        'published_at'   => 'datetime',
        'reviewed_at'    => 'datetime',
        'is_featured'    => 'boolean',
        'video_metadata' => 'array',
        'producer_views' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Propriétaire du contenu (producteur ou admin créateur). */
    public function producer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Membre du panel ayant validé/rejeté le contenu (assistant/admin). */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Restreint la requête aux contenus visibles par un utilisateur du panel :
     * un producteur ne voit que SES contenus ; admin/assistant voient tout.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user && $user->isProducer()) {
            return $query->where('user_id', $user->id);
        }

        return $query; // admin/assistant (ou contexte non restreint) : tout
    }

    /** Contenus approuvés par la modération (visibles au catalogue public). */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('moderation_status', 'approved');
    }

    /**
     * Visibilité publique = approuvé par la modération ET publié (published_at
     * nul ou passé). C'est LE filtre du catalogue public : un contenu en
     * attente/rejeté n'apparaît jamais côté utilisateur.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('moderation_status', 'approved')
            ->where(function ($q) {
                $q->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }

    /** Contenus en attente de modération. */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('moderation_status', 'pending');
    }

    public function isApproved(): bool
    {
        return $this->moderation_status === 'approved';
    }

    /**
     * URL du lecteur iframe Bunny Stream pour ce média (films uniquement).
     * Retourne null si aucune vidéo Bunny n'est attribuée.
     */
    public function bunnyEmbedUrl(): ?string
    {
        if ($this->video_provider !== 'bunny' || empty($this->video_id)) {
            return null;
        }

        $libraryId = $this->video_library_id ?: config('services.bunny.library_id');

        if (empty($libraryId)) {
            return null;
        }

        return "https://iframe.mediadelivery.net/embed/{$libraryId}/{$this->video_id}";
    }

    /**
     * Une série a plusieurs saisons.
     */
    public function seasonsRelation(): HasMany
    {
        return $this->hasMany(Season::class)->orderBy('season_number');
    }

    /**
     * Vérifier si c'est une série.
     */
    public function isSeries(): bool
    {
        return $this->type === 'series';
    }

    /**
     * Vérifier si c'est un film.
     */
    public function isMovie(): bool
    {
        return $this->type === 'movie';
    }
}
