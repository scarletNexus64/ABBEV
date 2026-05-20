<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionApiController extends Controller
{
    /**
     * Liste paginée des transactions de l'utilisateur courant.
     * GET /api/subscription-payment/transactions?page=1
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(5, min(50, $perPage));

        $page = Transaction::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $displayCurrency = $this->resolveDisplayCurrency($user);

        return response()->json([
            'data' => $page->getCollection()
                ->map(fn ($t) => $this->serialize($t, $displayCurrency))
                ->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'display_currency' => $displayCurrency ? [
                'code' => $displayCurrency->code,
                'symbol' => $displayCurrency->symbol,
                'decimals' => (int) $displayCurrency->decimals,
            ] : null,
        ]);
    }

    /**
     * Dernière transaction KPay encore "pending" pour cet utilisateur
     * (créée il y a moins de 30 minutes, fenêtre raisonnable d'attente).
     * Sert au mobile pour reprendre un polling silencieux après un retour
     * sur l'app.
     *
     * GET /api/subscription-payment/pending
     */
    public function pendingPayment(Request $request): JsonResponse
    {
        $user = $request->user();

        $transaction = Transaction::where('user_id', $user->id)
            ->where('payment_method', 'kpay')
            ->where('status', 'pending')
            ->where('created_at', '>=', now()->subMinutes(30))
            ->orderByDesc('created_at')
            ->first();

        if (!$transaction) {
            return response()->json(['data' => null]);
        }

        $displayCurrency = $this->resolveDisplayCurrency($request->user());

        return response()->json([
            'data' => $this->serialize($transaction, $displayCurrency),
        ]);
    }

    /**
     * Devise d'affichage = celle du compte utilisateur (fallback XOF).
     * Sert à convertir le montant XOF stocké en BDD pour l'afficher dans
     * la devise locale de l'utilisateur (ex: 5000 XAF → 7.62 EUR).
     */
    private function resolveDisplayCurrency($user): ?Currency
    {
        $code = $user?->currency_code ?: 'XOF';

        return Currency::where('code', strtoupper($code))
            ->where('is_active', true)
            ->first()
            ?? Currency::where('code', 'XOF')->first();
    }

    /**
     * Sérialisation publique d'une transaction (pas d'infos sensibles).
     * `displayCurrency` permet d'inclure le montant converti dans la devise
     * d'affichage de l'utilisateur, en plus du montant original (XAF/XOF).
     */
    private function serialize(Transaction $t, ?Currency $displayCurrency): array
    {
        $amountXof = (float) $t->amount;
        $displayAmount = $displayCurrency
            ? $displayCurrency->convertFromXof($amountXof)
            : $amountXof;

        return [
            'id' => $t->id,
            'transaction_id' => $t->transaction_id,
            'payment_method' => $t->payment_method,
            'type' => $t->type,
            // Montant original tel que stocké (devise du paiement réel).
            'amount' => $amountXof,
            'net_amount' => $t->net_amount !== null ? (float) $t->net_amount : null,
            'currency' => $t->currency,
            // Montant converti dans la devise d'affichage du user.
            'display_amount' => $displayAmount,
            'display_currency' => $displayCurrency?->code,
            'display_currency_symbol' => $displayCurrency?->symbol,
            'display_currency_decimals' => (int) ($displayCurrency?->decimals ?? 0),
            'external_reference' => $t->external_reference,
            'description' => $t->description,
            'status' => $t->status,
            'metadata' => $t->metadata,
            'created_at' => $t->created_at?->toIso8601String(),
            'completed_at' => $t->completed_at?->toIso8601String(),
        ];
    }
}
