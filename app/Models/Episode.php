<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Episode extends Model
{
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
    ];

    protected $casts = [
        'episode_number' => 'integer',
        'duration'       => 'integer',
        'views_count'    => 'integer',
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
    public function incrementViews(): void
    {
        $this->increment('views_count');
    }
}
