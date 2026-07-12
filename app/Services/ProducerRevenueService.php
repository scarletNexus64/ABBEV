<?php

namespace App\Services;

use App\Models\Configuration;
use App\Models\Episode;
use App\Models\Media;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Revenus producteurs (feature #5).
 *
 * Modèle : quand un utilisateur PAIE un abonnement d'un tier donné, tout le
 * contenu APPROUVÉ du même tier reçoit +1 « vue producteur » (producer_views) —
 * chaque film du tier, et chaque épisode d'une série du tier. Le gain d'un
 * producteur = somme, sur ses contenus, de (producer_views × tarif du tier).
 *
 * L'accès au contenu reste libre : le tier ne sert qu'à la rémunération.
 */
class ProducerRevenueService
{
    /** Tarif versé au producteur par vue, pour un tier donné. */
    public function ratePerView(string $tier): float
    {
        return (float) Configuration::getValue('revenue_per_view_' . $tier, 0);
    }

    /** Tarifs des trois tiers (pour l'admin / la simulation). */
    public function rates(): array
    {
        return [
            'classique' => $this->ratePerView('classique'),
            'standard'  => $this->ratePerView('standard'),
            'premium'   => $this->ratePerView('premium'),
        ];
    }

    public function currency(): string
    {
        return (string) Configuration::getValue('revenue_currency', 'XOF');
    }

    /**
     * Crédite +1 vue à tout le contenu approuvé du tier du forfait payé.
     * Idempotent par transaction (drapeau en metadata) : un même paiement ne
     * crédite qu'une fois, même si appelé depuis plusieurs points.
     */
    public function creditViewsForSubscription(Transaction $transaction): void
    {
        if ($transaction->type !== 'subscription') {
            return;
        }
        if (data_get($transaction->metadata, 'producer_views_credited')) {
            return; // déjà crédité
        }

        $planId = data_get($transaction->metadata, 'subscription_plan_id');
        $plan = $planId ? SubscriptionPlan::find($planId) : null;
        if (! $plan) {
            return;
        }
        $tier = $plan->tier ?: 'classique';

        // Films approuvés du tier → +1.
        $movies = Media::query()->approved()
            ->where('type', 'movie')
            ->where('tier', $tier)
            ->increment('producer_views');

        // Épisodes des séries approuvées du tier → +1.
        $episodes = Episode::whereIn('season_id', function ($q) use ($tier) {
            $q->select('seasons.id')
                ->from('seasons')
                ->join('media', 'seasons.media_id', '=', 'media.id')
                ->where('media.moderation_status', 'approved')
                ->where('media.tier', $tier);
        })->increment('producer_views');

        $transaction->update([
            'metadata' => array_merge($transaction->metadata ?? [], [
                'producer_views_credited' => true,
                'producer_views_tier' => $tier,
            ]),
        ]);

        Log::info('[ProducerRevenue] Vues créditées', [
            'transaction_id' => $transaction->id,
            'tier' => $tier,
            'movies' => $movies,
            'episodes' => $episodes,
        ]);
    }

    /**
     * Détail des gains d'un producteur : vues et montant par tier + total.
     *
     * @return array{by_tier:array<string,array{views:int,rate:float,amount:float}>,total_views:int,total_amount:float,currency:string}
     */
    public function earningsForProducer(User $producer): array
    {
        $rates = $this->rates();

        // Vues des films par tier.
        $movieViews = Media::where('user_id', $producer->id)
            ->where('type', 'movie')
            ->groupBy('tier')
            ->selectRaw('tier, COALESCE(SUM(producer_views),0) as v')
            ->pluck('v', 'tier');

        // Vues des épisodes, regroupées par le tier de LEUR série.
        $episodeViews = DB::table('episodes')
            ->join('seasons', 'episodes.season_id', '=', 'seasons.id')
            ->join('media', 'seasons.media_id', '=', 'media.id')
            ->where('media.user_id', $producer->id)
            ->groupBy('media.tier')
            ->selectRaw('media.tier as tier, COALESCE(SUM(episodes.producer_views),0) as v')
            ->pluck('v', 'tier');

        $byTier = [];
        $totalViews = 0;
        $totalAmount = 0.0;
        foreach (Media::TIERS as $tier) {
            $views = (int) ($movieViews[$tier] ?? 0) + (int) ($episodeViews[$tier] ?? 0);
            $rate = $rates[$tier];
            $amount = round($views * $rate, 2);
            $byTier[$tier] = ['views' => $views, 'rate' => $rate, 'amount' => $amount];
            $totalViews += $views;
            $totalAmount += $amount;
        }

        return [
            'by_tier' => $byTier,
            'total_views' => $totalViews,
            'total_amount' => round($totalAmount, 2),
            'currency' => $this->currency(),
        ];
    }
}
