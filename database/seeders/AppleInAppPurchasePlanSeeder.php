<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

/**
 * Les trois forfaits ABBEV et leur correspondance avec les abonnements
 * auto-renouvelables déclarés dans App Store Connect.
 *
 * Chaque `apple_product_id` DOIT être identique au Product ID du groupe
 * d'abonnement « ABBEV Access ». C'est cette colonne que consulte
 * SubscriptionPaymentController::verifyAppleReceipt() pour retrouver le
 * plan à partir du reçu renvoyé par StoreKit : une divergence d'une seule
 * lettre et l'achat est encaissé par Apple sans être crédité côté ABBEV.
 *
 * Le `tier` pilote la rémunération producteur (ProducerRevenueService) :
 * un paiement crédite +1 vue à tout le contenu approuvé du même tier.
 *
 * `price` reste la référence FCFA facturée hors iOS (KPay, Stripe, PayPal).
 * Sur iOS c'est Apple qui facture, au palier USD choisi dans App Store
 * Connect — les deux montants ne se correspondent qu'approximativement,
 * la conversion étant figée par les paliers Apple. Voir la table
 * `apple_tier_usd` ci-dessous, tenue à jour à la main lors des
 * changements de prix App Store Connect.
 */
class AppleInAppPurchasePlanSeeder extends Seeder
{
    /**
     * Palier USD retenu dans App Store Connect pour chaque product ID.
     * Purement documentaire : Apple reste la source de vérité du prix iOS.
     *
     * @var array<string,string>
     */
    private const APPLE_TIER_USD = [
        'com.abbev.sub.classique.monthly' => '1.99',
        'com.abbev.sub.standard.monthly'  => '4.99',
        'com.abbev.sub.premium.monthly'   => '8.99',
    ];

    public function run(): void
    {
        foreach ($this->plans() as $plan) {
            SubscriptionPlan::updateOrCreate(
                ['apple_product_id' => $plan['apple_product_id']],
                $plan
            );
        }

        // L'ancienne offre unique à 2500 F (product ID « com.abbev.sub.monthly »)
        // est remplacée par les trois forfaits ci-dessus. On la désactive au
        // lieu de la supprimer : des UserSubscription y font encore référence
        // et doivent rester lisibles dans l'historique.
        SubscriptionPlan::where('apple_product_id', 'com.abbev.sub.monthly')
            ->update(['is_active' => false, 'is_popular' => false]);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function plans(): array
    {
        return [
            [
                'apple_product_id' => 'com.abbev.sub.classique.monthly',
                'name'             => 'Classique',
                'tier'             => 'classique',
                'description'      => 'L\'essentiel du catalogue ABBEV',
                'price'            => 1000,
                'duration_days'    => 30,
                'features'         => [
                    'Accès au catalogue Classique',
                    'Visionnage en HD',
                    'Sans publicité',
                ],
                'is_active'  => true,
                'is_popular' => false,
                'order'      => 1,
            ],
            [
                'apple_product_id' => 'com.abbev.sub.standard.monthly',
                'name'             => 'Standard',
                'tier'             => 'standard',
                'description'      => 'Le catalogue élargi, séries comprises',
                'price'            => 2500,
                'duration_days'    => 30,
                'features'         => [
                    'Accès aux catalogues Classique et Standard',
                    'Visionnage en Full HD',
                    'Sans publicité',
                    'Téléchargement hors-ligne',
                ],
                'is_active'  => true,
                'is_popular' => true,
                'order'      => 2,
            ],
            [
                'apple_product_id' => 'com.abbev.sub.premium.monthly',
                'name'             => 'Premium',
                'tier'             => 'premium',
                'description'      => 'Tout ABBEV, exclusivités et avant-premières',
                'price'            => 5000,
                'duration_days'    => 30,
                'features'         => [
                    'Accès à tout le catalogue ABBEV',
                    'Exclusivités et avant-premières',
                    'Visionnage en Full HD',
                    'Sans publicité',
                    'Téléchargement hors-ligne illimité',
                ],
                'is_active'  => true,
                'is_popular' => false,
                'order'      => 3,
            ],
        ];
    }
}
