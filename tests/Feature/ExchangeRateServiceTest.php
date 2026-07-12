<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Services\ExchangeRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Mise à jour des taux de change (currencies.rate_from_xof) depuis
 * ExchangeRate-API. On mocke l'API HTTP (aucun appel réseau réel).
 */
class ExchangeRateServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedCurrencies(): void
    {
        foreach ([
            ['XOF', 'Franc CFA', 0, 1.0],
            ['USD', 'Dollar', 2, 0.0016],
            ['CDF', 'Franc congolais', 2, 3.0],
        ] as [$code, $name, $dec, $rate]) {
            Currency::create([
                'code' => $code,
                'name' => $name,
                'symbol' => $code,
                'rate_from_xof' => $rate,
                'decimals' => $dec,
                'is_active' => true,
            ]);
        }
    }

    private function service(): ExchangeRateService
    {
        config(['services.exchangerate.key' => 'test-key', 'services.exchangerate.base' => 'XOF']);

        return new ExchangeRateService();
    }

    public function test_met_a_jour_les_taux_depuis_l_api(): void
    {
        $this->seedCurrencies();
        Http::fake([
            'v6.exchangerate-api.com/*' => Http::response([
                'result' => 'success',
                'base_code' => 'XOF',
                'conversion_rates' => ['XOF' => 1, 'USD' => 0.00174, 'CDF' => 4.03, 'ZZZ' => 9.9],
            ]),
        ]);

        $count = $this->service()->updateCurrencies();

        $this->assertSame(3, $count); // XOF, USD, CDF (ZZZ inconnu → ignoré)
        $this->assertEqualsWithDelta(0.00174, (float) Currency::where('code', 'USD')->first()->rate_from_xof, 1e-8);
        $this->assertEqualsWithDelta(4.03, (float) Currency::where('code', 'CDF')->first()->rate_from_xof, 1e-8);
    }

    public function test_leve_une_exception_sur_erreur_api(): void
    {
        $this->seedCurrencies();
        Http::fake([
            'v6.exchangerate-api.com/*' => Http::response([
                'result' => 'error',
                'error-type' => 'invalid-key',
            ]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service()->fetchLatest();
    }

    public function test_helpers_de_conversion_currency(): void
    {
        $this->seedCurrencies();
        // 1 XOF = 3 CDF → 1000 XOF = 3000 CDF
        $this->assertSame(3000.0, Currency::convertFromXofTo(1000, 'CDF'));
        $this->assertSame(3.0, Currency::rateFromXof('CDF'));
        $this->assertNull(Currency::convertFromXofTo(1000, 'ZZZ'));
        $this->assertSame(1.23, Currency::rateFromXof('ZZZ', 1.23));
    }
}
