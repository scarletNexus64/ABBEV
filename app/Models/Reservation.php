<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspaceThrough;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reservation extends Model
{
    use BelongsToWorkspaceThrough, HasFactory;

    public static function workspaceParent(): string
    {
        return 'screening';
    }

    protected $fillable = [
        'reference',
        'user_id',
        'screening_id',
        'ticket_type_id',
        'transaction_id',
        'quantity',
        'redeemed_quantity',
        'unit_price',
        'total_amount',
        'currency',
        'status',
        'confirmed_at',
        'redeemed_at',
        'redeemed_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity'          => 'integer',
            'redeemed_quantity' => 'integer',
            'unit_price'        => 'decimal:2',
            'total_amount'      => 'decimal:2',
            'confirmed_at'      => 'datetime',
            'redeemed_at'       => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function screening()
    {
        return $this->belongsTo(Screening::class);
    }

    public function ticketType()
    {
        return $this->belongsTo(TicketType::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function redeemer()
    {
        return $this->belongsTo(User::class, 'redeemed_by');
    }

    /** Entrées encore utilisables sur ce billet ou ce code. */
    public function remainingEntries(): int
    {
        return max(0, (int) $this->quantity - (int) $this->redeemed_quantity);
    }
}
