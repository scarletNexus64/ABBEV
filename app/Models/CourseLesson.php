<?php

namespace App\Models;

use App\Concerns\HasObfuscatedRouteKey;
use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Leçon d'un cours : une vidéo (Bunny Stream ou lien direct) ou un PDF.
 *
 * Le fichier et la vidéo ne sont JAMAIS exposés tels quels : l'API délivre à
 * la demande une URL signée à durée de vie courte, après contrôle d'accès.
 */
class CourseLesson extends Model
{
    use HasObfuscatedRouteKey, HasTranslations;

    public array $translatable = ['title', 'summary'];

    protected $fillable = [
        'course_id', 'title', 'summary', 'sort_order', 'is_preview',
        'video_provider', 'video_id', 'video_library_id', 'video_url',
        'duration_minutes', 'file_path', 'pages',
    ];

    protected $casts = [
        'is_preview' => 'boolean',
        'sort_order' => 'integer',
        'duration_minutes' => 'integer',
        'pages' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** La leçon a-t-elle un contenu consultable (vidéo ou PDF) ? */
    public function hasContent(): bool
    {
        return filled($this->file_path) || filled($this->video_id) || filled($this->video_url);
    }

    /** URL du lecteur iframe Bunny, si la vidéo y est hébergée. */
    public function bunnyEmbedUrl(): ?string
    {
        if ($this->video_provider !== 'bunny' || blank($this->video_id)) {
            return null;
        }

        $libraryId = $this->video_library_id ?: config('services.bunny.library_id');

        return $libraryId
            ? "https://iframe.mediadelivery.net/embed/{$libraryId}/{$this->video_id}"
            : null;
    }
}
