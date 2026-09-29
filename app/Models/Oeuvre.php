<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\HasTranslations;

/**
 * Document (PDF) d'une rubrique de type `oeuvre`, lu dans l'app via le
 * lecteur intégré.
 */
class Oeuvre extends Model
{
    use BelongsToWorkspace, HasTranslations;

    /** Champs exposés à l'app et traduits via la table `translations`. */
    public array $translatable = ['title', 'description'];

    protected $fillable = [
        'rubrique_id', 'title', 'slug', 'author', 'description', 'pages',
        'cover_path', 'file_path', 'is_active', 'sort_order', 'published_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'pages' => 'integer',
        'sort_order' => 'integer',
    ];

    public function rubrique(): BelongsTo
    {
        return $this->belongsTo(Rubrique::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
