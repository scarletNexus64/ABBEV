<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ligne de filmographie d'un talent. */
class TalentCredit extends Model
{
    protected $fillable = ['talent_id', 'media_id', 'title', 'year', 'role', 'sort_order'];

    protected $casts = [
        'year' => 'integer',
        'sort_order' => 'integer',
    ];

    public function talent(): BelongsTo
    {
        return $this->belongsTo(Talent::class);
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
