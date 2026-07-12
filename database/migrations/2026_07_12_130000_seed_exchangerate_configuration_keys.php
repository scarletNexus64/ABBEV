<?php

use App\Models\Configuration;
use Illuminate\Database\Migrations\Migration;

/**
 * Ajoute les clés du groupe « exchange_rate » (ExchangeRate-API) à la table
 * configurations pour exposer l'onglet « Taux de change » dans la page
 * Configuration. Idempotent. Valeurs vides par défaut : tant qu'elles ne sont
 * pas renseignées, la config .env reste utilisée (RuntimeExchangeRateConfig
 * n'écrase que les valeurs non vides).
 */
return new class extends Migration
{
    public function up(): void
    {
        $configs = [
            ['key' => 'exchangerate_api_key', 'value' => '', 'group' => 'exchange_rate', 'description' => 'Clé API ExchangeRate-API (taux de change live)', 'is_secret' => true],
            ['key' => 'exchangerate_base', 'value' => 'XOF', 'group' => 'exchange_rate', 'description' => 'Devise de base des taux (XOF par défaut)', 'is_secret' => false],
        ];

        foreach ($configs as $config) {
            Configuration::updateOrCreate(['key' => $config['key']], $config);
        }
    }

    public function down(): void
    {
        Configuration::where('group', 'exchange_rate')->delete();
    }
};
