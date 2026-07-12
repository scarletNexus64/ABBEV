<?php

namespace App\Models;

use App\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasObfuscatedRouteKey;

    protected $fillable = [
        'user_id', 'transaction_id', 'payment_method', 'type', 'amount', 'fees',
        'net_amount', 'currency', 'external_reference', 'description', 'metadata',
        'payer_email', 'payer_name', 'status', 'completed_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
