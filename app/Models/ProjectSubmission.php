<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Candidature à un appel à écriture ou à musique. */
class ProjectSubmission extends Model
{
    public const STATUSES = [
        'received' => 'Reçue',
        'shortlisted' => 'Présélectionnée',
        'selected' => 'Lauréate',
        'rejected' => 'Non retenue',
    ];

    protected $fillable = [
        'project_call_id', 'user_id', 'title', 'logline', 'synopsis', 'message',
        'link_url', 'file_path', 'phone', 'status', 'admin_note', 'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(ProjectCall::class, 'project_call_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }
}
