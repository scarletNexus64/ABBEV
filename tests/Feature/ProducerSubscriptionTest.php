<?php

namespace Tests\Feature;

use App\Models\ProducerPlan;
use App\Models\ProducerSubscription;
use App\Models\Transaction;
use App\Models\User;
use App\Services\KpayService;
use App\Services\StripeService;
use Database\Seeders\ProducerPlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Pack producteur : un producteur paie (KPay ou Stripe, depuis le dashboard)
 * pour ouvrir son espace. Sans abonnement en cours, l'espace est entièrement
 * verrouillé, équipe comprise ; seule la page d'abonnement reste ouverte.
 */
class ProducerSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function plan(array $attributes = []): ProducerPlan
    {
        return ProducerPlan::create($attributes + [
            'name' => 'Pack Producteur',
            'price' => 20000,
            'billing_period' => 'month',
            'period_count' => 1,
            'features' => ['Upload de vos films'],
            'is_active' => true,
        ]);
    }

    private function producer(string $name = 'Prod'): User
    {
        return User::factory()->create(['role' => 'producer', 'name' => $name]);
    }

    private function member(User $producer): User
    {
        $member = User::factory()->create(['role' => 'producer']);
        $member->forceFill(['producer_id' => $producer->id, 'permissions' => ['contents', 'talents']])->save();

        return $member;
    }

    private function mockKpay(string $status = 'COMPLETED'): void
    {
        $kpay = Mockery::mock(KpayService::class)->makePartial();
        $kpay->shouldReceive('isConfigured')->andReturn(true);
        $kpay->shouldReceive('initPayment')->andReturnUsing(fn () => [
            'success' => true,
            'data' => ['id' => 'pay_' . uniqid(), 'reference' => 'KPAY-REF', 'status' => 'PENDING'],
        ]);
        $kpay->shouldReceive('getPayment')->andReturn(['success' => true, 'data' => ['status' => $status]]);
        $this->app->instance(KpayService::class, $kpay);
    }

    /** Paie le pack par Mobile Money et renvoie l'URL de suivi. */
    private function payByKpay(User $producer): string
    {
        return $this->actingAs($producer)->postJson(route('producer.subscription.kpay'), [
            'country_code' => 'CM',
            'mobile_operator' => 'ORANGE_CMR',
            'phone_number' => '670000001',
        ])->assertOk()->json('status_url');
    }

    /* ---------------------------------------------------------------
     |  Verrouillage
     * --------------------------------------------------------------- */

    public function test_sans_pack_actif_les_espaces_restent_ouverts(): void
    {
        $producer = $this->producer();
        $this->actingAs($producer)->get(route('admin.dashboard'))->assertOk();

        $this->plan(['is_active' => false]);
        $this->actingAs($producer)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($producer)->get(route('films.index'))->assertOk();
    }

    public function test_un_producteur_cree_par_l_admin_a_un_espace_verrouille(): void
    {
        Mail::fake();
        $this->plan();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('producers.store'), ['name' => 'Studio Neuf', 'email' => 'neuf@example.com']);
        $producer = User::where('email', 'neuf@example.com')->firstOrFail();

        $this->assertTrue($producer->isWorkspaceLocked());

        $subscribe = route('producer.subscription.show');
        foreach (['admin.dashboard', 'films.index', 'media.create', 'talents.index', 'team.index', 'admin.bunny.uploads.index'] as $name) {
            $this->actingAs($producer)->get(route($name))->assertRedirect($subscribe);
        }
        $this->actingAs($producer)->post(route('media.store'), ['title' => 'Film'])->assertRedirect($subscribe);

        // Upload vidéo (AJAX) : refusé sans redirection.
        $this->actingAs($producer)->postJson(route('admin.bunny.upload.start'), ['filename' => 'film.mp4', 'size' => 10])
            ->assertStatus(402)->assertJsonPath('subscribe_url', $subscribe);

        // La page d'abonnement invite à payer et le menu ne propose aucun module.
        $this->actingAs($producer)->get($subscribe)->assertOk()
            ->assertSee('Votre espace producteur est verrouillé')
            ->assertSee('20 000')
            ->assertSee('par mois')
            ->assertSee('Activer mon espace')
            ->assertDontSee(route('films.index'))
            ->assertDontSee(route('team.index'));
    }

    public function test_l_equipe_est_verrouillee_avec_son_producteur_et_ne_paie_pas(): void
    {
        $this->plan();
        $producer = $this->producer('Studio Alpha');
        $member = $this->member($producer);

        $this->actingAs($member)->get(route('films.index'))->assertRedirect(route('producer.subscription.show'));
        $this->actingAs($member)->get(route('producer.subscription.show'))->assertOk()
            ->assertSee("L'espace de Studio Alpha est verrouillé", false)
            ->assertDontSee('Payer ');
        $this->actingAs($member)->postJson(route('producer.subscription.kpay'), [
            'country_code' => 'CM', 'mobile_operator' => 'ORANGE_CMR', 'phone_number' => '670000001',
        ])->assertForbidden();

        // Le titulaire paie : l'équipe retrouve ses modules.
        ProducerSubscription::grant($producer, 1, null);
        $this->actingAs($member)->get(route('films.index'))->assertOk();
    }

    public function test_l_admin_n_est_jamais_verrouille(): void
    {
        $this->plan();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertFalse($admin->isWorkspaceLocked());
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($admin)->get(route('films.index'))->assertOk();
    }

    public function test_un_abonnement_expire_reverrouille_l_espace(): void
    {
        $this->plan();
        $producer = $this->producer();
        ProducerSubscription::create([
            'producer_id' => $producer->id, 'source' => 'admin', 'status' => 'active',
            'starts_at' => now()->subMonth(), 'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($producer)->get(route('admin.dashboard'))->assertRedirect(route('producer.subscription.show'));
    }

    /* ---------------------------------------------------------------
     |  Paiement
     * --------------------------------------------------------------- */

    public function test_paiement_mobile_money_ouvre_l_espace(): void
    {
        Queue::fake();
        $this->plan();
        $this->mockKpay('COMPLETED');
        $producer = $this->producer();

        $statusUrl = $this->payByKpay($producer);

        $transaction = Transaction::where('user_id', $producer->id)->sole();
        $this->assertSame('producer_subscription', $transaction->type);
        $this->assertSame('kpay', $transaction->payment_method);
        $this->assertSame('pending', $transaction->status);
        $this->assertEquals(20000, $transaction->amount);

        $this->actingAs($producer)->getJson($statusUrl)->assertOk()
            ->assertJson(['status' => 'completed', 'redirect' => route('admin.dashboard')]);

        $period = ProducerSubscription::where('producer_id', $producer->id)->sole();
        $this->assertSame($transaction->id, $period->transaction_id);
        $this->assertTrue($period->expires_at->between(now()->addMonth()->subMinute(), now()->addMonth()->addMinute()));

        $this->assertFalse($producer->fresh()->isWorkspaceLocked());
        $this->actingAs($producer)->get(route('admin.dashboard'))->assertOk();

        // Confirmations répétées (polling, Job) : une seule période.
        $this->actingAs($producer)->getJson($statusUrl)->assertOk();
        app(KpayService::class)->reconcileTransaction($transaction->fresh(), 'COMPLETED');
        $this->assertSame(1, ProducerSubscription::count());
    }

    public function test_paiement_mobile_money_echoue_laisse_l_espace_verrouille(): void
    {
        Queue::fake();
        $this->plan();
        $this->mockKpay('FAILED');
        $producer = $this->producer();

        $statusUrl = $this->payByKpay($producer);
        $this->actingAs($producer)->getJson($statusUrl)->assertOk()->assertJson(['status' => 'failed']);

        $this->assertSame(0, ProducerSubscription::count());
        $this->assertTrue($producer->fresh()->isWorkspaceLocked());
    }

    public function test_les_periodes_s_enchainent_selon_la_periode_du_pack(): void
    {
        Queue::fake();
        $this->plan(['billing_period' => 'year', 'period_count' => 1, 'price' => 150000]);
        $this->mockKpay('COMPLETED');
        $producer = $this->producer();

        $this->actingAs($producer)->getJson($this->payByKpay($producer));
        $this->actingAs($producer)->getJson($this->payByKpay($producer));

        [$first, $second] = ProducerSubscription::orderBy('id')->get()->all();
        $this->assertEquals($first->expires_at, $second->starts_at);
        $this->assertTrue($second->expires_at->between(now()->addYears(2)->subMinute(), now()->addYears(2)->addMinute()));
        $this->assertEquals(150000, Transaction::first()->amount);
    }

    public function test_paiement_par_carte_ouvre_l_espace(): void
    {
        $this->plan();
        $stripe = Mockery::mock(StripeService::class)->makePartial();
        $stripe->shouldReceive('isConfigured')->andReturn(true);
        $stripe->shouldReceive('createPaymentIntent')->once()
            ->with(Mockery::on(fn ($p) => $p['amount'] == 20000 && $p['metadata']['type'] === 'producer_subscription'))
            ->andReturn(['success' => true, 'payment_intent_id' => 'pi_123', 'client_secret' => 'pi_123_secret', 'publishable_key' => 'pk_test']);
        $stripe->shouldReceive('retrievePaymentIntent')->with('pi_123')->andReturn(['success' => true, 'status' => 'succeeded']);
        $this->app->instance(StripeService::class, $stripe);
        $producer = $this->producer();

        $res = $this->actingAs($producer)->postJson(route('producer.subscription.stripe'))->assertOk()
            ->assertJson(['client_secret' => 'pi_123_secret', 'publishable_key' => 'pk_test']);

        $this->actingAs($producer)->getJson($res->json('status_url'))->assertJson(['status' => 'completed']);
        $this->assertFalse($producer->fresh()->isWorkspaceLocked());
    }

    public function test_le_webhook_stripe_ouvre_l_espace(): void
    {
        $this->plan();
        $producer = $this->producer();
        $transaction = Transaction::create([
            'user_id' => $producer->id, 'transaction_id' => 'PRD-TEST', 'payment_method' => 'stripe',
            'type' => 'producer_subscription', 'amount' => 20000, 'net_amount' => 20000, 'currency' => 'XAF',
            'external_reference' => 'pi_webhook', 'status' => 'pending',
            'metadata' => ['producer_plan_id' => ProducerPlan::current()->id, 'billing_period' => 'month', 'period_count' => 1],
        ]);
        $stripe = Mockery::mock(StripeService::class)->makePartial();
        $stripe->shouldReceive('verifyWebhookSignature')->andReturn(true);
        $this->app->instance(StripeService::class, $stripe);

        $this->postJson('/api/webhooks/stripe', [
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_webhook']],
        ])->assertOk();

        $this->assertSame('completed', $transaction->fresh()->status);
        $this->assertFalse($producer->fresh()->isWorkspaceLocked());
    }

    public function test_un_producteur_ne_suit_pas_le_paiement_d_un_autre(): void
    {
        Queue::fake();
        $this->plan();
        $this->mockKpay();
        $statusUrl = $this->payByKpay($this->producer('Alpha'));

        $this->actingAs($this->producer('Beta'))->getJson($statusUrl)->assertNotFound();
    }

    /* ---------------------------------------------------------------
     |  Admin
     * --------------------------------------------------------------- */

    public function test_l_admin_offre_puis_coupe_l_acces(): void
    {
        $this->plan();
        $admin = User::factory()->create(['role' => 'admin']);
        $producer = $this->producer();

        $this->actingAs($admin)->post(route('producers.access.grant', $producer), ['months' => 3])->assertSessionHas('success');
        $period = ProducerSubscription::sole();
        $this->assertSame('admin', $period->source);
        $this->assertSame($admin->id, $period->granted_by);
        $this->assertFalse($producer->fresh()->isWorkspaceLocked());
        $this->actingAs($admin)->get(route('producers.show', $producer))->assertOk()->assertSee('Offert par ' . $admin->name);
        $this->actingAs($admin)->get(route('producers.index'))->assertOk()
            ->assertSee("Jusqu'au " . $period->expires_at->format('d/m/Y'), false);

        $this->actingAs($admin)->delete(route('producers.access.revoke', $producer))->assertSessionHas('success');
        $this->assertTrue($producer->fresh()->isWorkspaceLocked());
        $this->actingAs($admin)->get(route('producers.index'))->assertOk()->assertSee('Verrouillé');
    }

    public function test_l_admin_configure_le_pack_unique(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('producer-plan.edit'))->assertOk();

        $this->actingAs($admin)->put(route('producer-plan.update'), [
            'name' => 'Pack Studio', 'price' => 150000, 'billing_period' => 'year', 'period_count' => 1,
            'features' => ['Upload', ''], 'is_active' => '1',
        ])->assertRedirect(route('producer-plan.edit'));
        $this->actingAs($admin)->put(route('producer-plan.update'), [
            'name' => 'Pack Studio', 'price' => 150000, 'billing_period' => 'year', 'period_count' => 1, 'is_active' => '1',
        ]);

        $plan = ProducerPlan::sole();
        $this->assertSame('year', $plan->billing_period);
        $this->assertSame('par an', $plan->periodLabel());
        $this->actingAs($this->producer())->get(route('producer.subscription.show'))->assertSee('150 000')->assertSee('par an');

        // Réservé à l'admin, même pour un producteur abonné.
        $subscribed = $this->producer();
        ProducerSubscription::grant($subscribed, 1, $admin);
        $this->actingAs($subscribed)->get(route('producer-plan.edit'))->assertForbidden();
    }

    public function test_le_seeder_cree_le_pack_une_seule_fois_sans_l_ecraser(): void
    {
        $this->seed(ProducerPlanSeeder::class);
        $plan = ProducerPlan::sole();
        $this->assertEquals(20000, $plan->price);
        $this->assertSame('month', $plan->billing_period);
        $this->assertTrue($plan->is_active);

        $plan->update(['price' => 25000]);
        $this->seed(ProducerPlanSeeder::class);

        $this->assertSame(1, ProducerPlan::count());
        $this->assertEquals(25000, ProducerPlan::sole()->price);
    }

    public function test_l_historique_mobile_ignore_le_pack_producteur(): void
    {
        $producer = $this->producer();
        Transaction::create([
            'user_id' => $producer->id, 'transaction_id' => 'PRD-HIDDEN', 'payment_method' => 'kpay',
            'type' => 'producer_subscription', 'amount' => 20000, 'net_amount' => 20000, 'currency' => 'XAF', 'status' => 'pending',
        ]);

        $this->actingAs($producer, 'sanctum')->getJson('/api/subscription-payment/transactions')
            ->assertOk()->assertJsonCount(0, 'data');
    }
}
