<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ReconcileKpayTransaction;
use App\Models\SubscriptionPlan;
use App\Models\Transaction;
use App\Models\UserSubscription;
use App\Services\PayPalService;
use App\Services\FreemopayService;
use App\Services\KpayService;
use App\Services\AppleAppStoreService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionPaymentController extends Controller
{
    protected PayPalService $paypalService;
    protected FreemopayService $freemopayService;
    protected KpayService $kpayService;
    protected AppleAppStoreService $appleService;
    protected StripeService $stripeService;

    public function __construct(
        PayPalService $paypalService,
        FreemopayService $freemopayService,
        KpayService $kpayService,
        AppleAppStoreService $appleService,
        StripeService $stripeService
    ) {
        $this->paypalService = $paypalService;
        $this->freemopayService = $freemopayService;
        $this->kpayService = $kpayService;
        $this->appleService = $appleService;
        $this->stripeService = $stripeService;
    }

    /**
     * Initier un paiement d'abonnement
     * POST /api/subscription-payment/initiate
     */
    public function initiate(Request $request)
    {
        $validated = $request->validate([
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            'payment_method' => 'required|in:paypal,freemopay,kpay,stripe',
            'phone_number' => 'required_if:payment_method,freemopay|required_if:payment_method,kpay|string',
            // Code opérateur KPay du catalogue (ex. ORANGE_CMR, AIRTEL_COD_CDF).
            // Validé ensuite contre le pays (config/kpay.php).
            'mobile_operator' => 'required_if:payment_method,kpay|string',
            // Pays du paiement KPay (défaut : pays du compte, sinon Cameroun).
            'country_code' => 'nullable|string|size:2',
        ]);

        try {
            $plan = SubscriptionPlan::findOrFail($validated['subscription_plan_id']);
            $user = auth()->user();

            Log::info('[SubscriptionPayment] Initiate payment', [
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'plan_name' => $plan->name,
                'amount' => $plan->price,
                'payment_method' => $validated['payment_method'],
            ]);

            // Créer la transaction en pending
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'transaction_id' => 'TXN-' . strtoupper(Str::random(12)),
                'payment_method' => $validated['payment_method'],
                'type' => 'subscription',
                'amount' => $plan->price,
                'net_amount' => $plan->price,
                'currency' => 'XAF',
                'description' => "Abonnement {$plan->name}",
                'status' => 'pending',
                'metadata' => [
                    'subscription_plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'duration_days' => $plan->duration_days,
                ],
            ]);

            Log::info('[SubscriptionPayment] Transaction created', [
                'transaction_id' => $transaction->transaction_id,
                'user_id' => $user->id,
                'status' => 'pending',
            ]);

            if ($validated['payment_method'] === 'paypal') {
                // Initier paiement PayPal
                $result = $this->paypalService->createOrder([
                    'amount' => $plan->price,
                    'user_id' => $user->id,
                    'return_url' => url('/api/subscription-payment/paypal/success'),
                    'cancel_url' => url('/api/subscription-payment/paypal/cancel'),
                ]);

                if (!$result['success']) {
                    $transaction->update(['status' => 'failed']);

                    Log::warning('[SubscriptionPayment] PayPal order creation failed', [
                        'transaction_id' => $transaction->transaction_id,
                        'user_id' => $user->id,
                        'reason' => $result['message'] ?? 'unknown',
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $result['message'],
                    ], 400);
                }

                Log::info('[SubscriptionPayment] PayPal order created', [
                    'transaction_id' => $transaction->transaction_id,
                    'user_id' => $user->id,
                    'order_id' => $result['order_id'],
                ]);

                // Mettre à jour la transaction avec l'order_id PayPal
                $transaction->update([
                    'external_reference' => $result['order_id'],
                    'metadata' => array_merge($transaction->metadata, [
                        'paypal_order_id' => $result['order_id'],
                        'amount_usd' => $result['amount_usd'],
                    ]),
                ]);

                return response()->json([
                    'success' => true,
                    'transaction_id' => $transaction->transaction_id,
                    'payment_method' => 'paypal',
                    'approval_url' => $result['approval_url'],
                    'order_id' => $result['order_id'],
                ]);

            } elseif ($validated['payment_method'] === 'stripe') {
                // Carte (Stripe) — abonnement, Android uniquement côté app.
                // Crée un PaymentIntent ; la confirmation passe par le webhook
                // (source de vérité) + /stripe/confirm depuis l'app.
                if (!$this->stripeService->isConfigured()) {
                    $transaction->update(['status' => 'failed']);
                    return response()->json([
                        'success' => false,
                        'message' => 'Le paiement par carte n\'est pas disponible pour le moment.',
                    ], 503);
                }

                $result = $this->stripeService->createPaymentIntent([
                    'amount'      => (float) $plan->price,
                    'description' => "Abonnement {$plan->name}",
                    'metadata'    => [
                        'transaction_id'       => $transaction->transaction_id,
                        'subscription_plan_id' => $plan->id,
                        'type'                 => 'subscription',
                    ],
                ]);

                if (!$result['success']) {
                    $transaction->update(['status' => 'failed']);

                    Log::warning('[SubscriptionPayment] Stripe init failed', [
                        'transaction_id' => $transaction->transaction_id,
                        'user_id' => $user->id,
                        'reason' => $result['message'] ?? 'unknown',
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $result['message'] ?? 'Échec de l\'initialisation Stripe',
                    ], 400);
                }

                $transaction->update([
                    'external_reference' => $result['payment_intent_id'],
                    'metadata' => array_merge($transaction->metadata, [
                        'stripe_payment_intent_id' => $result['payment_intent_id'],
                    ]),
                ]);

                Log::info('[SubscriptionPayment] Stripe PaymentIntent created', [
                    'transaction_id' => $transaction->transaction_id,
                    'user_id' => $user->id,
                    'payment_intent_id' => $result['payment_intent_id'],
                ]);

                return response()->json([
                    'success' => true,
                    'transaction_id' => $transaction->transaction_id,
                    'payment_method' => 'stripe',
                    'client_secret' => $result['client_secret'],
                    'publishable_key' => $result['publishable_key'],
                    'payment_intent_id' => $result['payment_intent_id'],
                ]);

            } elseif ($validated['payment_method'] === 'kpay') {
                // Pays du paiement : celui fourni, sinon le pays du compte,
                // sinon Cameroun (repli legacy). Résolu depuis config/kpay.php.
                $countryCode = strtoupper($validated['country_code'] ?? $user->country_code ?? 'CM');
                $country = config('kpay.countries.' . $countryCode);

                if (! $country) {
                    $transaction->update(['status' => 'failed']);

                    return response()->json([
                        'success' => false,
                        'message' => "KPay n'est pas disponible dans votre pays ({$countryCode}).",
                    ], 422);
                }

                $operator = $validated['mobile_operator'];
                $operatorEntry = collect($country['operators'])
                    ->firstWhere('code', $operator);
                if (! $operatorEntry) {
                    $transaction->update(['status' => 'failed']);

                    return response()->json([
                        'success' => false,
                        'message' => "Cet opérateur n'est pas disponible pour {$country['name']}.",
                    ], 422);
                }

                // Montant réellement débité, converti depuis la base XOF vers la
                // devise de l'OPÉRATEUR (au taux live) — un pays peut avoir
                // plusieurs devises (ex. RDC : CDF ou USD). La transaction, elle,
                // GARDE son montant en XOF (base canonique pour l'affichage).
                $localCurrency = $operatorEntry['currency'];
                $amountLocal = \App\Models\Currency::convertFromXofTo((float) $plan->price, $localCurrency)
                    ?? (float) $plan->price;
                $amountForKpay = ($amountLocal == floor($amountLocal))
                    ? (int) $amountLocal
                    : round($amountLocal, 2);

                Log::info('[SubscriptionPayment] KPay montant converti', [
                    'transaction_id' => $transaction->transaction_id,
                    'country' => $countryCode,
                    'price_xof' => (float) $plan->price,
                    'amount_local' => $amountForKpay,
                    'currency' => $localCurrency,
                ]);

                // Initier paiement KPay (Mobile Money — USSD). Le mapping
                // opérateur→provider est fait dans initPayment selon le pays.
                $result = $this->kpayService->initPayment([
                    'amount' => $amountForKpay,
                    'provider' => $operator,
                    'country' => $countryCode,
                    // KPay exige le format international ; on préfixe l'indicatif
                    // du pays (mobile saisit un numéro local).
                    'phoneNumber' => KpayService::normalizeMsisdn($validated['phone_number'], $country['dial']),
                    'externalId' => $transaction->transaction_id,
                ]);

                if (!$result['success']) {
                    $transaction->update(['status' => 'failed']);

                    Log::warning('[SubscriptionPayment] KPay init failed', [
                        'transaction_id' => $transaction->transaction_id,
                        'user_id' => $user->id,
                        'reason' => $result['message'] ?? 'unknown',
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => $result['message'] ?? 'Échec de l\'initialisation KPay',
                    ], 400);
                }

                $kpayData = $result['data'];
                // GET /payments/:id attend l'id KPay (pas la référence humaine).
                // On stocke l'id dans external_reference et on l'expose au mobile
                // sous la clé "reference" (que le client utilise pour le polling).
                $kpayId = $kpayData['id'] ?? null;
                $kpayHumanRef = $kpayData['reference'] ?? null;

                $transaction->update([
                    'external_reference' => $kpayId,
                    'metadata' => array_merge($transaction->metadata, [
                        'kpay_id' => $kpayId,
                        'kpay_reference' => $kpayHumanRef,
                        'kpay_operator' => $validated['mobile_operator'],
                        'kpay_country' => $countryCode,
                        'kpay_currency' => $localCurrency,
                        'kpay_amount_local' => $amountForKpay,
                    ]),
                ]);

                Log::info('[SubscriptionPayment] KPay payment initiated', [
                    'transaction_id' => $transaction->transaction_id,
                    'user_id' => $user->id,
                    'kpay_id' => $kpayId,
                    'kpay_reference' => $kpayHumanRef,
                ]);

                ReconcileKpayTransaction::dispatch($transaction->id);

                return response()->json([
                    'success' => true,
                    'transaction_id' => $transaction->transaction_id,
                    'payment_method' => 'kpay',
                    'reference' => $kpayId,
                    'kpay_reference' => $kpayHumanRef,
                    'status' => $kpayData['status'] ?? 'pending',
                    'message' => 'Veuillez valider le paiement sur votre téléphone',
                ]);

            } else {
                // Initier paiement FreeMoPay
                $result = $this->freemopayService->initializePayment([
                    'amount' => $plan->price,
                    'phone_number' => $validated['phone_number'],
                    'external_reference' => $transaction->transaction_id,
                    'description' => "Abonnement {$plan->name}",
                    'callback_url' => url('/api/webhooks/freemopay'),
                ]);

                if (!$result['success']) {
                    $transaction->update(['status' => 'failed']);
                    return response()->json([
                        'success' => false,
                        'message' => $result['message'],
                    ], 400);
                }

                // Mettre à jour la transaction avec la référence FreeMoPay
                $transaction->update([
                    'external_reference' => $result['reference'],
                    'metadata' => array_merge($transaction->metadata, [
                        'freemopay_reference' => $result['reference'],
                    ]),
                ]);

                return response()->json([
                    'success' => true,
                    'transaction_id' => $transaction->transaction_id,
                    'payment_method' => 'freemopay',
                    'reference' => $result['reference'],
                    'status' => $result['status'],
                    'message' => 'Veuillez composer le code USSD affiché sur votre téléphone',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('[SubscriptionPayment] Error initiating payment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'initialisation du paiement',
            ], 500);
        }
    }

    /**
     * Capturer un paiement PayPal après approbation
     * POST /api/subscription-payment/paypal/capture
     */
    public function capturePayPal(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|string',
        ]);

        Log::info('[SubscriptionPayment] Capture PayPal payment', [
            'order_id' => $validated['order_id'],
        ]);

        try {
            // Trouver la transaction
            $transaction = Transaction::where('external_reference', $validated['order_id'])
                ->where('payment_method', 'paypal')
                ->firstOrFail();

            Log::info('[SubscriptionPayment] Transaction found for capture', [
                'transaction_id' => $transaction->transaction_id,
                'user_id' => $transaction->user_id,
                'order_id' => $validated['order_id'],
            ]);

            // Capturer le paiement
            $result = $this->paypalService->captureOrder($validated['order_id']);

            if (!$result['success']) {
                $transaction->update(['status' => 'failed']);

                Log::warning('[SubscriptionPayment] PayPal capture failed', [
                    'transaction_id' => $transaction->transaction_id,
                    'user_id' => $transaction->user_id,
                    'order_id' => $validated['order_id'],
                    'reason' => $result['message'] ?? 'unknown',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            // Mettre à jour la transaction
            $transaction->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            Log::info('[SubscriptionPayment] Payment completed', [
                'transaction_id' => $transaction->transaction_id,
                'user_id' => $transaction->user_id,
                'amount' => $transaction->amount,
            ]);

            // Créer l'abonnement
            $this->createSubscription($transaction);

            return response()->json([
                'success' => true,
                'message' => 'Paiement effectué avec succès',
                'transaction_id' => $transaction->transaction_id,
            ]);

        } catch (\Exception $e) {
            Log::error('[SubscriptionPayment] Error capturing PayPal payment', [
                'error' => $e->getMessage(),
                'order_id' => $validated['order_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la capture du paiement',
            ], 500);
        }
    }

    /**
     * Confirmer un paiement Stripe après PaymentSheet réussi.
     * POST /api/subscription-payment/stripe/confirm
     *
     * Le webhook reste la source de vérité ; cet endpoint active l'abonnement
     * immédiatement en revérifiant le statut du PaymentIntent. Idempotent.
     */
    public function confirmStripe(Request $request)
    {
        $validated = $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        try {
            $transaction = Transaction::where('external_reference', $validated['payment_intent_id'])
                ->where('payment_method', 'stripe')
                ->firstOrFail();

            $result = $this->stripeService->retrievePaymentIntent($validated['payment_intent_id']);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Vérification Stripe impossible',
                ], 400);
            }

            if (($result['status'] ?? null) !== 'succeeded') {
                return response()->json([
                    'success' => true,
                    'status' => 'pending',
                    'message' => 'Paiement en cours de traitement',
                ]);
            }

            if ($transaction->status !== 'completed') {
                $transaction->update(['status' => 'completed', 'completed_at' => now()]);
                $this->createSubscription($transaction);
            }

            return response()->json([
                'success' => true,
                'status' => 'completed',
                'message' => 'Paiement effectué avec succès',
                'transaction_id' => $transaction->transaction_id,
            ]);
        } catch (\Exception $e) {
            Log::error('[SubscriptionPayment] Error confirming Stripe payment', [
                'error' => $e->getMessage(),
                'payment_intent_id' => $validated['payment_intent_id'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la confirmation du paiement',
            ], 500);
        }
    }

    /**
     * Vérifier le statut d'un paiement FreeMoPay
     * GET /api/subscription-payment/freemopay/status/{reference}
     */
    public function checkFreeMoPayStatus($reference)
    {
        try {
            $result = $this->freemopayService->checkStatus($reference);

            // Trouver la transaction
            $transaction = Transaction::where('external_reference', $reference)
                ->where('payment_method', 'freemopay')
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction introuvable',
                ], 404);
            }

            $status = strtoupper($result['status']);

            // Si le paiement est réussi
            if (in_array($status, ['SUCCESS', 'SUCCESSFUL', 'COMPLETED'])) {
                if ($transaction->status !== 'completed') {
                    $transaction->update([
                        'status' => 'completed',
                        'completed_at' => now(),
                    ]);

                    // Créer l'abonnement
                    $this->createSubscription($transaction);
                }

                return response()->json([
                    'success' => true,
                    'status' => 'completed',
                    'message' => 'Paiement effectué avec succès',
                    'transaction_id' => $transaction->transaction_id,
                ]);
            }

            // Si le paiement a échoué
            if (in_array($status, ['FAILED', 'FAILURE', 'ERROR', 'REJECTED', 'CANCELLED'])) {
                $transaction->update(['status' => 'failed']);

                return response()->json([
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'Le paiement a échoué',
                    'reason' => $result['reason'] ?? null,
                ]);
            }

            // Sinon, toujours en attente
            return response()->json([
                'success' => true,
                'status' => 'pending',
                'message' => 'Paiement en cours de traitement',
            ]);

        } catch (\Exception $e) {
            Log::error('[SubscriptionPayment] Error checking FreeMoPay status', [
                'error' => $e->getMessage(),
                'reference' => $reference,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du statut',
            ], 500);
        }
    }

    /**
     * Créer l'abonnement après paiement réussi
     */
    protected function createSubscription(Transaction $transaction)
    {
        // Provisionnement centralisé : gère souscription ET
        // renouvellement (cumul des jours), idempotent.
        UserSubscription::provisionFromTransaction($transaction);
    }

    /**
     * Vérifier un achat In-App Apple et provisionner l'abonnement.
     * POST /api/subscription-payment/apple/verify
     *
     * L'app iOS envoie le `transaction_id` StoreKit après un achat réussi.
     * On interroge l'App Store Server API (source de vérité), on mappe le
     * product au plan, puis on réutilise le pipeline d'abonnement existant.
     */
    public function verifyApple(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => 'required|string',
        ]);

        $user = auth()->user();

        try {
            $result = $this->appleService->getTransaction($validated['transaction_id']);

            if (!$result['success']) {
                Log::warning('[SubscriptionPayment] Apple verify failed', [
                    'user_id' => $user->id,
                    'reason' => $result['message'] ?? 'unknown',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Vérification Apple échouée',
                ], 400);
            }

            $payload = $result['payload'];

            if (!$this->appleService->isTransactionValid($payload)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction Apple invalide ou révoquée',
                ], 400);
            }

            $transaction = $this->provisionAppleTransaction($payload, $user->id);

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan introuvable pour ce produit Apple',
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Abonnement activé',
                'transaction_id' => $transaction->transaction_id,
            ]);
        } catch (\Exception $e) {
            Log::error('[SubscriptionPayment] Apple verify error', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification Apple',
            ], 500);
        }
    }

    /**
     * App Store Server Notifications v2 (renouvellements, expirations, refunds).
     * POST /api/webhooks/apple  (public, signé par Apple)
     *
     * Apple notifie sans que l'app soit ouverte : indispensable pour activer
     * les renouvellements automatiques d'un abonnement auto-renouvelable.
     */
    public function appleWebhook(Request $request)
    {
        $signedPayload = $request->input('signedPayload');

        if (!$signedPayload) {
            return response()->json(['message' => 'No signedPayload'], 400);
        }

        $notification = $this->appleService->decodeNotification($signedPayload);

        if (!$notification) {
            return response()->json(['message' => 'Invalid payload'], 400);
        }

        $type = $notification['notificationType'] ?? null;
        $signedTx = $notification['data']['signedTransactionInfo'] ?? null;
        $payload = $signedTx ? $this->appleService->decodeNotification($signedTx) : null;

        Log::info('[SubscriptionPayment] Apple notification', [
            'type' => $type,
            'subtype' => $notification['subtype'] ?? null,
            'original_transaction_id' => $payload['originalTransactionId'] ?? null,
        ]);

        if (!$payload) {
            // Acquittement systématique pour éviter les renvois Apple.
            return response()->json(['message' => 'ok']);
        }

        // Renouvellement / réabonnement : on provisionne (cumul des jours,
        // idempotent par transactionId). L'utilisateur est retrouvé via
        // l'originalTransactionId d'une transaction Apple antérieure.
        if (in_array($type, ['DID_RENEW', 'SUBSCRIBED', 'DID_CHANGE_RENEWAL_STATUS'], true)) {
            $userId = $this->resolveAppleUserId($payload);
            if ($userId) {
                $this->provisionAppleTransaction($payload, $userId);
            }
        }

        // Expiration / remboursement : on laisse le passage à expiration
        // naturel (expires_at) gérer l'accès ; un refund révoque la transaction.
        if (in_array($type, ['REFUND', 'EXPIRED', 'GRACE_PERIOD_EXPIRED'], true)) {
            $this->revokeAppleTransaction($payload);
        }

        return response()->json(['message' => 'ok']);
    }

    /**
     * Crée/complète une Transaction Apple et provisionne l'abonnement.
     * Idempotent : un même `transactionId` Apple ne provisionne qu'une fois.
     */
    protected function provisionAppleTransaction(array $payload, int $userId): ?Transaction
    {
        $appleTxId = $payload['transactionId'] ?? null;
        $productId = $payload['productId'] ?? null;

        if (!$appleTxId || !$productId) {
            return null;
        }

        $plan = SubscriptionPlan::where('apple_product_id', $productId)->first();

        if (!$plan) {
            Log::warning('[SubscriptionPayment] Apple product not mapped', [
                'product_id' => $productId,
            ]);

            return null;
        }

        // Idempotence sur l'ID de transaction Apple.
        $transaction = Transaction::where('external_reference', $appleTxId)
            ->where('payment_method', 'apple_iap')
            ->first();

        if (!$transaction) {
            $transaction = Transaction::create([
                'user_id' => $userId,
                'transaction_id' => 'TXN-' . strtoupper(Str::random(12)),
                'payment_method' => 'apple_iap',
                'type' => 'subscription',
                'amount' => $plan->price,
                'net_amount' => $plan->price,
                'currency' => 'XAF',
                'external_reference' => $appleTxId,
                'description' => "Abonnement {$plan->name} (Apple)",
                'status' => 'completed',
                'completed_at' => now(),
                'metadata' => [
                    'subscription_plan_id' => $plan->id,
                    'plan_name' => $plan->name,
                    'duration_days' => $plan->duration_days,
                    'apple_product_id' => $productId,
                    'apple_original_transaction_id' => $payload['originalTransactionId'] ?? null,
                ],
            ]);

            $this->createSubscription($transaction);
        }

        return $transaction;
    }

    /**
     * Retrouve l'utilisateur lié à un abonnement Apple via une transaction
     * antérieure portant le même originalTransactionId.
     */
    protected function resolveAppleUserId(array $payload): ?int
    {
        $originalId = $payload['originalTransactionId'] ?? null;

        if (!$originalId) {
            return null;
        }

        $prior = Transaction::where('payment_method', 'apple_iap')
            ->where('metadata->apple_original_transaction_id', $originalId)
            ->first();

        return $prior?->user_id;
    }

    /**
     * Marque la transaction Apple comme remboursée (révoquée).
     */
    protected function revokeAppleTransaction(array $payload): void
    {
        $appleTxId = $payload['transactionId'] ?? null;

        if (!$appleTxId) {
            return;
        }

        Transaction::where('external_reference', $appleTxId)
            ->where('payment_method', 'apple_iap')
            ->update(['status' => 'failed']);
    }

    /**
     * Webhook FreeMoPay (optionnel)
     * POST /api/webhooks/freemopay
     */
    public function freemopayWebhook(Request $request)
    {
        $reference = $request->input('reference');

        if (!$reference) {
            return response()->json(['message' => 'No reference provided'], 400);
        }

        // Vérifier le statut via l'API
        $this->checkFreeMoPayStatus($reference);

        return response()->json(['message' => 'Webhook processed']);
    }

    /**
     * Pays & opérateurs KPay supportés (pour le sélecteur mobile).
     * GET /api/subscription-payment/kpay/countries
     */
    public function kpayCountries()
    {
        $countries = [];

        foreach ((array) config('kpay.countries', []) as $iso => $c) {
            $operators = [];
            foreach (($c['operators'] ?? []) as $op) {
                $operators[] = [
                    'code' => $op['code'],
                    'label' => $op['label'] ?? $op['code'],
                    'currency' => $op['currency'] ?? null,
                ];
            }
            $countries[] = [
                'code' => $iso,
                'name' => $c['name'],
                'flag' => $c['flag'] ?? null,
                'dial_code' => $c['dial'],
                'operators' => $operators,
            ];
        }

        return response()->json(['success' => true, 'data' => $countries]);
    }

    /**
     * Vérifier le statut d'un paiement KPay
     * GET /api/subscription-payment/kpay/status/{reference}
     */
    public function checkKpayStatus($reference)
    {
        try {
            // Le client envoie normalement l'id KPay (stocké dans
            // external_reference). En filet de sécurité, on tente aussi
            // un fallback sur metadata.kpay_reference (ancienne réf humaine).
            $transaction = Transaction::where('payment_method', 'kpay')
                ->where(function ($q) use ($reference) {
                    $q->where('external_reference', $reference)
                      ->orWhere('metadata->kpay_reference', $reference);
                })
                ->first();

            if (!$transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction introuvable',
                ], 404);
            }

            // Toujours interroger KPay avec l'id stocké, jamais avec la
            // chaîne brute reçue (qui peut être une vieille référence humaine).
            $result = $this->kpayService->getPayment($transaction->external_reference);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'] ?? 'Erreur lors de la vérification',
                ], 400);
            }

            $kpayStatus = $result['data']['status'] ?? 'PENDING';

            $localStatus = $this->kpayService->reconcileTransaction($transaction, $kpayStatus);

            if ($localStatus === 'completed') {
                return response()->json([
                    'success' => true,
                    'status' => 'completed',
                    'message' => 'Paiement effectué avec succès',
                    'transaction_id' => $transaction->transaction_id,
                ]);
            }

            if ($localStatus === 'failed') {
                return response()->json([
                    'success' => false,
                    'status' => 'failed',
                    'message' => 'Le paiement a échoué',
                ]);
            }

            return response()->json([
                'success' => true,
                'status' => 'pending',
                'message' => 'Paiement en cours de traitement',
            ]);

        } catch (\Exception $e) {
            Log::error('[SubscriptionPayment] Error checking KPay status', [
                'error' => $e->getMessage(),
                'reference' => $reference,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification du statut',
            ], 500);
        }
    }

}
