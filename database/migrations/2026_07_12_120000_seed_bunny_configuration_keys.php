<?php

use App\Models\Configuration;
use Illuminate\Database\Migrations\Migration;

/**
 * Ajoute les clés du groupe « bunny » (Bunny Stream) à la table configurations
 * pour exposer l'onglet Bunny dans la page Configuration. Idempotent.
 * Valeurs vides par défaut : tant qu'elles ne sont pas renseignées, la config
 * .env reste utilisée (RuntimeBunnyConfig n'écrase que les valeurs non vides).
 */
return new class extends Migration
{
    public function up(): void
    {
        $configs = [
            ['key' => 'bunny_library_id', 'value' => '', 'group' => 'bunny', 'description' => 'Library ID Bunny Stream', 'is_secret' => false],
            ['key' => 'bunny_api_key', 'value' => '', 'group' => 'bunny', 'description' => 'Clé API Bunny Stream (AccessKey)', 'is_secret' => true],
            ['key' => 'bunny_cdn_hostname', 'value' => '', 'group' => 'bunny', 'description' => 'CDN Hostname (ex: vz-xxxxxxxx.b-cdn.net)', 'is_secret' => false],
            ['key' => 'bunny_token_key', 'value' => '', 'group' => 'bunny', 'description' => 'Token Key (URLs signées)', 'is_secret' => true],
            ['key' => 'bunny_token_ttl', 'value' => '3600', 'group' => 'bunny', 'description' => 'Durée de validité des URLs signées (secondes)', 'is_secret' => false],
        ];

        foreach ($configs as $config) {
            Configuration::updateOrCreate(['key' => $config['key']], $config);
        }
    }

    public function down(): void
    {
        Configuration::where('group', 'bunny')->delete();
    }
};
