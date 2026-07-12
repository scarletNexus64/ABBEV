<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ReconcileKpayTransaction;
use App\Models\Currency;
use App\Models\Reservation;
use App\Models\TicketType;
use App\Models\Transaction;
use App\Services\KpayService;
use App\Services\PayPalService;
use App\Services\ReservationService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Paiement d'une réservation de ticket de séance.
 *
 * Réutilise la même mécanique PayPal / KPay (Mobile Money) que les
 * abonnements, mais à la validation du paiement on confirme la réservation
 * et on décompte le stock (via ReservationService), au lieu de provisionner
 * un abonnement.
 *
 * Flux :
 *   1. POST /initiate       → crée la résa (pending) + transaction, déclenche
 *                             PayPal (approval_url) ou KPay (push USSD).
 *   2a. PayPal : capture     → confirme la résa.
 *   2b. KPay   : status poll → confirme la résa quand KPay = COMPLETED
 *                             (le job ReconcileKpayTransaction le fait aussi
 *                             en tâche de fond).
 */
class ReservationPaymentController extends Controller
{
    public function __construct(
        private PayPalService $paypal,
        private KpayService $kpay,
        private ReservationService $reservations,
        private StripeService $stripe,
    ) {
    }

    /**
     * POST /api/reservation-payment/initiate
     */
    public function initiate(Request $request)
    {
        $validated = $request->validate([
            'ticket_type_id'  => 'required|exists:ticket_types,id',
            'quantity'        => 'required|integer|min:1|max:20',
            'payment_method'  => 'required|in:paypal,kpay,stripe',
            'phone_number'    => 'required_if:payment_method,kpay|string',
            // Opérateur du catalogue multi-pays (ex. MTN_MOMO_CMR, ORANGE_CIV…)
            // ou ancien code legacy (MTN_MONEY/ORANGE_MONEY).
            'mobile_operator' => 'required_if:payment_method,kpay|string',
            'country_code'    => 'nullable|string|size:2',
        ]);

        $user       = $request->user();
        $ticketType = TicketType::findOrFail($validated['ticket_type_id']);

        try {
            $created = $this->reservations->create(
                $user->id,
                $ticketType,
                $validated['quantity'],
                $validated['payment_method'],
            );
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        /** @var Reservation $reservation */
        $reservation = $created['reservation'];
        /** @var Transaction $transaction */
        $transaction = $created['transaction'];

        Log::info('[ReservationPayment] Initiate', [
            'reservation' => $reservation->reference,
            'amount'      => $transaction->amount,
            'method'      => $validated['payment_method'],
        ]);

        if ($validated['payment_method'] === 'paypal') {
            return $this->initiatePayPal($transaction, $reservation);
        }

        if ($validated['payment_method'] === 'stripe') {
            return $this->initiateStripe($transaction, $reservation);
        }

        return $this->initiateKpay($transaction, $reservation, $validated);
    }

    /**
     * Carte (Stripe) : crée un PaymentIntent et renvoie le client_secret pour
     * le PaymentSheet flutter_stripe. La confirmation effective passe par le
     * webhook (source de vérité) + un appel /stripe/confirm depuis l'app.
     */
    private function initiateStripe(Transaction $transaction, Reservation $reservation)
    {
        if (! $this->stripe->isConfigured()) {
            $this->reservations->cancel($reservation);
            return response()->json([
                'success' => false,
                'message' => 'Le paiement par carte n\'est pas disponible pour le moment.',
            ], 503);
        }

        $result = $this->stripe->createPaymentIntent([
            'amount'      => (float) $transaction->amount,
            'description' => $transaction->description,
            'metadata'    => [
                'transaction_id' => $transaction->transaction_id,
                'reservation_id' => $reservation->id,
                'type'           => 'reservation',
            ],
        ]);

        if (! ($result['success'] ?? false)) {
            $this->reservations->cancel($reservation);
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? "Impossible d'initier le paiement par carte.",
            ], 400);
        }

        $transaction->update([
            'external_reference' => $result['payment_intent_id'],
            'metadata' => array_merge($transaction->metadata ?? [], [
                'stripe_payment_intent_id' => $result['payment_intent_id'],
            ]),
        ]);

