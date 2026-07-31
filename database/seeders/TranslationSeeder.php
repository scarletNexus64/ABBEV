<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Rubrique;
use App\Models\SubscriptionPlan;
use App\Models\TicketType;
use Illuminate\Database\Seeder;

/**
 * Traductions anglaises du contenu piloté depuis le panel admin.
 *
 * Portée : les vocabulaires **finis et énumérables** — catégories, plans,
 * rubriques, types de billets. Les titres et synopsis des 149 médias ne sont
 * PAS ici : ce sont des textes rédactionnels propres à chaque œuvre, qu'aucun
 * seeder ne peut inventer (voir `TranslateMediaCommand` pour les saisir).
 *
 * Idempotent : `setTranslation` fait un `updateOrCreate`. Relancer le seeder
 * ne crée pas de doublons et rafraîchit les libellés modifiés ici.
 *
 * Rappel du modèle : la table métier garde le **français** (source de vérité),
 * cette table n'ajoute que l'anglais. Un contenu absent de ces listes
 * continuera de s'afficher en français — dégradation propre, jamais de vide.
 */
class TranslationSeeder extends Seeder
{
    /**
     * Catégories / genres : nom FR exact en base → nom EN.
     * La clé DOIT correspondre au `name` stocké, sinon la ligne est ignorée
     * (et signalée dans le récapitulatif de fin).
     */
    private const CATEGORIES = [
        'Acteur/Actrice'         => 'Actor/Actress',
        'Action'                 => 'Action',
        'Animation'              => 'Animation',
        'Anime'                  => 'Anime',
        'Aventure'               => 'Adventure',
        'Biographie'             => 'Biography',
        'Comédie'                => 'Comedy',
        'Cours de Cinéma'        => 'Film Courses',
        'Court-métrage'          => 'Short Film',
        'Crime'                  => 'Crime',
        'Documentaire'           => 'Documentary',
        'Drame'                  => 'Drama',
        'Famille'                => 'Family',
        'Fantastique'            => 'Fantasy',
        'Film Documentaire'      => 'Documentary Film',
        "Film d'Animation"       => 'Animated Film',
        'Films'                  => 'Movies',
        'Financement de Projets' => 'Project Funding',
        'Guerre'                 => 'War',
        'Historique'             => 'Historical',
        'Horreur'                => 'Horror',
        'Lions Head Awards'      => 'Lions Head Awards',
        'Long-métrage'           => 'Feature Film',
        'Meilleur Acteur'        => 'Best Actor',
        'Meilleur Film'          => 'Best Film',
        'Meilleur Réalisateur'   => 'Best Director',
        'Meilleur Scénario'      => 'Best Screenplay',
        'Meilleure Actrice'      => 'Best Actress',
        'Meilleure Série'        => 'Best Series',
        'Montage'                => 'Editing',
        'Musical'                => 'Musical',
        'Mystère'                => 'Mystery',
        'Photographie'           => 'Cinematography',
        'Production'             => 'Production',
        'Romance'                => 'Romance',
        'Réalisation'            => 'Directing',
        'Réservation Cinéma'     => 'Cinema Booking',
        "Révélation de l'année"  => 'Breakthrough of the Year',
        'Science-Fiction'        => 'Science Fiction',
        'Scénarisation'          => 'Screenwriting',
        'Son & Musique'          => 'Sound & Music',
        'Sport'                  => 'Sports',
        'Séries'                 => 'Series',
        'Thriller'               => 'Thriller',
        'Web-série'              => 'Web Series',
        'Western'                => 'Western',
    ];

    /** Descriptions de catégories rencontrées en base. */
    private const CATEGORY_DESCRIPTIONS = [
        "Films et séries d'action palpitants" => 'Thrilling action movies and series',
        'Pour rire et se détendre'            => 'To laugh and unwind',
        'Histoires émouvantes et profondes'   => 'Moving, profound stories',
        'Frissons et suspense garantis'       => 'Chills and suspense guaranteed',
        'Découvrez le monde réel'             => 'Discover the real world',
        "Histoires d'amour touchantes"        => 'Touching love stories',
        "Voyages vers l'avenir"               => 'Journeys into the future',
        'Suspense et mystère'                 => 'Suspense and mystery',
        'Pour toute la famille'               => 'For the whole family',
        'Divertissement familial'             => 'Family entertainment',
        'Mondes magiques et fantastiques'     => 'Magical, fantastic worlds',
        'Récits de guerre historiques'        => 'Historical war stories',
    ];

    /** Plans d'abonnement : nom FR → [nom EN, description EN]. */
    private const PLANS = [
        'Basic' => [
            'name'        => 'Basic',
            'description' => 'For personal use',
        ],
        'Premium' => [
            'name'        => 'Premium',
            'description' => 'The ultimate experience',
        ],
        'ABBEV' => [
            'name'        => 'ABBEV',
            'description' => 'Full access to the entire ABBEV catalogue',
        ],
    ];

