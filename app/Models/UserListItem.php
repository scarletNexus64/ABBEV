<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une entrée de "Ma liste" : le lien entre un utilisateur et un média
 * (film ou série) qu'il a ajouté à sa liste.
 */
class UserListItem extends Model
{
    protected $table = 'user_list';

    protected $fillable = [
        'user_id',
        'media_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
