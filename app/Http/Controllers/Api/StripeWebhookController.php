<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProducerSubscription;
use App\Models\Transaction;
use App\Models\UserSubscription;
use App\Services\ReservationService;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook Stripe — source de vérité des paiements par carte.
 *
 * Stripe notifie le serveur indépendamment de l'app (même si elle se ferme
 * pendant le 3D Secure). On écoute `payment_intent.succeeded` pour confirmer
 * la transaction, et `payment_intent.payment_failed` pour la marquer échouée.
 *
 * Le rapprochement est idempotent : on retrouve la Transaction via son
 * `external_reference` (= id du PaymentIntent) puis on provisionne
 * l'abonnement ou on confirme la réservation, selon le `type`.
 *
 * POST /api/webhooks/stripe  (public, signé par Stripe)
 */
class StripeWebhookController extends Controller
{
    public function __construct(
        private StripeService $stripe,
        private ReservationService $reservations,
    ) {
    }

    public function handle(Request $request)
    {
        $payload   = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        if (! $this->stripe->verifyWebhookSignature($payload, $sigHeader)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $event = json_decode($payload, true);
        $type  = $event['type'] ?? null;
        $intent = $event['data']['object'] ?? [];
        $intentId = $intent['id'] ?? null;

        Log::info('[StripeWebhook] Event reçu', [
            'type'      => $type,
            'intent_id' => $intentId,
        ]);

        if (! $intentId) {
            // Acquittement systématique pour éviter les renvois Stripe.
            return response()->json(['received' => true]);
        }

        $transaction = Transaction::where('payment_method', 'stripe')
            ->where('external_reference', $intentId)
            ->first();

        if (! $transaction) {
            Log::warning('[StripeWebhook] Transaction introuvable pour le PaymentIntent', [
                'intent_id' => $intentId,
            ]);

            return response()->json(['received' => true]);
        }

        if ($type === 'payment_intent.succeeded') {
            $this->fulfill($transaction);
        } elseif ($type === 'payment_intent.payment_failed') {
            if ($transaction->status !== 'completed') {
                $transaction->update(['status' => 'failed']);
            }
        }

        return response()->json(['received' => true]);
    }

    /**
     * Marque la transaction payée et provisionne le service correspondant.
     * Idempotent : ne refait rien si déjà complétée.
     */
    private function fulfill(Transaction $transaction): void
    {
        if ($transaction->status === 'completed') {
            return;
        }

        $transaction->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);

        if ($transaction->type === 'subscription') {
            UserSubscription::provisionFromTransaction($transaction);
        } elseif ($transaction->type === 'purchase') {
            $this->reservations->confirmFromTransaction($transaction);
        } elseif ($transaction->type === 'producer_subscription') {
            ProducerSubscription::provisionFromTransaction($transaction);
        }

        Log::info('[StripeWebhook] Transaction confirmée', [
            'transaction_id' => $transaction->transaction_id,
            'type'           => $transaction->type,
        ]);
    }
}
