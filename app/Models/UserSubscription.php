<?php

namespace App\Models;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class UserSubscription extends Model
{
    protected $fillable = [
        'user_id', 'subscription_plan_id', 'transaction_id', 'starts_at', 'expires_at', 'status'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Provisionne (souscription OU renouvellement) un abonnement à
     * partir d'une transaction payée. Source unique de vérité partagée
     * par tous les moyens de paiement (PayPal, KPay…).
     *
     * Comportement :
     *  - Idempotent : si la transaction a déjà provisionné un
     *    abonnement, on ne fait rien (re-appel webhook/polling/Job).
     *  - Renouvellement : s'il existe un abonnement actif non expiré
     *    (QUEL QUE SOIT le plan), on PROLONGE sa date d'expiration
     *    (+ durée du plan payé) et on bascule sur le plan choisi —
     *    l'utilisateur cumule ses jours restants.
     *  - Sinon : création d'un nouvel abonnement.
     *
     * @return self|null l'abonnement provisionné, ou null si invalide.
     */
    public static function provisionFromTransaction(Transaction $transaction): ?self
    {
        $planId = $transaction->metadata['subscription_plan_id'] ?? null;

        if (!$planId) {
            Log::error('[UserSubscription] No plan ID in transaction metadata', [
                'transaction_id' => $transaction->id,
            ]);

            return null;
        }

        $plan = SubscriptionPlan::find($planId);

        if (!$plan) {
            Log::error('[UserSubscription] Plan not found', ['plan_id' => $planId]);

            return null;
        }

        // Idempotence : cette transaction a-t-elle déjà provisionné ?
        $existing = static::where('transaction_id', $transaction->id)->first();
        if ($existing) {
            return $existing;
        }

        // Rémunération producteurs : ce paiement d'abonnement crédite +1 vue à
        // tout le contenu approuvé du tier du forfait. Idempotent par transaction.
        app(\App\Services\ProducerRevenueService::class)
            ->creditViewsForSubscription($transaction);

        $now = now();

        // Renouvellement : tout abonnement actif non expiré de
        // l'utilisateur, quel que soit le plan.
        $current = static::where('user_id', $transaction->user_id)
            ->where('status', 'active')
            ->where('expires_at', '>', $now)
            ->latest('expires_at')
            ->first();

        if ($current) {
            // On cumule : prolongation depuis la date d'expiration
            // existante + bascule sur le plan payé.
            $current->update([
                'subscription_plan_id' => $plan->id,
                'expires_at' => $current->expires_at->copy()->addDays($plan->duration_days),
                'transaction_id' => $transaction->id,
            ]);

            Log::info('[UserSubscription] Subscription renewed (cumulated)', [
                'user_id' => $transaction->user_id,
                'plan_id' => $plan->id,
                'transaction_id' => $transaction->id,
                'new_expires_at' => $current->expires_at->toIso8601String(),
            ]);

            return $current;
        }

        // Nouvelle souscription.
        $subscription = static::create([
            'user_id' => $transaction->user_id,
            'subscription_plan_id' => $plan->id,
            'transaction_id' => $transaction->id,
            'starts_at' => $now,
            'expires_at' => $now->copy()->addDays($plan->duration_days),
            'status' => 'active',
        ]);

        Log::info('[UserSubscription] Subscription created', [
            'user_id' => $transaction->user_id,
            'plan_id' => $plan->id,
            'transaction_id' => $transaction->id,
        ]);

        return $subscription;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
