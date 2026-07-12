<?php

use App\Models\Configuration;
use Illuminate\Database\Migrations\Migration;

/**
 * Ajoute les clés du groupe « email » (SMTP / Resend) à la table configurations
 * pour que la page Configuration expose l'onglet Email en production, sans avoir
 * à relancer le ConfigurationSeeder. Idempotent (updateOrCreate).
 */
return new class extends Migration
{
    public function up(): void
    {
        $emailConfigs = [
            ['key' => 'mail_mailer', 'value' => 'log', 'group' => 'email', 'description' => 'Mailer (log, smtp ou resend)', 'is_secret' => false],
            ['key' => 'mail_host', 'value' => '', 'group' => 'email', 'description' => 'Hôte SMTP (ex: smtp.gmail.com)', 'is_secret' => false],
            ['key' => 'mail_port', 'value' => '587', 'group' => 'email', 'description' => 'Port SMTP (587 TLS, 465 SSL)', 'is_secret' => false],
            ['key' => 'mail_username', 'value' => '', 'group' => 'email', 'description' => 'Utilisateur SMTP', 'is_secret' => false],
            ['key' => 'mail_password', 'value' => '', 'group' => 'email', 'description' => 'Mot de passe SMTP', 'is_secret' => true],
            ['key' => 'mail_encryption', 'value' => 'tls', 'group' => 'email', 'description' => 'Chiffrement SMTP (tls, ssl ou aucun)', 'is_secret' => false],
            ['key' => 'resend_api_key', 'value' => '', 'group' => 'email', 'description' => 'Clé API Resend (mailer = resend)', 'is_secret' => true],
            ['key' => 'mail_from_address', 'value' => 'no-reply@abbev.tv', 'group' => 'email', 'description' => 'Adresse d\'expéditeur (From)', 'is_secret' => false],
            ['key' => 'mail_from_name', 'value' => 'ABBEV', 'group' => 'email', 'description' => 'Nom d\'expéditeur (From)', 'is_secret' => false],
        ];

        foreach ($emailConfigs as $config) {
            Configuration::updateOrCreate(['key' => $config['key']], $config);
        }
    }

    public function down(): void
    {
        Configuration::where('group', 'email')->delete();
    }
};
