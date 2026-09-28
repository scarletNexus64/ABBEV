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
     * Catégories : **slug** → [nom EN, description EN].
     *
     * Indexé sur le slug et non sur le libellé français : le slug est la clé
     * stable du catalogue (cf. `CategorySeeder`), alors qu'un libellé se
     * reformule au fil des retours client — et chaque reformulation faisait
     * silencieusement retomber la ligne en français.
     */
    private const CATEGORIES = [
        // Les 15 genres de cat.md — seules entrées de la table `categories`
        // depuis que formats, casting, cours, appels, awards et billetterie
        // ont leur propre module.
        'drame' => ['Drama', 'Dramatic, emotionally charged stories'],
        'romance' => ['Romance', 'Love stories and romantic relationships'],
        'aventure' => ['Adventure', 'Exploration, quests and epic journeys'],
        'comedie' => ['Comedy', 'Comedies and humorous series to make you laugh'],
        'famille' => ['Family', 'Content suitable for the whole family'],
        'fantastique' => ['Fantasy', 'Magical worlds, fantastic creatures and mythology'],
        'documentaire' => ['Documentary', 'Documentary films and series on real subjects'],
        'docu-fiction' => ['Docudrama', 'True stories reconstructed with the means of fiction'],
        'policier' => ['Crime', 'Investigations, suspense and crime stories'],
        'historique' => ['Historical', 'Reconstructions of historical events'],
        'mystere' => ['Mystery', 'Riddles and mysterious investigations'],
        'science-fiction' => ['Science Fiction', 'Futuristic worlds, advanced technology and imagined universes'],
        'western' => ['Western', 'Wide open spaces, cowboys and duels'],
        'animation' => ['Animation', 'Animated films and series for all ages'],
        'horreur' => ['Horror', 'Horror and terror films and series'],
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
        'Classique' => [
            'name'        => 'Classic',
            'description' => 'The essentials of the ABBEV catalogue',
        ],
        'Standard' => [
            'name'        => 'Standard',
            'description' => 'The extended catalogue, series included',
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
        'Accès au catalogue Classique'          => 'Access to the Classic catalogue',
        'Accès aux catalogues Classique et Standard' => 'Access to the Classic and Standard catalogues',
        'Accès à tout le catalogue ABBEV'       => 'Access to the entire ABBEV catalogue',
        'Exclusivités et avant-premières'       => 'Exclusives and premieres',
        'Visionnage en HD'                      => 'HD streaming',
        'Visionnage en Full HD'                 => 'Full HD streaming',
        'Téléchargement hors-ligne'             => 'Offline downloads',
    ];

    /** Rubriques : nom FR → [nom EN, description EN]. */
    private const RUBRIQUES = [
        'Avant-première' => [
            'name'        => 'Premieres',
            'description' => 'Releases to discover before everyone else.',
        ],
        // Ancienne graphie, encore présente dans les bases existantes.
        'Avant Première' => [
            'name'        => 'Premieres',
            'description' => 'Releases to discover before everyone else.',
        ],
        'Œuvre adaptable' => [
            'name'        => 'Adaptable Works',
            'description' => 'Literary works awaiting adaptation.',
        ],
        'Sport' => [
            'name'        => 'Sports',
            'description' => 'Competitions, sports shows and documentaries.',
        ],
        'Jeux' => [
            'name'        => 'Games',
            'description' => 'Game shows, quizzes and entertainment.',
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
            $entry = self::CATEGORIES[$category->slug] ?? null;

            if ($entry === null) {
                // Signalé par le slug : c'est lui qu'il faudra ajouter à la
                // table ci-dessus, pas le libellé.
                $missed[] = $category->slug;
                continue;
            }

            [$name, $description] = $entry;
            $category->setTranslation('name', 'en', $name);
            if ($description !== '') {
                $category->setTranslation('description', 'en', $description);
            }
            $done++;
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
