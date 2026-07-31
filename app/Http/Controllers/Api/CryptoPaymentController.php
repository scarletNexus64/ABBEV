<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Models\SubscriptionPlan;
use App\Models\TicketType;
use App\Models\Transaction;
use App\Models\UserSubscription;
use App\Services\NowPaymentsService;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Paiement par crypto-monnaie (BTC, ETH, USDT…) via NOWPayments.
 *
 * Couvre les deux cas d'usage existants :
 *   - Abonnement       (type = subscription, metadata.subscription_plan_id)
 *   - Réservation ticket (type = purchase, metadata.reservation_id)
 *
 * Flux :
 *   1. POST /initiate            → crée la transaction (pending) + invoice
 *                                  NOWPayments, renvoie l'URL de paiement hébergée.
 *   2. NOWPayments IPN           → POST /api/webhooks/nowpayments confirme la
 *                                  transaction et déclenche le provisionnement
 *                                  (abonnement ou réservation) selon le type.
 *   3. GET /status/{transaction} → polling côté app pour rafraîchir l'état
 *                                  (force la confirmation si l'IPN tarde).
 */
class CryptoPaymentController extends Controller
{
    public function __construct(
        private NowPaymentsService $crypto,
        private ReservationService $reservations,
    ) {
    }

    /**
     * GET /api/crypto-payment/config
     *
     * Config publique pour l'app (état d'activation, mode, montant min,
     * cryptos supportées).
     */
    public function config()
    {
        return response()->json([
            'success' => true,
            'data'    => [
                'enabled'            => $this->crypto->isConfigured(),
                'provider'           => 'nowpayments',
                'mode'               => $this->crypto->getMode(),
                'minimum_amount_xaf' => $this->crypto->getMinAmountXaf(),
                'exchange_rate'      => $this->crypto->getExchangeRate(),
                'supported_currencies' => [
                    ['code' => 'BTC',  'name' => 'Bitcoin'],
                    ['code' => 'ETH',  'name' => 'Ethereum'],
                    ['code' => 'USDT', 'name' => 'Tether (USDT)'],
                    ['code' => 'USDC', 'name' => 'USD Coin'],
                    ['code' => 'LTC',  'name' => 'Litecoin'],
                    ['code' => 'DOGE', 'name' => 'Dogecoin'],
                ],
            ],
        ]);
    }

    /**
     * POST /api/crypto-payment/initiate
     *
     * Body : { purpose: subscription|reservation, ...selon le cas }
     *   - subscription : subscription_plan_id
     *   - reservation  : ticket_type_id, quantity
     */
    public function initiate(Request $request)
    {
        $validated = $request->validate([
            'purpose'              => 'required|in:subscription,reservation',
            'subscription_plan_id' => 'required_if:purpose,subscription|exists:subscription_plans,id',
            'ticket_type_id'       => 'required_if:purpose,reservation|exists:ticket_types,id',
            'quantity'             => 'required_if:purpose,reservation|integer|min:1|max:20',
        ]);

        if (! $this->crypto->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => __('messages.payment.crypto_unavailable'),
            ], 503);
        }

        $user = $request->user();

        if ($validated['purpose'] === 'subscription') {
            [$transaction, $context] = $this->buildSubscriptionTransaction($user->id, $validated);
        } else {
            try {
                [$transaction, $context] = $this->buildReservationTransaction($user->id, $validated);
            } catch (\RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
            }
        }

        Log::info('[CryptoPayment] Initiate', [
            'transaction_id' => $transaction->transaction_id,
            'purpose'        => $validated['purpose'],
            'amount'         => $transaction->amount,
        ]);

        $result = $this->crypto->createInvoice($transaction, [
            'return_url' => rtrim(config('app.url'), '/') . '/',
            'cancel_url' => rtrim(config('app.url'), '/') . '/',
        ]);

        if (! ($result['success'] ?? false)) {
            $this->failTransaction($transaction, $context);

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? __('messages.payment.crypto_init_failed'),
            ], 400);
        }

        $transaction->update([
            'external_reference' => $result['invoice_id'],
            'metadata' => array_merge($transaction->metadata ?? [], [
                'nowpayments_invoice_id' => $result['invoice_id'],
                'amount_usd'             => $result['amount_usd'] ?? null,
            ]),
        ]);

        return response()->json(array_merge([
            'success'        => true,
            'payment_method' => 'crypto',
            'transaction_id' => $transaction->transaction_id,
            'payment_url'    => $result['payment_url'],
            'invoice_id'     => $result['invoice_id'],
            'amount'         => (float) $transaction->amount,
            'amount_usd'     => $result['amount_usd'] ?? null,
        ], $context['response_extra'] ?? []));
    }

    /**
     * GET /api/crypto-payment/status/{transactionId}
     *
     * Polling côté app : renvoie le statut de NOTRE transaction. L'IPN
     * NOWPayments (webhook signé) est la SEULE source de vérité — c'est lui qui
     * fait passer la transaction à `completed`/`failed` et qui déclenche le
     * provisionnement. On ne ré-interroge PAS l'API NOWPayments ici :
     *   - l'endpoint de liste des payments exige un login JWT (pas l'API key),
     *   - et l'invoice peut exister sans payment tant que l'utilisateur n'a pas
     *     fini de payer.
     * Ce polling se contente donc de refléter l'état mis à jour par l'IPN.
     */
    public function status(Request $request, string $transactionId)
    {
        $transaction = Transaction::where('transaction_id', $transactionId)
            ->where('user_id', $request->user()->id)
            ->where('payment_method', 'crypto')
            ->first();

        if (! $transaction) {
            return response()->json(['success' => false, 'message' => __('messages.payment.transaction_not_found')], 404);
        }

        return match ($transaction->status) {
            'completed' => response()->json(['success' => true, 'status' => 'completed']),
            'failed', 'cancelled' => response()->json([
                'success' => false,
                'status'  => $transaction->status,
                'message' => __('messages.payment.failed'),
            ]),
            default => response()->json([
                'success' => true,
                'status'  => 'pending',
                'message' => __('messages.payment.crypto_processing'),
            ]),
        };
    }

    /**
     * POST /api/webhooks/nowpayments  (public, signé HMAC-SHA512)
     *
     * IPN NOWPayments : header `x-nowpayments-sig` = HMAC-SHA512 du body
     * trié alphabétiquement.
     */
    public function webhook(Request $request)
    {
        $rawBody   = $request->getContent();
        $signature = $request->header('x-nowpayments-sig');

        // DEBUG TEMPORAIRE : capture le payload réel pour diagnostiquer la
        // signature. À retirer une fois la vérification validée.
        Log::info('[CryptoPayment] IPN received', [
            'signature'    => $signature,
            'raw_body'     => $rawBody,
            'expected_sig' => $this->crypto->debugExpectedSignature($rawBody),
        ]);

        // Vérifie la signature si un secret IPN est configuré.
        if ($this->crypto->hasIpnSecret() && ! $this->crypto->verifyIpnSignature($rawBody, $signature)) {
            Log::warning('[CryptoPayment] IPN signature invalid');

            return response()->json(['error' => 'invalid signature'], 401);
        }

        $payload   = $request->all();
        $orderId   = $payload['order_id'] ?? null;       // = transaction_id ABBEV
        $invoiceId = $payload['invoice_id'] ?? null;
        $rawStatus = strtolower($payload['payment_status'] ?? 'waiting');
        $mapped    = NowPaymentsService::STATUS_MAP[$rawStatus] ?? 'pending';

        $transaction = Transaction::where('transaction_id', $orderId)
            ->when($invoiceId, fn ($q) => $q->orWhere('external_reference', (string) $invoiceId))
            ->first();

        if (! $transaction) {
            Log::warning('[CryptoPayment] IPN: transaction not found', compact('orderId', 'invoiceId'));

            return response()->json(['received' => true], 200);
        }

        // Idempotence.
        if ($transaction->status === 'completed') {
            return response()->json(['received' => true, 'already_processed' => true], 200);
        }

        if ($mapped === 'completed') {
            $this->completeTransaction($transaction, [
                'crypto_currency' => $payload['pay_currency'] ?? null,
                'crypto_amount'   => $payload['actually_paid'] ?? $payload['pay_amount'] ?? null,
            ]);
        } elseif (in_array($mapped, ['failed', 'cancelled'], true)) {
            $transaction->update(['status' => $mapped]);
        } else {
            $transaction->update(['status' => 'pending']);
        }

        return response()->json(['received' => true], 200);
    }

    // ------------------------------------------------------------------
    // Helpers internes
    // ------------------------------------------------------------------

    /**
     * @return array{0: Transaction, 1: array<string,mixed>}
     */
    private function buildSubscriptionTransaction(int $userId, array $validated): array
    {
        $plan = SubscriptionPlan::findOrFail($validated['subscription_plan_id']);

        $transaction = Transaction::create([
            'user_id'        => $userId,
            'transaction_id' => 'TXN-' . strtoupper(Str::random(12)),
            'payment_method' => 'crypto',
            'type'           => 'subscription',
            'amount'         => $plan->price,
            'fees'           => 0,
            'net_amount'     => $plan->price,
            'currency'       => 'XAF',
            'description'    => "Abonnement {$plan->name}",
            'status'         => 'pending',
            'metadata'       => [
                'subscription_plan_id' => $plan->id,
                'plan_name'            => $plan->name,
                'duration_days'        => $plan->duration_days,
                'purpose'              => 'subscription',
            ],
        ]);

        return [$transaction, ['purpose' => 'subscription']];
    }

    /**
     * @return array{0: Transaction, 1: array<string,mixed>}
     */
    private function buildReservationTransaction(int $userId, array $validated): array
    {
        $ticketType = TicketType::findOrFail($validated['ticket_type_id']);

        $created = $this->reservations->create(
            $userId,
            $ticketType,
            $validated['quantity'],
            'crypto',
        );

        /** @var Reservation $reservation */
        $reservation = $created['reservation'];
        /** @var Transaction $transaction */
        $transaction = $created['transaction'];

        // Marque le but pour le webhook (en plus de reservation_id déjà présent).
        $transaction->update([
            'metadata' => array_merge($transaction->metadata ?? [], ['purpose' => 'reservation']),
        ]);

        return [$transaction->refresh(), [
            'purpose'        => 'reservation',
            'reservation'    => $reservation,
            'response_extra' => [
                'reservation' => [
                    'id'           => $reservation->id,
                    'reference'    => $reservation->reference,
                    'status'       => $reservation->status,
                    'quantity'     => $reservation->quantity,
                    'total_amount' => (float) $reservation->total_amount,
                    'currency'     => $reservation->currency,
                ],
            ],
        ]];
    }

    /**
     * Confirme une transaction payée et déclenche le provisionnement adéquat.
     * Idempotent (chaque provisionneur l'est).
     *
     * @param array{crypto_currency?:?string, crypto_amount?:mixed} $cryptoInfo
     */
    private function completeTransaction(Transaction $transaction, array $cryptoInfo = []): void
    {
        DB::transaction(function () use ($transaction, $cryptoInfo) {
            $transaction->update([
                'status'       => 'completed',
                'completed_at' => now(),
                'metadata'     => array_merge($transaction->metadata ?? [], array_filter([
                    'crypto_currency' => $cryptoInfo['crypto_currency'] ?? null,
                    'crypto_amount'   => $cryptoInfo['crypto_amount'] ?? null,
                ], fn ($v) => $v !== null)),
            ]);

            $purpose = $transaction->metadata['purpose']
                ?? ($transaction->type === 'subscription' ? 'subscription' : 'reservation');

            if ($purpose === 'subscription') {
                UserSubscription::provisionFromTransaction($transaction);
            } else {
                $this->reservations->confirmFromTransaction($transaction);
            }
        });

        Log::info('[CryptoPayment] Transaction completed', [
            'transaction_id' => $transaction->transaction_id,
            'purpose'        => $transaction->metadata['purpose'] ?? $transaction->type,
        ]);
    }

    /**
     * Annule proprement une transaction dont l'initiation a échoué.
     *
     * @param array<string,mixed> $context
     */
    private function failTransaction(Transaction $transaction, array $context): void
    {
        if (($context['purpose'] ?? null) === 'reservation' && isset($context['reservation'])) {
            $this->reservations->cancel($context['reservation']);
        } else {
            $transaction->update(['status' => 'failed']);
        }
    }
}
