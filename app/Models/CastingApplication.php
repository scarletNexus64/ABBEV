<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspaceThrough;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Candidature d'un utilisateur de l'app à un rôle d'une annonce. */
class CastingApplication extends Model
{
    use BelongsToWorkspaceThrough;

    public static function workspaceParent(): string
    {
        return 'call';
    }

    public const STATUSES = [
        'pending' => 'Reçue',
        'shortlisted' => 'Présélectionnée',
        'accepted' => 'Retenue',
        'rejected' => 'Non retenue',
    ];

    protected $fillable = [
        'casting_call_id', 'casting_role_id', 'user_id', 'full_name', 'email',
        'phone', 'age', 'city', 'message', 'portfolio_url', 'photo_path',
        'status', 'admin_note', 'reviewed_at',
    ];

    protected $casts = [
        'age' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function call(): BelongsTo
    {
        return $this->belongsTo(CastingCall::class, 'casting_call_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(CastingRole::class, 'casting_role_id');
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
