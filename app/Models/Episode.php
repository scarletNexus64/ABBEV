<?php

namespace App\Models;

use App\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Episode extends Model
{
    use HasObfuscatedRouteKey;

    protected $fillable = [
        'season_id',
        'episode_number',
        'title',
        'description',
        'duration',
        'video_path',
        'video_provider',
        'video_id',
        'video_library_id',
        'video_metadata',
        'thumbnail_path',
        'published_at',
        'views_count',
        'producer_views',
    ];

    protected $casts = [
        'episode_number' => 'integer',
        'duration'       => 'integer',
        'views_count'    => 'integer',
        'producer_views' => 'integer',
        'published_at'   => 'datetime',
        'video_metadata' => 'array',
    ];

    /**
     * Un épisode appartient à une saison.
     */
    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /**
     * Obtenir le média (série) via la saison.
     */
    public function media()
    {
        return $this->season->media ?? null;
    }

    /**
     * Incrémenter le compteur de vues.
     */
    /**
     * Vues « producteur » de cet épisode : +1 par abonnement payé du tier de
     * sa série. Hérité du tier de la série (via season → media.tier).
     */
    public function producerTier(): ?string
    {
        return $this->season?->media?->tier;
    }

    public function incrementViews(): void
    {
        $this->increment('views_count');
    }
}
