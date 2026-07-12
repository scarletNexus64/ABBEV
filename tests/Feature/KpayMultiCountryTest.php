<?php

namespace Tests\Feature;

use App\Services\KpayService;
use Tests\TestCase;

/**
 * KPay multi-pays (#devises) : résolution du code provider par pays, préfixe
 * téléphonique local, et intégrité du mapping config/kpay.php.
 */
class KpayMultiCountryTest extends TestCase
{
    private KpayService $kpay;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kpay = new KpayService();
    }

    public function test_code_provider_valide_est_renvoye_tel_quel(): void
    {
        // Le mobile envoie directement le code du catalogue.
        $this->assertSame('MTN_MOMO_CMR', $this->kpay->providerFor('MTN_MOMO_CMR', 'CM'));
        $this->assertSame('ORANGE_CMR', $this->kpay->providerFor('ORANGE_CMR', 'CM'));
        $this->assertSame('MTN_MOMO_BEN', $this->kpay->providerFor('MTN_MOMO_BEN', 'BJ'));
        $this->assertSame('AIRTEL_COD_CDF', $this->kpay->providerFor('AIRTEL_COD_CDF', 'CD'));
        $this->assertSame('VODACOM_COD_USD', $this->kpay->providerFor('VODACOM_COD_USD', 'CD'));
        $this->assertSame('MPESA_KEN', $this->kpay->providerFor('MPESA_KEN', 'KE'));
    }

    public function test_repli_legacy_cameroun_sans_pays(): void
    {
        $this->assertSame('MTN_MOMO_CMR', $this->kpay->providerFor('MTN_MONEY'));
        $this->assertSame('ORANGE_CMR', $this->kpay->providerFor('ORANGE_MONEY'));
    }

    public function test_operateurs_et_devise_par_operateur(): void
    {
        // RDC : 6 opérateurs (CDF x3 + USD x3).
        $this->assertCount(6, $this->kpay->operatorsFor('CD'));
        $this->assertSame('CDF', $this->kpay->findOperator('CD', 'AIRTEL_COD_CDF')['currency']);
        $this->assertSame('USD', $this->kpay->findOperator('CD', 'ORANGE_COD_USD')['currency']);
        $this->assertNull($this->kpay->findOperator('CD', 'INCONNU'));
        // Kenya présent (M-Pesa / KES).
        $this->assertSame('KES', $this->kpay->findOperator('KE', 'MPESA_KEN')['currency']);
        $this->assertSame([], $this->kpay->operatorsFor('XX')); // pays inconnu
    }

    public function test_prefixe_telephone_par_pays(): void
    {
        // RDC (+243)
        $this->assertSame('243812345678', KpayService::normalizeMsisdn('0812345678', '243'));
        // Sénégal (+221), numéro déjà international
        $this->assertSame('221771234567', KpayService::normalizeMsisdn('221771234567', '221'));
        // Cameroun par défaut
        $this->assertSame('237691234567', KpayService::normalizeMsisdn('691234567'));
    }

    public function test_integrite_du_mapping_config(): void
    {
        $countries = config('kpay.countries');
        $this->assertNotEmpty($countries);

        foreach ($countries as $iso => $c) {
            $this->assertSame(2, strlen($iso), "ISO2 attendu pour {$iso}");
            $this->assertNotEmpty($c['dial'], "Indicatif manquant pour {$iso}");
            $this->assertNotEmpty($c['operators'], "Opérateurs manquants pour {$iso}");
            foreach ($c['operators'] as $op) {
                $this->assertNotEmpty($op['code'], "Code provider vide pour {$iso}");
                $this->assertNotEmpty($op['label'], "Libellé vide pour {$iso}/{$op['code']}");
                $this->assertNotEmpty($op['currency'], "Devise vide pour {$iso}/{$op['code']}");
            }
        }
    }
}
