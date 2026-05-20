<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Media extends Model
{
    protected $fillable = [
        'category_id',
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
    ];

    protected $casts = [
        'published_at'   => 'datetime',
        'is_featured'    => 'boolean',
        'video_metadata' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
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
