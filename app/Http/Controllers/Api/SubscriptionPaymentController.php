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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionPaymentController extends Controller
{
    protected PayPalService $paypalService;
    protected FreemopayService $freemopayService;
    protected KpayService $kpayService;

    public function __construct(
        PayPalService $paypalService,
        FreemopayService $freemopayService,
        KpayService $kpayService
    ) {
        $this->paypalService = $paypalService;
        $this->freemopayService = $freemopayService;
        $this->kpayService = $kpayService;
    }

    /**
     * Initier un paiement d'abonnement
     * POST /api/subscription-payment/initiate
     */
    public function initiate(Request $request)
    {
        $validated = $request->validate([
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            'payment_method' => 'required|in:paypal,freemopay,kpay',
            'phone_number' => 'required_if:payment_method,freemopay|required_if:payment_method,kpay|string',
            'mobile_operator' => 'required_if:payment_method,kpay|in:MTN_MONEY,ORANGE_MONEY',
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

            } elseif ($validated['payment_method'] === 'kpay') {
                // Initier paiement KPay (Mobile Money — USSD)
                $result = $this->kpayService->initPayment([
                    'amount' => (int) $plan->price,
                    'paymentMethod' => $validated['mobile_operator'],
                    'phoneNumber' => $validated['phone_number'],
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
