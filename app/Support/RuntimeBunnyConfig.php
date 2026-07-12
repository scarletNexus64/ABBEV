<?php

namespace App\Support;

use App\Models\Configuration;
use Illuminate\Support\Facades\Schema;

/**
 * Applique au runtime la configuration Bunny Stream stockée en base (groupe
 * « bunny » de la page Configuration) par-dessus la config lue depuis .env.
 *
 * Permet à l'admin de renseigner/corriger la clé API Bunny (cause fréquente des
 * erreurs 401 à l'upload) directement depuis le dashboard, sans toucher au .env.
 * On ne remplace une valeur que si elle est réellement renseignée en base.
 */
class RuntimeBunnyConfig
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

        $cfg = Configuration::where('group', 'bunny')->pluck('value', 'key');

        if ($cfg->isEmpty()) {
            return;
        }

        self::set('services.bunny.library_id', $cfg->get('bunny_library_id'));
        self::set('services.bunny.api_key', $cfg->get('bunny_api_key'));
        self::set('services.bunny.cdn_hostname', $cfg->get('bunny_cdn_hostname'));
        self::set('services.bunny.token_key', $cfg->get('bunny_token_key'));
        self::set('services.bunny.token_ttl', $cfg->get('bunny_token_ttl'), numeric: true);
    }

    private static function set(string $configKey, $value, bool $numeric = false): void
    {
        if ($value === null) {
            return;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return;
        }

        config([$configKey => $numeric ? (int) $value : $value]);
    }
}