    /**
     * Puces `features` des plans (JSON). Traduites ligne à ligne : la clé est
     * la puce FR exacte, ce qui rend la table réutilisable entre plans.
     */
    private const FEATURES = [
        'Visionnage HD'                         => 'HD streaming',
        'Visionnage Full HD'                    => 'Full HD streaming',
        'Visionnage 4K + HDR'                   => '4K + HDR streaming',
        'Sans publicité'                        => 'Ad-free',
        'Catalogue complet'                     => 'Full catalogue',
        'Catalogue complet (films & séries)'    => 'Full catalogue (movies & series)',
        'Catalogue complet + Avant-premières'   => 'Full catalogue + Premieres',
        '1 écran simultané'                     => '1 screen at a time',
        '4 écrans simultanés'                   => '4 screens at a time',
        'Téléchargement (5 contenus)'           => 'Downloads (5 items)',
        'Téléchargement illimité'               => 'Unlimited downloads',
        'Téléchargement hors-ligne illimité'    => 'Unlimited offline downloads',
        'Contenu exclusif Premium'              => 'Exclusive Premium content',
        'Support prioritaire 24/7'              => '24/7 priority support',
        'Invitations événements ABBEV'          => 'Invitations to ABBEV events',
        'Nouveautés et exclusivités'            => 'New releases and exclusives',
    ];

    /** Rubriques : nom FR → [nom EN, description EN]. */
    private const RUBRIQUES = [
        'Avant Première' => [
            'name'        => 'Premieres',
            'description' => 'Releases to discover before everyone else.',
        ],
        'Œuvre adaptable' => [
            'name'        => 'Adaptable Works',
            'description' => 'Literary works awaiting adaptation.',
        ],
    ];

    /** Catégories de billets (séances cinéma). */
    private const TICKET_TYPES = [
        'Standard' => 'Standard',
        'VIP'      => 'VIP',
        'Loge'     => 'Box',
        'Normal'   => 'Standard',
        'Premium'  => 'Premium',
    ];

    public function run(): void
    {
        $stats = [
            'categories'   => $this->seedCategories(),
            'plans'        => $this->seedPlans(),
            'rubriques'    => $this->seedRubriques(),
            'ticketTypes'  => $this->seedTicketTypes(),
        ];

        foreach ($stats as $label => [$done, $missed]) {
            $this->command?->info(sprintf('  %-12s %d traduits', $label, $done));
            foreach ($missed as $name) {
                // Signalé, pas silencieux : un libellé ajouté côté admin sans
                // entrée ici resterait en français sans qu'on le sache.
                $this->command?->warn("    ⚠️  sans traduction EN : « {$name} »");
            }
        }
    }

    /** @return array{0:int,1:array<string>} */
    private function seedCategories(): array
    {
        $done = 0;
        $missed = [];

        foreach (Category::all() as $category) {
            $hasName = isset(self::CATEGORIES[$category->name]);

            if ($hasName) {
                $category->setTranslation('name', 'en', self::CATEGORIES[$category->name]);
                $done++;
            } else {
                $missed[] = $category->name;
            }

            $description = (string) $category->description;
            if ($description !== '' && isset(self::CATEGORY_DESCRIPTIONS[$description])) {
                $category->setTranslation('description', 'en', self::CATEGORY_DESCRIPTIONS[$description]);
            }
        }

        return [$done, $missed];
    }

    /** @return array{0:int,1:array<string>} */
    private function seedPlans(): array
    {
        $done = 0;
        $missed = [];

        foreach (SubscriptionPlan::all() as $plan) {
            if (! isset(self::PLANS[$plan->name])) {
                $missed[] = $plan->name;
                continue;
            }

            $plan->setTranslation('name', 'en', self::PLANS[$plan->name]['name']);
            $plan->setTranslation('description', 'en', self::PLANS[$plan->name]['description']);

            // `features` est un JSON : on traduit puce par puce et on ré-encode.
            $features = $plan->features;
            if (is_string($features)) {
                $features = json_decode($features, true);
            }

            if (is_array($features) && $features !== []) {
                $translated = [];
                foreach ($features as $feature) {
                    if (! isset(self::FEATURES[$feature])) {
                        $missed[] = "feature: {$feature}";
                    }
                    // Puce inconnue → on garde le FR plutôt que de la perdre.
                    $translated[] = self::FEATURES[$feature] ?? $feature;
                }

                $plan->setTranslation(
                    'features',
                    'en',
                    json_encode($translated, JSON_UNESCAPED_UNICODE),
                );
            }

            $done++;
        }

        return [$done, $missed];
    }

    /** @return array{0:int,1:array<string>} */
    private function seedRubriques(): array
    {
        $done = 0;
        $missed = [];

        foreach (Rubrique::all() as $rubrique) {
            if (! isset(self::RUBRIQUES[$rubrique->name])) {
                $missed[] = $rubrique->name;
                continue;
            }

            $rubrique->setTranslation('name', 'en', self::RUBRIQUES[$rubrique->name]['name']);
            $rubrique->setTranslation('description', 'en', self::RUBRIQUES[$rubrique->name]['description']);
            $done++;
        }

        return [$done, $missed];
    }

    /** @return array{0:int,1:array<string>} */
    private function seedTicketTypes(): array
    {
        $done = 0;
        $missed = [];

        foreach (TicketType::all() as $type) {
            if (! isset(self::TICKET_TYPES[$type->name])) {
                $missed[] = $type->name;
                continue;
            }

            $type->setTranslation('name', 'en', self::TICKET_TYPES[$type->name]);
            $done++;
        }

        return [$done, $missed];
    }
}
