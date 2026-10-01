<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ReconcileKpayTransaction;
use App\Models\Currency;
use App\Models\ProducerPlan;
use App\Models\ProducerSubscription;
use App\Models\Transaction;
use App\Models\User;
use App\Services\KpayService;
use App\Services\StripeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Abonnement du producteur à son espace (pack producteur), payé depuis le
 * dashboard par Mobile Money (KPay) ou par carte (Stripe).
 *
 * C'est la seule page accessible à un espace verrouillé (voir
 * EnsureWorkspaceSubscription). Seul le producteur titulaire paie ; son équipe
 * voit l'état de l'espace.
 *
 * Le paiement est confirmé par la même mécanique que les abonnements de
 * l'app : polling de cette page, Job ReconcileKpayTransaction, webhook Stripe.
 * Tous aboutissent à ProducerSubscription::provisionFromTransaction.
 */
class ProducerSubscriptionController extends Controller
{
    public function __construct(
        private KpayService $kpay,
        private StripeService $stripe,
    ) {
    }

    public function show(Request $request)
    {
        $user = $request->user();
        $plan = ProducerPlan::current();

        $payments = $user->isProducerOwner()
            ? Transaction::where('user_id', $user->id)
                ->where('type', 'producer_subscription')
                ->latest()->limit(10)->get()
            : collect();

        // Pays & opérateurs Mobile Money (même catalogue que l'app).
        $countries = collect(config('kpay.countries', []))
            ->map(fn (array $c, string $iso) => [
                'code' => $iso,
                'name' => $c['name'],
                'flag' => $c['flag'] ?? '',
                'dial' => $c['dial'],
                'operators' => array_map(fn ($op) => [
                    'code' => $op['code'],
                    'label' => $op['label'] ?? $op['code'],
                ], $c['operators'] ?? []),
            ])->values();

        return view('producer-subscription.show', [
            'user' => $user,
            'owner' => $user->isProducerOwner() ? $user : $user->producer,
            'plan' => $plan,
            'locked' => $user->isWorkspaceLocked(),
            'accessEndsAt' => ProducerSubscription::accessEndsAt($user->workspaceId()),
            'canPay' => $user->isProducerOwner() && $plan?->is_active,
            'payments' => $payments,
            'countries' => $countries,
            'defaultCountry' => strtoupper($user->country_code ?? 'CM'),
            'kpayEnabled' => $this->kpay->isConfigured(),
            'stripeEnabled' => $this->stripe->isConfigured(),
        ]);
    }

    /** Paiement Mobile Money : l'utilisateur valide sur son téléphone. */
    public function payKpay(Request $request): JsonResponse
    {
        $plan = $this->payablePlan($request);

        $validated = $request->validate([
            'country_code' => 'required|string|size:2',
            'mobile_operator' => 'required|string|max:50',
            'phone_number' => 'required|string|max:20',
        ]);

        $countryCode = strtoupper($validated['country_code']);
        $country = $this->kpay->country($countryCode);
        $operator = $country ? $this->kpay->findOperator($countryCode, $validated['mobile_operator']) : null;

        if (! $operator) {
            return response()->json(['message' => "Cet opérateur n'est pas disponible pour le pays choisi."], 422);
        }

        $user = $request->user();
        $transaction = $this->openTransaction($user, $plan, 'kpay');

        // Prix du pack (FCFA) converti dans la devise de l'opérateur, comme
        // pour les abonnements de l'app.
        $amountLocal = Currency::convertFromXofTo((float) $plan->price, $operator['currency']) ?? (float) $plan->price;
        $amount = $amountLocal == floor($amountLocal) ? (int) $amountLocal : round($amountLocal, 2);

        $result = $this->kpay->initPayment([
            'amount' => $amount,
            'provider' => $operator['code'],
            'country' => $countryCode,
            'phoneNumber' => KpayService::normalizeMsisdn($validated['phone_number'], $country['dial']),
            'externalId' => $transaction->transaction_id,
        ]);

        $kpayId = $result['data']['id'] ?? null;

        if (! $result['success'] || ! $kpayId) {
            $transaction->update(['status' => 'failed']);

            Log::warning('[ProducerSubscription] KPay init failed', [
                'transaction_id' => $transaction->transaction_id,
                'producer_id' => $user->id,
                'reason' => $result['message'] ?? 'id KPay manquant',
            ]);

            return response()->json([
                'message' => $result['message'] ?? "Le paiement Mobile Money n'a pas pu être lancé. Réessayez.",
            ], 400);
        }

        $transaction->update([
            'external_reference' => $kpayId,
            'metadata' => array_merge($transaction->metadata, [
                'kpay_id' => $kpayId,
                'kpay_reference' => $result['data']['reference'] ?? null,
                'kpay_operator' => $operator['code'],
                'kpay_country' => $countryCode,
                'kpay_currency' => $operator['currency'],
                'kpay_amount_local' => $amount,
            ]),
        ]);

        // Filet serveur si l'onglet est fermé avant la validation.
        ReconcileKpayTransaction::dispatch($transaction->id);

        return response()->json([
            'status' => 'pending',
            'status_url' => route('producer.subscription.status', $transaction),
            'message' => 'Validez le paiement sur votre téléphone (code secret Mobile Money).',
        ]);
    }

