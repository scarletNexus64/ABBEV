<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Configuration;

class ConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            // ============================================
            // GÉNÉRAL
            // ============================================
            ['key' => 'app_name', 'value' => 'ABBEV', 'group' => 'general', 'description' => 'Nom de l\'application', 'is_secret' => false],
            ['key' => 'app_logo', 'value' => '/images/logo.png', 'group' => 'general', 'description' => 'Logo de l\'application', 'is_secret' => false],
            ['key' => 'app_slogan', 'value' => 'Votre plateforme de streaming', 'group' => 'general', 'description' => 'Slogan de l\'application', 'is_secret' => false],
            ['key' => 'app_description', 'value' => 'ABBEV est une plateforme de streaming qui vous permet de regarder vos films et séries préférés.', 'group' => 'general', 'description' => 'Description de l\'application', 'is_secret' => false],
            ['key' => 'contact_email', 'value' => 'contact@abbev.com', 'group' => 'general', 'description' => 'Email de contact', 'is_secret' => false],
            ['key' => 'contact_phone', 'value' => '+229 XX XX XX XX', 'group' => 'general', 'description' => 'Téléphone de contact', 'is_secret' => false],
            ['key' => 'contact_address', 'value' => 'Cotonou, Bénin', 'group' => 'general', 'description' => 'Adresse physique', 'is_secret' => false],
            ['key' => 'developed_by', 'value' => 'ABBEV Team', 'group' => 'general', 'description' => 'Développé par', 'is_secret' => false],

            // ============================================
            // RÉSEAUX SOCIAUX
            // ============================================
            ['key' => 'social_facebook', 'value' => '', 'group' => 'general', 'description' => 'Lien Facebook', 'is_secret' => false],
            ['key' => 'social_twitter', 'value' => '', 'group' => 'general', 'description' => 'Lien Twitter', 'is_secret' => false],
            ['key' => 'social_instagram', 'value' => '', 'group' => 'general', 'description' => 'Lien Instagram', 'is_secret' => false],

            // ============================================
            // MAINTENANCE
            // ============================================
            ['key' => 'maintenance_mode', 'value' => '0', 'group' => 'maintenance', 'description' => 'Mode maintenance', 'is_secret' => false],
            ['key' => 'maintenance_message', 'value' => 'Nous effectuons une maintenance technique. Nous serons de retour très bientôt.', 'group' => 'maintenance', 'description' => 'Message de maintenance', 'is_secret' => false],
            ['key' => 'maintenance_end_time', 'value' => '', 'group' => 'maintenance', 'description' => 'Heure de fin de maintenance', 'is_secret' => false],

            // ============================================
            // SYSTÈME
            // ============================================
            ['key' => 'timezone', 'value' => 'Africa/Porto-Novo', 'group' => 'system', 'description' => 'Fuseau horaire', 'is_secret' => false],
            ['key' => 'default_language', 'value' => 'fr', 'group' => 'system', 'description' => 'Langue par défaut', 'is_secret' => false],
            ['key' => 'currency', 'value' => 'XAF', 'group' => 'system', 'description' => 'Devise par défaut', 'is_secret' => false],
            ['key' => 'currency_symbol', 'value' => 'FCFA', 'group' => 'system', 'description' => 'Symbole de la devise', 'is_secret' => false],
            ['key' => 'min_deposit_amount', 'value' => '100', 'group' => 'system', 'description' => 'Montant minimum de dépôt (FCFA)', 'is_secret' => false],
            ['key' => 'min_withdrawal_amount', 'value' => '100', 'group' => 'system', 'description' => 'Montant minimum de retrait (FCFA)', 'is_secret' => false],

            // ============================================
            // PAYPAL
            // ============================================
            ['key' => 'paypal_mode', 'value' => 'sandbox', 'group' => 'paypal', 'description' => 'Mode (sandbox ou live)', 'is_secret' => false],
            ['key' => 'paypal_client_id', 'value' => '', 'group' => 'paypal', 'description' => 'Client ID PayPal', 'is_secret' => true],
            ['key' => 'paypal_client_secret', 'value' => '', 'group' => 'paypal', 'description' => 'Client Secret PayPal', 'is_secret' => true],
            ['key' => 'paypal_currency', 'value' => 'USD', 'group' => 'paypal', 'description' => 'Devise PayPal', 'is_secret' => false],
            ['key' => 'paypal_exchange_rate', 'value' => '655', 'group' => 'paypal', 'description' => 'Taux de change USD vers XAF', 'is_secret' => false],

            // ============================================
            // FEDAPAY
            // ============================================
            ['key' => 'fedapay_enabled', 'value' => '0', 'group' => 'fedapay', 'description' => 'Activer Fedapay', 'is_secret' => false],
            ['key' => 'fedapay_mode', 'value' => 'sandbox', 'group' => 'fedapay', 'description' => 'Mode (sandbox ou live)', 'is_secret' => false],
            ['key' => 'fedapay_public_key', 'value' => '', 'group' => 'fedapay', 'description' => 'Fedapay Public Key', 'is_secret' => true],
            ['key' => 'fedapay_secret_key', 'value' => '', 'group' => 'fedapay', 'description' => 'Fedapay Secret Key', 'is_secret' => true],
            ['key' => 'fedapay_webhook_secret', 'value' => '', 'group' => 'fedapay', 'description' => 'Fedapay Webhook Secret', 'is_secret' => true],
            ['key' => 'fedapay_currency', 'value' => 'XOF', 'group' => 'fedapay', 'description' => 'Devise Fedapay', 'is_secret' => false],
            ['key' => 'fedapay_callback_url', 'value' => '', 'group' => 'fedapay', 'description' => 'URL de callback Fedapay', 'is_secret' => false],
            ['key' => 'fedapay_timeout', 'value' => '300', 'group' => 'fedapay', 'description' => 'Timeout Fedapay (secondes)', 'is_secret' => false],
            ['key' => 'fedapay_auto_commission', 'value' => '1', 'group' => 'fedapay', 'description' => 'Frais Fedapay automatiques', 'is_secret' => false],

            // ============================================
            // FREEMOPAY
            // ============================================
            ['key' => 'freemopay_enabled', 'value' => '0', 'group' => 'freemopay', 'description' => 'Activer les paiements FreeMoPay', 'is_secret' => false],
            ['key' => 'freemopay_base_url', 'value' => 'https://api-v2.freemopay.com', 'group' => 'freemopay', 'description' => 'URL de base FreeMoPay', 'is_secret' => false],
            ['key' => 'freemopay_app_key', 'value' => '', 'group' => 'freemopay', 'description' => 'App Key FreeMoPay', 'is_secret' => true],
            ['key' => 'freemopay_secret_key', 'value' => '', 'group' => 'freemopay', 'description' => 'Secret Key FreeMoPay', 'is_secret' => true],
            ['key' => 'freemopay_callback_url', 'value' => '/api/webhooks/freemopay', 'group' => 'freemopay', 'description' => 'URL de callback FreeMoPay', 'is_secret' => false],
            ['key' => 'freemopay_timeout_init', 'value' => '30', 'group' => 'freemopay', 'description' => 'Timeout init paiement (secondes)', 'is_secret' => false],
            ['key' => 'freemopay_timeout_verify', 'value' => '30', 'group' => 'freemopay', 'description' => 'Timeout vérification statut (secondes)', 'is_secret' => false],
            ['key' => 'freemopay_timeout_token', 'value' => '30', 'group' => 'freemopay', 'description' => 'Timeout token (secondes)', 'is_secret' => false],
            ['key' => 'freemopay_token_cache_duration', 'value' => '3000', 'group' => 'freemopay', 'description' => 'Durée cache token (secondes) - 50 min', 'is_secret' => false],
            ['key' => 'freemopay_retry_attempts', 'value' => '5', 'group' => 'freemopay', 'description' => 'Nombre de tentatives de retry', 'is_secret' => false],
            ['key' => 'freemopay_retry_delay', 'value' => '0.5', 'group' => 'freemopay', 'description' => 'Délai entre tentatives (secondes)', 'is_secret' => false],

            // ============================================
            // KPAY
            // ============================================
            ['key' => 'kpay_enabled', 'value' => '0', 'group' => 'kpay', 'description' => 'Activer les paiements KPay', 'is_secret' => false],
            ['key' => 'kpay_base_url', 'value' => 'https://admin.kpay.site', 'group' => 'kpay', 'description' => 'URL de base KPay', 'is_secret' => false],
            ['key' => 'kpay_api_key', 'value' => '', 'group' => 'kpay', 'description' => 'API Key KPay', 'is_secret' => true],
            ['key' => 'kpay_secret_key', 'value' => '', 'group' => 'kpay', 'description' => 'Secret Key KPay', 'is_secret' => true],
            ['key' => 'kpay_max_duration', 'value' => '300', 'group' => 'kpay', 'description' => 'Durée max attente statut final (secondes)', 'is_secret' => false],

            // ============================================
            // NEXAH SMS
            // ============================================
            ['key' => 'nexah_sms_enabled', 'value' => '0', 'group' => 'nexah_sms', 'description' => 'Activer Nexah SMS', 'is_secret' => false],
            ['key' => 'nexah_base_url', 'value' => 'https://smsvas.com/bulk/public/index.php/api/v1', 'group' => 'nexah_sms', 'description' => 'URL de base Nexah', 'is_secret' => false],
            ['key' => 'nexah_send_endpoint', 'value' => '/sendsms', 'group' => 'nexah_sms', 'description' => 'Endpoint d\'envoi', 'is_secret' => false],
            ['key' => 'nexah_credits_endpoint', 'value' => '/smscredit', 'group' => 'nexah_sms', 'description' => 'Endpoint crédits SMS', 'is_secret' => false],
            ['key' => 'nexah_user', 'value' => '', 'group' => 'nexah_sms', 'description' => 'Email/Username Nexah', 'is_secret' => false],
            ['key' => 'nexah_password', 'value' => '', 'group' => 'nexah_sms', 'description' => 'Mot de passe Nexah', 'is_secret' => true],
            ['key' => 'nexah_sender_id', 'value' => 'ABBEV', 'group' => 'nexah_sms', 'description' => 'Sender ID (max 11 chars)', 'is_secret' => false],
            ['key' => 'nexah_api_key', 'value' => '', 'group' => 'nexah_sms', 'description' => 'Nexah SMS API Key', 'is_secret' => true],
            ['key' => 'nexah_api_secret', 'value' => '', 'group' => 'nexah_sms', 'description' => 'Nexah SMS API Secret', 'is_secret' => true],
            ['key' => 'nexah_account_sid', 'value' => '', 'group' => 'nexah_sms', 'description' => 'Nexah Account SID', 'is_secret' => true],
            ['key' => 'nexah_country_code', 'value' => '+229', 'group' => 'nexah_sms', 'description' => 'Code pays par défaut (Bénin)', 'is_secret' => false],
            ['key' => 'nexah_timeout', 'value' => '30', 'group' => 'nexah_sms', 'description' => 'Timeout des requêtes API (secondes)', 'is_secret' => false],

            // ============================================
            // WHATSAPP
            // ============================================
            ['key' => 'whatsapp_enabled', 'value' => '0', 'group' => 'whatsapp', 'description' => 'Activer WhatsApp Business API', 'is_secret' => false],
            ['key' => 'whatsapp_business_account_id', 'value' => '', 'group' => 'whatsapp', 'description' => 'WhatsApp Business Account ID', 'is_secret' => true],
            ['key' => 'whatsapp_phone_number_id', 'value' => '', 'group' => 'whatsapp', 'description' => 'Phone Number ID', 'is_secret' => false],
            ['key' => 'whatsapp_business_phone', 'value' => '', 'group' => 'whatsapp', 'description' => 'Numéro WhatsApp Business', 'is_secret' => false],
            ['key' => 'whatsapp_display_name', 'value' => 'ABBEV', 'group' => 'whatsapp', 'description' => 'Nom d\'affichage WhatsApp', 'is_secret' => false],
            ['key' => 'whatsapp_access_token', 'value' => '', 'group' => 'whatsapp', 'description' => 'WhatsApp Access Token (Permanent)', 'is_secret' => true],
            ['key' => 'whatsapp_api_token', 'value' => '', 'group' => 'whatsapp', 'description' => 'Token API WhatsApp Business', 'is_secret' => true],
            ['key' => 'whatsapp_app_id', 'value' => '', 'group' => 'whatsapp', 'description' => 'Meta App ID', 'is_secret' => true],
            ['key' => 'whatsapp_app_secret', 'value' => '', 'group' => 'whatsapp', 'description' => 'Meta App Secret', 'is_secret' => true],
            ['key' => 'whatsapp_api_version', 'value' => 'v22.0', 'group' => 'whatsapp', 'description' => 'Version API WhatsApp', 'is_secret' => false],
            ['key' => 'whatsapp_webhook_verify_token', 'value' => '', 'group' => 'whatsapp', 'description' => 'Token de vérification Webhook', 'is_secret' => true],
            ['key' => 'whatsapp_template_name', 'value' => 'otp_message', 'group' => 'whatsapp', 'description' => 'Nom du template OTP', 'is_secret' => false],
            ['key' => 'whatsapp_template_language', 'value' => 'fr', 'group' => 'whatsapp', 'description' => 'Langue du template', 'is_secret' => false],

            // ============================================
            // CODE PROMO
            // ============================================
            ['key' => 'promo_enabled', 'value' => '1', 'group' => 'promo', 'description' => 'Activer les codes promo', 'is_secret' => false],
            ['key' => 'promo_max_uses', 'value' => '1', 'group' => 'promo', 'description' => 'Utilisations max par défaut', 'is_secret' => false],
            ['key' => 'promo_max_discount', 'value' => '50', 'group' => 'promo', 'description' => 'Réduction max (%)', 'is_secret' => false],

            // ============================================
            // NOTIFICATIONS
            // ============================================
            ['key' => 'email_notifications', 'value' => '1', 'group' => 'notifications', 'description' => 'Notifications par email activées', 'is_secret' => false],
            ['key' => 'sms_notifications', 'value' => '0', 'group' => 'notifications', 'description' => 'Notifications par SMS activées', 'is_secret' => false],
            ['key' => 'push_notifications', 'value' => '0', 'group' => 'notifications', 'description' => 'Notifications push activées', 'is_secret' => false],
            ['key' => 'otp_default_service', 'value' => 'auto', 'group' => 'notifications', 'description' => 'Service OTP par défaut (auto, whatsapp, sms)', 'is_secret' => false],

            // ============================================
            // SÉCURITÉ
            // ============================================
            ['key' => 'two_factor_enabled', 'value' => '0', 'group' => 'security', 'description' => 'Authentification à deux facteurs', 'is_secret' => false],
            ['key' => 'password_min_length', 'value' => '8', 'group' => 'security', 'description' => 'Longueur minimale du mot de passe', 'is_secret' => false],
            ['key' => 'session_timeout', 'value' => '120', 'group' => 'security', 'description' => 'Durée de session (minutes)', 'is_secret' => false],
        ];

        foreach ($configs as $config) {
            Configuration::updateOrCreate(
                ['key' => $config['key']],
                $config
            );
        }
    }
}
