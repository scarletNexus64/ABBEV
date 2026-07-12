<?php

namespace App\Support;

use App\Models\Configuration;
use Illuminate\Support\Facades\Schema;

/**
 * Applique au runtime la configuration email stockée en base (groupe « email »
 * de la page Configuration) par-dessus la config Laravel lue depuis .env.
 *
 * Ainsi l'admin pilote le mailer (SMTP / Resend / log) depuis le dashboard sans
 * jamais toucher au .env. On ne remplace une valeur que si elle est réellement
 * renseignée en base, pour ne pas écraser un .env valide par des champs vides.
 */
class RuntimeMailConfig
{
    public static function apply(): void
    {
        // Le boot() tourne aussi pendant les migrations / avant que la table
        // existe : on ne fait rien tant qu'elle n'est pas là.
        try {
            if (! Schema::hasTable('configurations')) {
                return;
            }
        } catch (\Throwable $e) {
            return; // pas de connexion DB (ex: certaines commandes artisan)
        }

        $cfg = Configuration::where('group', 'email')->pluck('value', 'key');

        if ($cfg->isEmpty()) {
            return;
        }

        $mailer = trim((string) $cfg->get('mail_mailer', ''));
        if ($mailer !== '') {
            config(['mail.default' => $mailer]);
        }

        // SMTP
        self::set('mail.mailers.smtp.host', $cfg->get('mail_host'));
        self::set('mail.mailers.smtp.port', $cfg->get('mail_port'), numeric: true);
        self::set('mail.mailers.smtp.username', $cfg->get('mail_username'));
        self::set('mail.mailers.smtp.password', $cfg->get('mail_password'));

        // Chiffrement : « aucun »/« none »/vide → pas de chiffrement.
        $enc = strtolower(trim((string) $cfg->get('mail_encryption', '')));
        if ($enc !== '') {
            config(['mail.mailers.smtp.encryption' => in_array($enc, ['none', 'aucun'], true) ? null : $enc]);
        }

        // Resend (API)
        self::set('services.resend.key', $cfg->get('resend_api_key'));

        // Expéditeur (From)
        self::set('mail.from.address', $cfg->get('mail_from_address'));
        self::set('mail.from.name', $cfg->get('mail_from_name'));
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
