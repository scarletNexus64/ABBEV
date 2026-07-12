<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Configuration;
use App\Models\Episode;
use App\Models\Media;
use App\Models\Season;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ProducerRevenueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Revenus producteurs : un paiement d'abonnement d'un tier crédite +1 vue à
 * tout le contenu approuvé du même tier, et le gain = vues × tarif du tier.
 */
class ProducerRevenueTest extends TestCase
{
    use RefreshDatabase;

    private ProducerRevenueService $svc;
    private User $producer;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = app(ProducerRevenueService::class);
        $this->producer = User::factory()->create(['role' => 'producer']);
        $this->category = Category::firstOrCreate(['slug' => 'action'], ['name' => 'Action']);

        Configuration::updateOrCreate(['key' => 'revenue_per_view_standard'], ['value' => '10', 'group' => 'revenue']);
        Configuration::updateOrCreate(['key' => 'revenue_per_view_classique'], ['value' => '5', 'group' => 'revenue']);
        Configuration::updateOrCreate(['key' => 'revenue_per_view_premium'], ['value' => '20', 'group' => 'revenue']);
    }

    private function movie(string $tier, string $status = 'approved'): Media
    {
        return Media::create([
            'user_id' => $this->producer->id,
            'category_id' => $this->category->id,
            'type' => 'movie',
            'title' => 'Film ' . uniqid(),
            'slug' => 'film-' . uniqid(),
            'tier' => $tier,
            'moderation_status' => $status,
        ]);
    }

    private function subscriptionTransaction(SubscriptionPlan $plan): Transaction
    {
        return Transaction::create([
            'user_id' => User::factory()->create()->id,
            'transaction_id' => 'TXN-' . uniqid(),
            'payment_method' => 'kpay',
            'type' => 'subscription',
            'amount' => $plan->price,
            'net_amount' => $plan->price,
            'currency' => 'XOF',
            'status' => 'completed',
            'metadata' => ['subscription_plan_id' => $plan->id],
        ]);
    }

    public function test_credite_le_contenu_du_tier_du_forfait(): void
    {
        $std = $this->movie('standard');
        $classic = $this->movie('classique');
        $plan = SubscriptionPlan::create(['name' => 'Std', 'tier' => 'standard', 'price' => 1000, 'duration_days' => 30]);

        $this->svc->creditViewsForSubscription($this->subscriptionTransaction($plan));

        $this->assertSame(1, $std->fresh()->producer_views);
        $this->assertSame(0, $classic->fresh()->producer_views); // autre tier, pas crédité
    }

    public function test_ne_credite_pas_le_contenu_non_approuve(): void
    {
        $pending = $this->movie('standard', 'pending');
        $plan = SubscriptionPlan::create(['name' => 'Std', 'tier' => 'standard', 'price' => 1000, 'duration_days' => 30]);

        $this->svc->creditViewsForSubscription($this->subscriptionTransaction($plan));

        $this->assertSame(0, $pending->fresh()->producer_views);
    }

    public function test_credit_idempotent_par_transaction(): void
    {
        $movie = $this->movie('standard');
        $plan = SubscriptionPlan::create(['name' => 'Std', 'tier' => 'standard', 'price' => 1000, 'duration_days' => 30]);
        $tx = $this->subscriptionTransaction($plan);

        $this->svc->creditViewsForSubscription($tx);
        $this->svc->creditViewsForSubscription($tx->fresh()); // 2e appel

        $this->assertSame(1, $movie->fresh()->producer_views); // toujours 1
    }

    public function test_credite_les_episodes_des_series_du_tier(): void
    {
        $serie = Media::create([
            'user_id' => $this->producer->id, 'category_id' => $this->category->id,
            'type' => 'series', 'title' => 'Série', 'slug' => 'serie-' . uniqid(),
            'tier' => 'premium', 'moderation_status' => 'approved',
        ]);
        $season = Season::create(['media_id' => $serie->id, 'season_number' => 1]);
        $ep = Episode::create(['season_id' => $season->id, 'episode_number' => 1, 'title' => 'E1']);
        $plan = SubscriptionPlan::create(['name' => 'Prem', 'tier' => 'premium', 'price' => 5000, 'duration_days' => 30]);

        $this->svc->creditViewsForSubscription($this->subscriptionTransaction($plan));

        $this->assertSame(1, $ep->fresh()->producer_views);
    }

    public function test_calcul_des_gains_par_tier(): void
    {
        $this->movie('standard')->update(['producer_views' => 3]); // 3 × 10 = 30
        $this->movie('classique')->update(['producer_views' => 4]); // 4 × 5 = 20

        $earnings = $this->svc->earningsForProducer($this->producer);

        $this->assertSame(30.0, $earnings['by_tier']['standard']['amount']);
        $this->assertSame(20.0, $earnings['by_tier']['classique']['amount']);
        $this->assertSame(7, $earnings['total_views']);
        $this->assertSame(50.0, $earnings['total_amount']);
    }
}
