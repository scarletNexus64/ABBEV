<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Gratuit',
                'description' => 'Accès limité avec publicités',
                'price' => 0,
                'duration_days' => 30,
                'features' => [
                    'Visionnage en SD',
                    'Publicités',
                    'Accès limité au catalogue',
                    '1 écran simultané'
                ],
                'is_active' => true,
                'is_popular' => false,
                'order' => 1
            ],
            [
                'name' => 'Basic',
                'description' => 'Pour un usage personnel',
                'price' => 2500,
                'duration_days' => 30,
                'features' => [
                    'Visionnage HD',
                    'Sans publicité',
                    'Catalogue complet',
                    '1 écran simultané',
                    'Téléchargement (5 contenus)'
                ],
                'is_active' => true,
                'is_popular' => false,
                'order' => 2
            ],
            [
                'name' => 'Standard',
                'description' => 'Pour partager en famille',
                'price' => 5000,
                'duration_days' => 30,
                'features' => [
                    'Visionnage Full HD',
                    'Sans publicité',
                    'Catalogue complet + Exclusivités',
                    '2 écrans simultanés',
                    'Téléchargement illimité',
                    'Accès prioritaire aux nouveautés'
                ],
                'is_active' => true,
                'is_popular' => true,
                'order' => 3
            ],
            [
                'name' => 'Premium',
                'description' => 'L\'expérience ultime',
                'price' => 10000,
                'duration_days' => 30,
                'features' => [
                    'Visionnage 4K + HDR',
                    'Sans publicité',
                    'Catalogue complet + Avant-premières',
                    '4 écrans simultanés',
                    'Téléchargement illimité',
                    'Contenu exclusif Premium',
                    'Support prioritaire 24/7',
                    'Invitations événements ABBEV'
                ],
                'is_active' => true,
                'is_popular' => false,
                'order' => 4
            ],
            [
                'name' => 'Annuel Basic',
                'description' => 'Basic - 12 mois (économisez 20%)',
                'price' => 24000, // 2500 * 12 * 0.8
                'duration_days' => 365,
                'features' => [
                    'Toutes les fonctionnalités Basic',
                    '20% de réduction',
                    'Facturé annuellement'
                ],
                'is_active' => true,
                'is_popular' => false,
                'order' => 5
            ],
            [
                'name' => 'Annuel Premium',
                'description' => 'Premium - 12 mois (économisez 25%)',
                'price' => 90000, // 10000 * 12 * 0.75
                'duration_days' => 365,
                'features' => [
                    'Toutes les fonctionnalités Premium',
                    '25% de réduction',
                    'Facturé annuellement',
                    '2 mois offerts'
                ],
                'is_active' => true,
                'is_popular' => false,
                'order' => 6
            ]
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::create($plan);
        }
    }
}
