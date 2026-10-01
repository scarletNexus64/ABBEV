<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Une période d'accès à l'espace producteur, payée par le producteur ou
 * offerte par l'admin. Les périodes s'enchaînent : un renouvellement démarre
 * à la fin de l'accès en cours, le producteur ne perd aucun jour.
 */
class ProducerSubscription extends Model
{
    protected $fillable = [
        'producer_id', 'producer_plan_id', 'transaction_id', 'source', 'granted_by',
        'starts_at', 'expires_at', 'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /** Périodes non annulées et pas encore terminées (en cours ou à venir). */
    public function scopeUnexpired($query)
    {
        return $query->where('status', 'active')->where('expires_at', '>', now());
    }

    /** L'espace de ce producteur est-il couvert en ce moment ? */
    public static function coversNow(int $producerId): bool
    {
        return static::where('producer_id', $producerId)
            ->unexpired()
            ->where('starts_at', '<=', now())
            ->exists();
    }

    /** Fin de l'accès en cours (périodes enchaînées comprises), ou null. */
    public static function accessEndsAt(int $producerId): ?CarbonInterface
    {
        return static::where('producer_id', $producerId)->unexpired()
            ->latest('expires_at')->first()?->expires_at;
    }

    /** Début de la prochaine période : fin de l'accès en cours, sinon maintenant. */
    private static function nextStartFor(int $producerId): CarbonInterface
    {
        return static::accessEndsAt($producerId) ?? now();
    }

    /**
     * Provisionne la période payée par une transaction `producer_subscription`
     * complétée. Source unique partagée par KPay (polling, Job) et Stripe
     * (webhook, confirmation depuis le dashboard).
     *
     * Idempotent : une transaction ne provisionne qu'une période (index unique
     * sur transaction_id), même si plusieurs canaux confirment en même temps.
     */
    public static function provisionFromTransaction(Transaction $transaction): ?self
    {
        if ($transaction->type !== 'producer_subscription' || $transaction->status !== 'completed') {
            return null;
        }

        if ($existing = static::where('transaction_id', $transaction->id)->first()) {
            return $existing;
        }

        // Période figée au moment du paiement : si l'admin change le pack
        // entre-temps, le producteur reçoit ce qu'il a payé.
        $meta = $transaction->metadata ?? [];
        $plan = ProducerPlan::find($meta['producer_plan_id'] ?? null);
        $period = $meta['billing_period'] ?? $plan?->billing_period ?? 'month';
        $count = (int) ($meta['period_count'] ?? $plan?->period_count ?? 1);

        try {
            $subscription = DB::transaction(function () use ($transaction, $plan, $period, $count) {
                // Deux paiements simultanés du même producteur s'enchaînent
                // au lieu de démarrer tous les deux maintenant.
                User::whereKey($transaction->user_id)->lockForUpdate()->first();

                if ($existing = static::where('transaction_id', $transaction->id)->first()) {
                    return $existing;
                }

                $start = static::nextStartFor($transaction->user_id);

                return static::create([
                    'producer_id' => $transaction->user_id,
                    'producer_plan_id' => $plan?->id,
                    'transaction_id' => $transaction->id,
                    'source' => 'payment',
                    'starts_at' => $start,
                    'expires_at' => ProducerPlan::addPeriod($start, $period, $count),
                    'status' => 'active',
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return static::where('transaction_id', $transaction->id)->first();
        }

        Log::info('[ProducerSubscription] Période provisionnée', [
            'producer_id' => $subscription->producer_id,
            'transaction_id' => $transaction->transaction_id,
            'expires_at' => $subscription->expires_at->toIso8601String(),
        ]);

        return $subscription;
    }

    /** Accès offert par l'admin (enchaîné après l'accès en cours). */
    public static function grant(User $producer, int $months, ?User $admin): self
    {
        return DB::transaction(function () use ($producer, $months, $admin) {
            User::whereKey($producer->id)->lockForUpdate()->first();

            $start = static::nextStartFor($producer->id);

            return static::create([
                'producer_id' => $producer->id,
                'producer_plan_id' => ProducerPlan::current()?->id,
                'source' => 'admin',
                'granted_by' => $admin?->id,
                'starts_at' => $start,
                'expires_at' => $start->copy()->addMonthsNoOverflow($months),
                'status' => 'active',
            ]);
        });
    }

    /** Coupe l'accès : périodes en cours et à venir annulées. */
    public static function revoke(User $producer): int
    {
        return static::where('producer_id', $producer->id)->unexpired()
            ->update(['status' => 'cancelled']);
    }

    public function producer()
    {
        return $this->belongsTo(User::class, 'producer_id');
    }

    public function plan()
    {
        return $this->belongsTo(ProducerPlan::class, 'producer_plan_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function grantedBy()
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
