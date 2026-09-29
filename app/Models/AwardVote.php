<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspaceThrough;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Vote d'un compte dans une catégorie (un seul par catégorie). */
class AwardVote extends Model
{
    use BelongsToWorkspaceThrough;

    public static function workspaceParent(): string
    {
        return 'category';
    }

    protected $fillable = ['award_category_id', 'award_nominee_id', 'user_id', 'ip_address'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(AwardCategory::class, 'award_category_id');
    }

    public function nominee(): BelongsTo
    {
        return $this->belongsTo(AwardNominee::class, 'award_nominee_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
