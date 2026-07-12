<?php

namespace App\Support;

use App\Models\Configuration;
use Illuminate\Support\Facades\Schema;

/**
 * Applique au runtime la configuration ExchangeRate-API stockée en base (groupe
 * « exchange_rate » de la page Configuration) par-dessus la config lue depuis
 * .env.
 *
 * Permet à l'admin de renseigner/corriger la clé API des taux de change
 * directement depuis le dashboard, sans toucher au .env. On ne remplace une
 * valeur que si elle est réellement renseignée en base.
 */
class RuntimeExchangeRateConfig
{
    public static function apply(): void
    {
        try {
            if (! Schema::hasTable('configurations')) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        $cfg = Configuration::where('group', 'exchange_rate')->pluck('value', 'key');

        if ($cfg->isEmpty()) {
            return;
        }

        self::set('services.exchangerate.key', $cfg->get('exchangerate_api_key'));
        self::set('services.exchangerate.base', $cfg->get('exchangerate_base'));
    }

    private static function set(string $configKey, $value): void
    {
        if ($value === null) {
            return;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return;
        }

        config([$configKey => $value]);
    }
}
