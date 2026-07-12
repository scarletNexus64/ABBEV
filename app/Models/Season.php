<?php

namespace App\Models;

use App\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{
    use HasObfuscatedRouteKey;

    protected $fillable = [
        'media_id',
        'season_number',
        'title',
        'description',
        'thumbnail_path',
        'release_year',
        'episodes_count',
    ];

    protected $casts = [
        'season_number' => 'integer',
        'episodes_count' => 'integer',
    ];

    /**
     * Une saison appartient à un média (série).
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * Une saison a plusieurs épisodes.
     */
    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class)->orderBy('episode_number');
    }

    /**
     * Mettre à jour le compteur d'épisodes.
     */
    public function updateEpisodesCount(): void
    {
        $this->update([
            'episodes_count' => $this->episodes()->count()
        ]);
    }
}
