<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspaceThrough;
use App\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Nommé d'un prix : une œuvre du catalogue, un talent de l'annuaire, ou une
 * entrée libre. Le nom et la photo sont recopiés à la nomination pour que le
 * palmarès reste lisible même si l'œuvre ou le talent disparaît ensuite.
 */
class AwardNominee extends Model
{
    use BelongsToWorkspaceThrough, HasObfuscatedRouteKey;

    public static function workspaceParent(): string
    {
        return 'category';
    }

    protected $fillable = [
        'award_category_id', 'media_id', 'talent_id', 'name', 'subtitle',
        'photo_path', 'votes_count', 'is_winner', 'sort_order',
    ];

    protected $casts = [
        'votes_count' => 'integer',
        'is_winner' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AwardCategory::class, 'award_category_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(AwardVote::class);
    }

    /**
     * Visuel du nommé, du plus spécifique au plus générique : photo propre à
     * la nomination, portrait du talent, affiche de l'œuvre.
     */
    public function imagePath(): ?string
    {
        return $this->photo_path
            ?: $this->talent?->photo_path
            ?: ($this->media?->cover_path ?: $this->media?->thumbnail_path);
    }
}
