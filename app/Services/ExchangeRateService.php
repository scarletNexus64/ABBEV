<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Récupère les taux de change en direct via ExchangeRate-API (v6) et met à jour
 * la colonne `currencies.rate_from_xof`.
 *
 * La devise de BASE de la plateforme est XOF : l'endpoint `latest/XOF` renvoie
 * `conversion_rates[CODE]` = nombre d'unités de CODE pour 1 XOF, ce qui
 * correspond EXACTEMENT à `rate_from_xof`. Toute la conversion d'affichage et
 * des paiements (PayPal/crypto/KPay local) s'appuie ensuite sur ces taux.
 *
 * Plan gratuit : ~1 rafraîchissement/jour, quota mensuel large. On planifie donc
 * une mise à jour quotidienne (cf. routes/console.php) — surtout pas à chaque
 * requête.
 *
 * Clé API : `config('services.exchangerate.key')` (EXCHANGERATE_API_KEY dans .env).
 */
class ExchangeRateService
{
    private const ENDPOINT = 'https://v6.exchangerate-api.com/v6';

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    private function apiKey(): string
    {
        return (string) config('services.exchangerate.key', '');
    }

    private function base(): string
    {
        return strtoupper((string) config('services.exchangerate.base', 'XOF'));
    }

    /**
     * Récupère les taux live (unités par 1 unité de base). Lève une exception
     * en cas d'échec API (result != success) ou réseau.
     *
     * @return array<string,float> code devise => taux
     */
    public function fetchLatest(): array
    {
        $url = self::ENDPOINT . '/' . $this->apiKey() . '/latest/' . $this->base();

        $response = Http::timeout(20)->acceptJson()->get($url);
        $json = $response->json();

        if (! is_array($json) || ($json['result'] ?? null) !== 'success') {
            $type = is_array($json) ? ($json['error-type'] ?? 'inconnu') : 'réponse invalide';
            throw new \RuntimeException("ExchangeRate-API a échoué (HTTP {$response->status()}, error-type: {$type}).");
        }

        $rates = $json['conversion_rates'] ?? [];

        return is_array($rates) ? $rates : [];
    }

    /**
     * Met à jour `rate_from_xof` de chaque devise connue avec le taux live.
     * Ne touche que les devises présentes des DEUX côtés (table + API).
     *
     * @return int nombre de devises mises à jour
     */
    public function updateCurrencies(): int
    {
        $rates = $this->fetchLatest();
        if (empty($rates)) {
            return 0;
        }

        // Sécurité : la base doit valoir 1.0 dans sa propre base.
        $base = $this->base();
        if (($rates[$base] ?? null) !== null && abs((float) $rates[$base] - 1.0) > 0.0001) {
            Log::warning('[ExchangeRateService] Taux de base inattendu', [
                'base' => $base,
                'value' => $rates[$base],
            ]);
        }

        $updated = 0;
        foreach (Currency::all() as $currency) {
            $code = strtoupper($currency->code);
            if (isset($rates[$code]) && is_numeric($rates[$code])) {
                $currency->update(['rate_from_xof' => (float) $rates[$code]]);
                $updated++;
            }
        }

        Log::info('[ExchangeRateService] Taux mis à jour', [
            'base' => $base,
            'updated' => $updated,
            'received' => count($rates),
        ]);

        return $updated;
    }
}
