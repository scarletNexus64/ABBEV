<?php

use App\Models\Configuration;
use Illuminate\Database\Migrations\Migration;

/**
 * Clés du groupe « revenue » : tarif de rémunération PAR VUE et PAR TIER, versé
 * au producteur. Une vue = un abonné qui paie un forfait du tier du contenu.
 * Gain producteur = vues du contenu × tarif du tier. Éditable au dashboard
 * (onglet « Revenus producteurs »), avec simulation pour éviter les erreurs.
 */
return new class extends Migration
{
    public function up(): void
    {
        $configs = [
            ['key' => 'revenue_per_view_classique', 'value' => '0', 'group' => 'revenue', 'description' => 'Montant versé au producteur par vue — tier Classique', 'is_secret' => false],
            ['key' => 'revenue_per_view_standard', 'value' => '0', 'group' => 'revenue', 'description' => 'Montant versé au producteur par vue — tier Standard', 'is_secret' => false],
            ['key' => 'revenue_per_view_premium', 'value' => '0', 'group' => 'revenue', 'description' => 'Montant versé au producteur par vue — tier Premium', 'is_secret' => false],
            ['key' => 'revenue_currency', 'value' => 'XOF', 'group' => 'revenue', 'description' => 'Devise des gains producteurs (code ISO, ex. XOF)', 'is_secret' => false],
        ];

        foreach ($configs as $config) {
            Configuration::updateOrCreate(['key' => $config['key']], $config);
        }
    }

    public function down(): void
    {
        Configuration::where('group', 'revenue')->delete();
    }
};
