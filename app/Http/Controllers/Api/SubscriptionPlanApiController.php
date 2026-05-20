<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPlanApiController extends Controller
{
    /**
     * Liste publique des plans d'abonnement actifs. Les prix sont stockés
     * en XOF (FCFA) et convertis dans la devise de l'utilisateur connecté
     * — ou celle fournie en query string (`?currency=USD`), ou XOF par défaut.
     */
    public function index(Request $request): JsonResponse
    {
        $currency = $this->resolveCurrency($request);

        $plans = SubscriptionPlan::where('is_active', true)
            ->orderBy('order')
            ->get()
            ->map(fn (SubscriptionPlan $plan) => $this->transform($plan, $currency));

        return response()->json([
            'data' => $plans,
            'currency' => $this->currencyMeta($currency),
        ]);
    }

    /**
     * Détail d'un plan.
     */
    public function show(Request $request, SubscriptionPlan $subscriptionPlan): JsonResponse
    {
        $currency = $this->resolveCurrency($request);

        return response()->json([
            'data' => $this->transform($subscriptionPlan, $currency),
            'currency' => $this->currencyMeta($currency),
        ]);
    }

      /**
     * Détermine la devise d'affichage. Priorités :
     *   1. Query string ?currency=XXX
     *   2. Devise de l'utilisateur authentifié (token Sanctum)
     *   3. XOF par défaut
     *
     * Note : la route n'étant pas sous middleware `auth:sanctum`,
     * `$request->user()` est `null` même si un token Bearer est présent.
     * On résout donc manuellement le token via le guard `sanctum`.
     */
    private function resolveCurrency(Request $request): Currency
    {
        $code = $request->query('currency');

        if (! $code) {
            $user = $request->user() ?? auth('sanctum')->user();
            if ($user) {
                $code = $user->currency_code;
            }
        }

        $code = strtoupper($code ?: 'XOF');

        return Currency::where('code', $code)->where('is_active', true)->first()
            ?? Currency::where('code', 'XOF')->first()
            ?? new Currency(['code' => 'XOF', 'symbol' => 'FCFA', 'decimals' => 0, 'rate_from_xof' => 1]);
    }

    private function currencyMeta(Currency $currency): array
    {
        return [
            'code' => $currency->code,
            'symbol' => $currency->symbol,
            'decimals' => (int) $currency->decimals,
            'rateFromXof' => (float) $currency->rate_from_xof,
        ];
    }

    private function transform(SubscriptionPlan $plan, Currency $currency): array
    {
        $features = $plan->features;
        if (is_string($features)) {
            $decoded = json_decode($features, true);
            $features = is_array($decoded) ? $decoded : [];
        }

        $priceXof = (float) $plan->price;
        $convertedPrice = $currency->convertFromXof($priceXof);

        return [
            'id' => (string) $plan->id,
            'name' => $plan->name,
            'description' => $plan->description ?? '',
            // Prix dans la devise demandée (déjà arrondi).
            'price' => $convertedPrice,
            'currency' => $currency->code,
            'currencySymbol' => $currency->symbol,
            // Nombre de décimales à afficher (0 pour XOF/JPY, 2 pour EUR/USD…).
            // Sans ça l'app afficherait "0" au lieu de "0.08" pour les
            // petits prix après conversion.
            'currencyDecimals' => (int) $currency->decimals,
            // Prix de référence en XOF — utile au front pour afficher
            // « ~ 5000 FCFA » à côté ou recalculer si besoin.
            'priceXof' => $priceXof,
            'durationInDays' => (int) $plan->duration_days,
            'features' => array_values($features ?? []),
            'isPopular' => (bool) $plan->is_popular,
        ];
    }
}
