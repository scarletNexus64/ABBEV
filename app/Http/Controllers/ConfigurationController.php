<?php

namespace App\Http\Controllers;

use App\Models\Configuration;
use App\Services\BunnyStreamService;
use App\Services\KpayService;
use App\Support\RuntimeBunnyConfig;
use App\Support\RuntimeMailConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ConfigurationController extends Controller
{
    public function index()
    {
        $configurations = Configuration::all()->groupBy('group');

        return view('configuration.index', compact('configurations'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'configs' => 'required|array',
            'configs.*' => 'nullable|string',
        ]);

        foreach ($validated['configs'] as $key => $value) {
            Configuration::where('key', $key)->update(['value' => $value]);
        }

        return back()->with('success', 'Configuration mise à jour avec succès.');
    }

    /**
     * Sauvegarder uniquement les paramètres d'un groupe (onglet),
     * sans toucher aux autres catégories de configuration.
     */
    public function updateGroup(Request $request, string $group)
    {
        $validated = $request->validate([
            'configs' => 'required|array',
            'configs.*' => 'nullable|string',
        ]);

        // On ne met à jour que les clés appartenant réellement au
        // groupe ciblé (sécurité : ignore toute clé hors groupe).
        $allowedKeys = Configuration::where('group', $group)->pluck('key')->all();

        $updated = 0;
        foreach ($validated['configs'] as $key => $value) {
            if (in_array($key, $allowedKeys, true)) {
                Configuration::where('key', $key)->update(['value' => $value]);
                $updated++;
            }
        }

        return back()
            ->with('success', "Configuration « {$group} » mise à jour ({$updated} paramètre(s)).")
            ->with('active_tab', $group);
    }

    /**
     * Teste la connectivité avec l'API KPay en vérifiant les credentials.
     */
    public function testKpay(KpayService $kpay)
    {
        if (!$kpay->isConfigured()) {
            return back()
                ->with('error', 'KPay non configuré : veuillez renseigner l\'URL, l\'API Key et la Secret Key.')
                ->with('active_tab', 'kpay');
        }

        // On vérifie les credentials en récupérant un paiement fictif.
        // Credentials valides → 404 (not found), invalides → 401.
        // Aucun effet de bord (pas de vrai paiement initié).
        $result = $kpay->getPayment('test-connectivity-' . time());

        $http = $result['http'] ?? null;

        // 2xx ou 404 = credentials acceptés (le paiement fictif n'existe pas, c'est normal)
        if ($result['success'] || $http === 404) {
            return back()
                ->with('success', 'Connexion KPay réussie ! Les credentials sont valides.')
                ->with('active_tab', 'kpay');
        }

        $message = $result['message'] ?? 'Erreur inconnue';

        return back()
            ->with('error', "Échec de connexion KPay (HTTP {$http}) : {$message}")
            ->with('active_tab', 'kpay');
    }

    /**
     * Envoie un email de test à l'admin connecté avec la config email actuelle
     * (groupe « email »). Permet de vérifier immédiatement que le mailer livre.
     */
    public function testMail(Request $request)
    {
        // Réapplique la config email de la base (au cas où elle vient d'être
        // enregistrée dans la même requête PJAX que ce test).
        RuntimeMailConfig::apply();

        $to = $request->user()?->email;

        if (! $to) {
            return back()
                ->with('error', "Impossible de déterminer l'adresse de destination (email admin manquant).")
                ->with('active_tab', 'email');
        }

        $mailer = Configuration::getValue('mail_mailer', 'log');

        if ($mailer === 'log') {
            return back()
                ->with('error', "Le mailer est sur « log » : l'email n'est pas livré (écrit dans les logs). Choisissez SMTP ou Resend, enregistrez, puis retestez.")
                ->with('active_tab', 'email');
        }

        try {
            Mail::raw(
                "Ceci est un email de test envoyé depuis la configuration ABBEV.\n\n".
                "Si vous le recevez, votre mailer « {$mailer} » fonctionne correctement.\n\nL'équipe ABBEV",
                function ($message) use ($to) {
                    $message->to($to)->subject('Email de test — ABBEV');
                }
            );

            return back()
                ->with('success', "Email de test envoyé à {$to} via « {$mailer} ». Vérifiez votre boîte de réception (et les spams).")
                ->with('active_tab', 'email');
        } catch (\Throwable $e) {
            return back()
                ->with('error', "Échec de l'envoi via « {$mailer} » : ".$e->getMessage())
                ->with('active_tab', 'email');
        }
    }

    /**
     * Teste la connectivité Bunny Stream avec la configuration actuelle (groupe
     * « bunny »). Vérifie que la clé API + la library répondent (cause typique
     * des erreurs 401 à l'upload).
     */
    public function testBunny(BunnyStreamService $bunny)
    {
        // Réapplique la config Bunny de la base (au cas où elle vient d'être
        // enregistrée), puis reconstruit le service avec ces valeurs.
        RuntimeBunnyConfig::apply();
        $bunny = new BunnyStreamService();

        if (! $bunny->isConfigured()) {
            return back()
                ->with('error', 'Bunny non configuré : renseignez Library ID, clé API et CDN Hostname.')
                ->with('active_tab', 'bunny');
        }

        $result = $bunny->ping();

        if ($result['ok']) {
            return back()
                ->with('success', 'Connexion Bunny réussie ! La clé API et la library sont valides.')
                ->with('active_tab', 'bunny');
        }

        $http = $result['status'];
        $detail = match ($http) {
            401, 403 => 'clé API (AccessKey) invalide.',
            404      => 'Library ID introuvable.',
            null     => 'Bunny injoignable ('.($result['message'] ?? 'erreur réseau').').',
            default  => 'réponse HTTP '.$http.'.',
        };

        return back()
            ->with('error', 'Échec de connexion Bunny : '.$detail)
            ->with('active_tab', 'bunny');
    }
}