        return response()->json([
            'success'         => true,
            'payment_method'  => 'stripe',
            'transaction_id'  => $transaction->transaction_id,
            'client_secret'   => $result['client_secret'],
            'publishable_key' => $result['publishable_key'],
            'payment_intent_id' => $result['payment_intent_id'],
            'reservation'     => $this->presentReservation($reservation),
        ]);
    }

    private function initiatePayPal(Transaction $transaction, Reservation $reservation)
    {
        // Garde-fou : si PayPal n'est pas configuré (identifiants vides dans
        // le dashboard), renvoyer un message clair plutôt qu'un échec 401
        // opaque côté client.
        if (! $this->paypal->isConfigured()) {
            $this->reservations->cancel($reservation);
            return response()->json([
                'success' => false,
                'message' => 'Le paiement PayPal n\'est pas disponible pour le moment. Choisissez Mobile Money ou réessayez plus tard.',
            ], 503);
        }

        $result = $this->paypal->createOrder([
            'amount'     => (float) $transaction->amount,
            'user_id'    => $transaction->user_id,
            'return_url' => url('/api/subscription-payment/paypal/success'),
            'cancel_url' => url('/api/subscription-payment/paypal/cancel'),
        ]);

        if (! ($result['success'] ?? false)) {
            $this->reservations->cancel($reservation);
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? "Impossible d'initier le paiement PayPal.",
            ], 400);
        }

        $transaction->update([
            'external_reference' => $result['order_id'],
            'metadata' => array_merge($transaction->metadata ?? [], [
                'paypal_order_id' => $result['order_id'],
                'amount_usd'      => $result['amount_usd'] ?? null,
            ]),
        ]);

        return response()->json([
            'success'        => true,
            'payment_method' => 'paypal',
            'transaction_id' => $transaction->transaction_id,
            'approval_url'   => $result['approval_url'],
            'order_id'       => $result['order_id'],
            'reservation'    => $this->presentReservation($reservation),
        ]);
    }

    /**
     * @param array<string,mixed> $validated
     */
    private function initiateKpay(Transaction $transaction, Reservation $reservation, array $validated)
    {
        // Pays du paiement : fourni, sinon pays du compte, sinon Cameroun.
        // Résolu depuis config/kpay.php (catalogue multi-pays). L'opérateur
        // est validé contre ce pays. Le montant du ticket est déjà en devise
        // LOCALE fixe du cinéma → on ne convertit pas (contrairement aux
        // abonnements dont le prix est en base XOF).
        $countryCode = strtoupper($validated['country_code']
            ?? $reservation->user?->country_code
            ?? 'CM');
        $country = config('kpay.countries.' . $countryCode);
        $operator = $validated['mobile_operator'];

        if ($country) {
            $operatorEntry = collect($country['operators'])->firstWhere('code', $operator);
            if (! $operatorEntry) {
                $this->reservations->cancel($reservation);
                return response()->json([
                    'success' => false,
                    'message' => "Cet opérateur n'est pas disponible pour {$country['name']}.",
                ], 422);
            }
        }

        $result = $this->kpay->initPayment([
            'amount'        => (int) $transaction->amount,
            'provider'      => $operator,
            'country'       => $countryCode,
            'phoneNumber'   => $validated['phone_number'],
            'externalId'    => $transaction->transaction_id,
        ]);

        if (! ($result['success'] ?? false)) {
            $this->reservations->cancel($reservation);
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? "Échec de l'initialisation KPay.",
            ], 400);
        }

        $kpayData = $result['data'];
        $kpayId   = $kpayData['id'] ?? null;
        $kpayRef  = $kpayData['reference'] ?? null;

        $transaction->update([
            'external_reference' => $kpayId,
            'metadata' => array_merge($transaction->metadata ?? [], [
                'kpay_id'        => $kpayId,
                'kpay_reference' => $kpayRef,
                'kpay_operator'  => $validated['mobile_operator'],
            ]),
        ]);

        ReconcileKpayTransaction::dispatch($transaction->id);

        return response()->json([
            'success'        => true,
            'payment_method' => 'kpay',
            'transaction_id' => $transaction->transaction_id,
            'reference'      => $kpayId,
            'kpay_reference' => $kpayRef,
            'status'         => $kpayData['status'] ?? 'pending',
            'message'        => 'Veuillez valider le paiement sur votre téléphone.',
            'reservation'    => $this->presentReservation($reservation),
        ]);
    }

    /**
     * POST /api/reservation-payment/paypal/capture
     */
    public function capturePayPal(Request $request)
    {
        $validated = $request->validate(['order_id' => 'required|string']);

        $transaction = Transaction::where('external_reference', $validated['order_id'])
            ->where('payment_method', 'paypal')
            ->where('type', 'purchase')
            ->first();

        if (! $transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction introuvable.'], 404);
        }

        $result = $this->paypal->captureOrder($validated['order_id']);

        if (! ($result['success'] ?? false)) {
            $transaction->update(['status' => 'failed']);
            $this->reservations->confirmFromTransaction($transaction); // no-op (résa reste pending)
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'La capture du paiement a échoué.',
            ], 400);
        }

        $transaction->update(['status' => 'completed', 'completed_at' => now()]);

        $reservation = $this->reservations->confirmFromTransaction($transaction);

        return response()->json([
            'success'        => true,
            'message'        => 'Réservation confirmée.',
            'transaction_id' => $transaction->transaction_id,
            'reservation'    => $reservation ? $this->presentReservation($reservation) : null,
        ]);
    }

    /**
     * POST /api/reservation-payment/stripe/confirm
     *
     * Appelé par l'app après un PaymentSheet réussi. Le webhook reste la
     * source de vérité ; cet endpoint confirme immédiatement (sans attendre
     * le webhook) en revérifiant le statut du PaymentIntent côté Stripe.
     * Idempotent.
     */
    public function confirmStripe(Request $request)
    {
        $validated = $request->validate(['payment_intent_id' => 'required|string']);

        $transaction = Transaction::where('external_reference', $validated['payment_intent_id'])
            ->where('payment_method', 'stripe')
            ->where('type', 'purchase')
            ->first();

        if (! $transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction introuvable.'], 404);
        }

        $result = $this->stripe->retrievePaymentIntent($validated['payment_intent_id']);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Vérification Stripe impossible.',
            ], 400);
        }

        if (($result['status'] ?? null) !== 'succeeded') {
            return response()->json([
                'success' => true,
                'status'  => 'pending',
                'message' => 'Paiement en cours de traitement.',
            ]);
        }

        if ($transaction->status !== 'completed') {
            $transaction->update(['status' => 'completed', 'completed_at' => now()]);
        }

        $reservation = $this->reservations->confirmFromTransaction($transaction);

        return response()->json([
            'success'        => true,
            'status'         => 'completed',
            'message'        => 'Réservation confirmée.',
            'transaction_id' => $transaction->transaction_id,
            'reservation'    => $reservation ? $this->presentReservation($reservation) : null,
        ]);
    }

    /**
     * GET /api/reservation-payment/kpay/status/{reference}
     */
    public function checkKpayStatus(string $reference)
    {
        $transaction = Transaction::where('payment_method', 'kpay')
            ->where('type', 'purchase')
            ->where(function ($q) use ($reference) {
                $q->where('external_reference', $reference)
                  ->orWhere('metadata->kpay_reference', $reference);
            })
            ->first();

        if (! $transaction) {
            return response()->json(['success' => false, 'message' => 'Transaction introuvable.'], 404);
        }

        $result = $this->kpay->getPayment($transaction->external_reference);

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Erreur lors de la vérification.',
            ], 400);
        }

        // reconcileTransaction confirme automatiquement la réservation pour
        // les transactions de type purchase (voir KpayService).
        $localStatus = $this->kpay->reconcileTransaction(
            $transaction,
            $result['data']['status'] ?? 'PENDING'
        );

        if ($localStatus === 'completed') {
            $reservation = Reservation::find($transaction->metadata['reservation_id'] ?? null);
            return response()->json([
                'success'     => true,
                'status'      => 'completed',
                'message'     => 'Réservation confirmée.',
                'reservation' => $reservation ? $this->presentReservation($reservation) : null,
            ]);
        }

        if ($localStatus === 'failed') {
            return response()->json([
                'success' => false,
                'status'  => 'failed',
                'message' => 'Le paiement a échoué.',
            ]);
        }

        return response()->json([
            'success' => true,
            'status'  => 'pending',
            'message' => 'Paiement en cours de traitement.',
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function presentReservation(Reservation $r): array
    {
        $currency = $r->currency
            ? Currency::where('code', strtoupper($r->currency))->first()
            : null;

        return [
            'id'                => $r->id,
            'reference'         => $r->reference,
            'status'            => $r->status,
            'quantity'          => $r->quantity,
            'total_amount'      => (float) $r->total_amount,
            'currency'          => $r->currency,
            'currency_symbol'   => $currency?->symbol ?: ($r->currency ?? ''),
            'currency_decimals' => (int) ($currency?->decimals ?? 0),
        ];
    }
}
