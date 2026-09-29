<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspaceThrough;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Promesse de soutien à un appel à financement.
 *
 * Aucun argent ne transite par l'app : l'utilisateur s'engage sur un
 * montant, l'équipe ABBEV le recontacte pour finaliser, puis CONFIRME la
 * promesse dans l'admin à réception des fonds. Seules les promesses
 * confirmées comptent dans le montant affiché.
 */
class ProjectPledge extends Model
{
    use BelongsToWorkspaceThrough;

    public static function workspaceParent(): string
    {
        return 'call';
    }

    public const STATUSES = [
        'pending' => 'À confirmer',
        'confirmed' => 'Confirmée',
        'cancelled' => 'Annulée',
    ];

    protected $fillable = [
        'project_call_id', 'user_id', 'amount', 'currency', 'reward_title',
        'message', 'phone', 'is_anonymous', 'status', 'confirmed_at', 'admin_note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_anonymous' => 'boolean',
        'confirmed_at' => 'datetime',
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