    /** Paiement par carte : PaymentIntent confirmé par Stripe.js sur la page. */
    public function payStripe(Request $request): JsonResponse
    {
        $plan = $this->payablePlan($request);

        if (! $this->stripe->isConfigured()) {
            return response()->json(['message' => "Le paiement par carte n'est pas disponible pour le moment."], 503);
        }

        $user = $request->user();
        $transaction = $this->openTransaction($user, $plan, 'stripe');

        $result = $this->stripe->createPaymentIntent([
            'amount' => (float) $plan->price,
            'currency' => $transaction->currency,
            'description' => $transaction->description,
            'metadata' => [
                'transaction_id' => $transaction->transaction_id,
                'producer_id' => $user->id,
                'type' => 'producer_subscription',
            ],
        ]);

        if (! $result['success']) {
            $transaction->update(['status' => 'failed']);

            return response()->json([
                'message' => $result['message'] ?? "Le paiement par carte n'a pas pu être lancé. Réessayez.",
            ], 400);
        }

        $transaction->update([
            'external_reference' => $result['payment_intent_id'],
            'metadata' => array_merge($transaction->metadata, [
                'stripe_payment_intent_id' => $result['payment_intent_id'],
            ]),
        ]);

        return response()->json([
            'client_secret' => $result['client_secret'],
            'publishable_key' => $result['publishable_key'],
            'status_url' => route('producer.subscription.status', $transaction),
        ]);
    }

    /**
     * État d'un paiement, interrogé en boucle par la page. Vérifie auprès du
     * prestataire et ouvre l'espace dès que c'est payé (idempotent).
     */
    public function status(Request $request, Transaction $transaction): JsonResponse
    {
        abort_unless(
            $transaction->type === 'producer_subscription' && (int) $transaction->user_id === (int) $request->user()->id,
            404
        );

        if ($transaction->status === 'pending' && $transaction->external_reference) {
            if ($transaction->payment_method === 'kpay') {
                $result = $this->kpay->getPayment($transaction->external_reference);
                if ($result['success']) {
                    $this->kpay->reconcileTransaction($transaction, (string) ($result['data']['status'] ?? 'PENDING'));
                }
            } elseif ($transaction->payment_method === 'stripe') {
                $result = $this->stripe->retrievePaymentIntent($transaction->external_reference);
                if (($result['status'] ?? null) === 'succeeded') {
                    $transaction->update(['status' => 'completed', 'completed_at' => now()]);
                    ProducerSubscription::provisionFromTransaction($transaction);
                }
            }
        }

        $transaction->refresh();

        return response()->json([
            'status' => $transaction->status,
            'redirect' => $transaction->status === 'completed' ? route('admin.dashboard') : null,
        ]);
    }

    /** Pack payable par l'utilisateur courant (titulaire de l'espace). */
    private function payablePlan(Request $request): ProducerPlan
    {
        abort_unless($request->user()->isProducerOwner(), 403, "Seul le titulaire de l'espace peut souscrire au pack producteur.");

        $plan = ProducerPlan::current();
        abort_unless($plan?->is_active, 422, "Le pack producteur n'est pas disponible pour le moment.");

        return $plan;
    }

    private function openTransaction(User $user, ProducerPlan $plan, string $method): Transaction
    {
        return Transaction::create([
            'user_id' => $user->id,
            'transaction_id' => 'PRD-' . strtoupper(Str::random(12)),
            'payment_method' => $method,
            'type' => 'producer_subscription',
            'amount' => $plan->price,
            'net_amount' => $plan->price,
            'currency' => 'XAF',
            'description' => "Pack producteur {$plan->name} ({$plan->durationLabel()})",
            'payer_email' => $user->email,
            'payer_name' => $user->name,
            'status' => 'pending',
            // Période figée au moment du paiement (voir provisionFromTransaction).
            'metadata' => [
                'producer_plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'billing_period' => $plan->billing_period,
                'period_count' => $plan->period_count,
            ],
        ]);
    }
}
