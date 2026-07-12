<?php

namespace Tests\Feature;

use App\Jobs\ReconcileKpayTransaction;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\User;
use App\Services\KpayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Flux complet de paiement d'abonnement par Mobile Money (KPay), côté back.
 *
 * On mocke [KpayService] (la couche HTTP vers admin.kpay.site) pour tester
 * TOUTE notre logique métier sans dépendre des credentials KPay ni du réseau :
 *  - initiation (succès / échec / validation),
 *  - normalisation du numéro + mapping provider transmis à KPay,
 *  - vérification de statut + provisioning de l'abonnement,
 *  - réconciliation (logique pure sur la transaction).
 */
class SubscriptionPaymentKpayTest extends TestCase
{
    use RefreshDatabase;

    private function makePlan(int $price = 50, int $days = 30): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Mensuel',
            'description' => 'Accès illimité',
            'price' => $price,
            'duration_days' => $days,
            'features' => ['hd'],
            'is_active' => true,
        ]);
    }

    public function test_initiate_kpay_succes_cree_une_transaction_pending_et_renvoie_la_reference(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $plan = $this->makePlan();

        // Le mock VÉRIFIE que le controller transmet bien à KPay le code
        // provider interne (mappé plus loin par KpayService) ET le numéro
        // normalisé au format international.
        $mock = Mockery::mock(KpayService::class);
        $mock->shouldReceive('initPayment')
            ->once()
            ->with(Mockery::on(function ($params) {
                // Le mobile envoie le code du catalogue ; le contrôleur le
                // transmet tel quel + le pays + le numéro normalisé (237…).
                return ($params['provider'] ?? null) === 'ORANGE_CMR'
                    && ($params['country'] ?? null) === 'CM'
                    && ($params['phoneNumber'] ?? null) === '237670000001'
                    && ($params['amount'] ?? null) === 50
                    && !empty($params['externalId']);
            }))
            ->andReturn([
                'success' => true,
                'data' => ['id' => 'pay_abc123', 'reference' => 'KPAY-REF-1', 'status' => 'PENDING'],
            ]);
        $this->app->instance(KpayService::class, $mock);

        $res = $this->actingAs($user, 'sanctum')->postJson('/api/subscription-payment/initiate', [
            'subscription_plan_id' => $plan->id,
            'payment_method' => 'kpay',
            'mobile_operator' => 'ORANGE_CMR',
            'phone_number' => '670000001',
        ]);

        $res->assertOk()->assertJson([
            'success' => true,
            'payment_method' => 'kpay',
            'reference' => 'pay_abc123',
            'status' => 'PENDING',
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'payment_method' => 'kpay',
            'status' => 'pending',
            'external_reference' => 'pay_abc123',
        ]);

        // Le polling de réconciliation asynchrone est bien programmé.
        Queue::assertPushed(ReconcileKpayTransaction::class);
    }

    public function test_initiate_kpay_echec_renvoie_400_avec_le_message_reel_et_marque_failed(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $plan = $this->makePlan();

        $mock = Mockery::mock(KpayService::class);
        $mock->shouldReceive('initPayment')->once()->andReturn([
            'success' => false,
            'message' => 'Invalid API credentials',
            'http' => 401,
        ]);
        $this->app->instance(KpayService::class, $mock);

        $res = $this->actingAs($user, 'sanctum')->postJson('/api/subscription-payment/initiate', [
            'subscription_plan_id' => $plan->id,
            'payment_method' => 'kpay',
            'mobile_operator' => 'MTN_MOMO_CMR',
            'phone_number' => '670000001',
        ]);

        $res->assertStatus(400)->assertJson([
            'success' => false,
            'message' => 'Invalid API credentials',
        ]);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'payment_method' => 'kpay',
            'status' => 'failed',
        ]);
    }

    public function test_initiate_kpay_valide_operateur_et_numero(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan();

        // Opérateur inconnu pour le pays → 422 (rejeté par le contrôleur).
        $this->actingAs($user, 'sanctum')->postJson('/api/subscription-payment/initiate', [
            'subscription_plan_id' => $plan->id,
            'payment_method' => 'kpay',
            'mobile_operator' => 'BITCOIN',
            'phone_number' => '670000001',
        ])->assertStatus(422)->assertJson(['success' => false]);

        // Numéro manquant → 422 (validation).
        $this->actingAs($user, 'sanctum')->postJson('/api/subscription-payment/initiate', [
            'subscription_plan_id' => $plan->id,
            'payment_method' => 'kpay',
            'mobile_operator' => 'ORANGE_CMR',
        ])->assertStatus(422)->assertJsonValidationErrors(['phone_number']);
    }

    public function test_initiate_exige_authentification(): void
    {
        $plan = $this->makePlan();

        $this->postJson('/api/subscription-payment/initiate', [
            'subscription_plan_id' => $plan->id,
            'payment_method' => 'kpay',
            'mobile_operator' => 'ORANGE_MONEY',
            'phone_number' => '670000001',
        ])->assertUnauthorized();
    }

    public function test_check_status_completed_provisionne_l_abonnement(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan();

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'transaction_id' => 'TXN-TEST-1',
            'payment_method' => 'kpay',
            'type' => 'subscription',
            'amount' => 50,
            'net_amount' => 50,
            'currency' => 'XAF',
            'description' => 'Abonnement Mensuel',
            'status' => 'pending',
            'external_reference' => 'pay_abc123',
            'metadata' => [
                'subscription_plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'duration_days' => $plan->duration_days,
            ],
        ]);

        $mock = Mockery::mock(KpayService::class);
        $mock->shouldReceive('getPayment')->once()->with('pay_abc123')->andReturn([
            'success' => true,
            'data' => ['id' => 'pay_abc123', 'status' => 'COMPLETED'],
        ]);
        // La réconciliation garde sa vraie logique métier.
        $mock->shouldReceive('reconcileTransaction')->passthru();
        $this->app->instance(KpayService::class, $mock);

        $res = $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscription-payment/kpay/status/pay_abc123');

        $res->assertOk()->assertJson([
            'success' => true,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('transactions', [
            'transaction_id' => 'TXN-TEST-1',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }

    public function test_check_status_failed_marque_la_transaction_echouee(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan();

        Transaction::create([
            'user_id' => $user->id,
            'transaction_id' => 'TXN-TEST-2',
            'payment_method' => 'kpay',
            'type' => 'subscription',
            'amount' => 50,
            'net_amount' => 50,
            'currency' => 'XAF',
            'description' => 'Abonnement Mensuel',
            'status' => 'pending',
            'external_reference' => 'pay_fail',
            'metadata' => ['subscription_plan_id' => $plan->id],
        ]);

        $mock = Mockery::mock(KpayService::class);
        $mock->shouldReceive('getPayment')->once()->andReturn([
            'success' => true,
            'data' => ['status' => 'FAILED'],
        ]);
        $mock->shouldReceive('reconcileTransaction')->passthru();
        $this->app->instance(KpayService::class, $mock);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/subscription-payment/kpay/status/pay_fail')
            ->assertOk()
            ->assertJson(['success' => false, 'status' => 'failed']);

        $this->assertDatabaseHas('transactions', [
            'transaction_id' => 'TXN-TEST-2',
            'status' => 'failed',
        ]);
        $this->assertDatabaseMissing('user_subscriptions', [
            'user_id' => $user->id,
        ]);
    }

    public function test_reconcile_transaction_est_idempotent_et_pending_ne_change_rien(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan();
        $kpay = app(KpayService::class);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'transaction_id' => 'TXN-TEST-3',
            'payment_method' => 'kpay',
            'type' => 'subscription',
            'amount' => 50,
            'net_amount' => 50,
            'currency' => 'XAF',
            'description' => 'Abonnement Mensuel',
            'status' => 'pending',
            'metadata' => ['subscription_plan_id' => $plan->id],
        ]);

        // PENDING → reste pending.
        $this->assertSame('pending', $kpay->reconcileTransaction($transaction, 'PENDING'));
        $this->assertSame('pending', $transaction->fresh()->status);

        // COMPLETED → complété + abonnement provisionné.
        $this->assertSame('completed', $kpay->reconcileTransaction($transaction, 'COMPLETED'));
        $this->assertSame('completed', $transaction->fresh()->status);
        $this->assertDatabaseHas('user_subscriptions', ['user_id' => $user->id]);

        // Re-COMPLETED → idempotent : toujours un seul abonnement.
        $kpay->reconcileTransaction($transaction->fresh(), 'COMPLETED');
        $this->assertSame(1, \App\Models\UserSubscription::where('user_id', $user->id)->count());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
