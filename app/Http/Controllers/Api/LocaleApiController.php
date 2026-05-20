<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\Currency;
use Illuminate\Http\JsonResponse;

class LocaleApiController extends Controller
{
    /**
     * Liste des pays actifs (consommée par l'écran d'inscription).
     */
    public function countries(): JsonResponse
    {
        $countries = Country::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['code', 'name', 'flag_emoji', 'phone_code', 'currency_code']);

        return response()->json([
            'data' => $countries->map(fn (Country $c) => [
                'code' => $c->code,
                'name' => $c->name,
                'flag' => $c->flag_emoji,
                'phoneCode' => $c->phone_code,
                'currencyCode' => $c->currency_code,
            ]),
        ]);
    }

    /**
     * Liste des devises actives. Filtrable par `country_code` : utile pour
     * vérifier la cohérence du couple pays/devise choisi à l'inscription.
     */
    public function currencies(): JsonResponse
    {
        $countryCode = request('country_code');

        $query = Currency::query()->where('is_active', true);

        if ($countryCode) {
            $country = Country::where('code', strtoupper($countryCode))->first();
            if ($country) {
                $query->where('code', $country->currency_code);
            }
        }

        $currencies = $query->orderBy('code')->get();

        return response()->json([
            'data' => $currencies->map(fn (Currency $c) => [
                'code' => $c->code,
                'name' => $c->name,
                'symbol' => $c->symbol,
                'decimals' => (int) $c->decimals,
                'rateFromXof' => (float) $c->rate_from_xof,
            ]),
        ]);
    }
}
