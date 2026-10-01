<?php

namespace Database\Seeders;

use App\Models\ProducerPlan;
use Illuminate\Database\Seeder;

/**
 * Pack producteur initial : 20 000 FCFA par mois.
 *
 * Sûr en production : il ne crée le pack QUE s'il n'existe pas encore, et ne
 * touche à rien d'autre. Relancé plus tard, il ne réécrit pas le prix ou la
 * période réglés depuis le dashboard.
 *
 *   php artisan db:seed --class=ProducerPlanSeeder --force
 *
 * Dès que ce pack (actif) existe, l'espace des producteurs sans abonnement
 * en cours est verrouillé.
 */
class ProducerPlanSeeder extends Seeder
{
    public function run(): void
    {
        if ($plan = ProducerPlan::current()) {
            $price = number_format((float) $plan->price, 0, ',', ' ');
            $this->command?->info("Pack producteur déjà configuré (« {$plan->name} », {$price} FCFA {$plan->periodLabel()}) : rien à faire.");

            return;
        }

        ProducerPlan::create([
            'name' => 'Pack Producteur',
            'description' => 'Ouvrez votre espace producteur ABBEV : publiez vos films et séries et gérez tous vos modules.',
            'price' => 20000,
            'billing_period' => 'month',
            'period_count' => 1,
            'features' => [
                'Upload de vos films et séries',
                'Talents, agents & casting',
                'Lions Head Awards, cours et appels à projets',
                'Billetterie et contrôle des billets',
                'Audience de vos contenus',
                'Équipe avec permissions par module',
            ],
            'is_active' => true,
        ]);

        $this->command?->info('Pack producteur créé : 20 000 FCFA par mois.');
    }
}
