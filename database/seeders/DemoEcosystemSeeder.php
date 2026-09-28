<?php

namespace Database\Seeders;

use App\Models\Agent;
use App\Models\AwardCategory;
use App\Models\AwardEdition;
use App\Models\AwardNominee;
use App\Models\CastingCall;
use App\Models\CastingRole;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\Media;
use App\Models\ProjectCall;
use App\Models\ProjectPledge;
use App\Models\ProjectSubmission;
use App\Models\Rubrique;
use App\Models\Screening;
use App\Models\Talent;
use App\Models\TalentCredit;
use App\Models\TicketType;
use App\Models\User;
use App\Services\AwardVotingService;
use App\Support\AwardCatalog;
use App\Support\BusinessTime;
use Database\Seeders\Support\DemoAssets;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Données de DÉMONSTRATION de l'écosystème cat.md : talents et agents,
 * annonces de casting, cours, appels à projets, Lions Head Awards, offres de
 * billetterie (séances et codes cinéma), formats du catalogue démo.
 *
 * À lancer à la main, jamais en production :
 *
 *     php artisan db:seed --class=DemoEcosystemSeeder
 *
 * Idempotent : chaque entrée est retrouvée par son slug (ou son intitulé)
 * et mise à jour plutôt que dupliquée.
 */
class DemoEcosystemSeeder extends Seeder
{
    private DemoAssets $assets;

    /** @var array<string, Talent> */
    private array $talents = [];

    public function run(): void
    {
        $this->assets = new DemoAssets();

        $this->formats();
        $this->premieres();
        $this->adaptableWorks();
        $agents = $this->agents();
        $this->talentsDirectory($agents);
        $this->castingCalls();
        $this->courses();
        $this->projectCalls();
        $this->awards();
        $this->ticketing();

        $this->command?->info('Écosystème de démonstration prêt.');
    }

    // ------------------------------------------------------------------
    //  Catalogue : formats et avant-premières
    // ------------------------------------------------------------------

    /**
     * Le catalogue démo pointe sur un clip de 3 min : le format déduit de la
     * durée serait « court » pour tout. On fixe ici le format RÉEL de ces
     * œuvres connues (longs métrages ; séries selon la durée d'un épisode).
     */
    private function formats(): void
    {
        Media::where('type', 'movie')->update(['format' => 'long', 'format_locked' => true]);

        $shortSeries = ['Ted Lasso', 'The Bear', 'The Boys Presents: Diabolical'];
        Media::where('type', 'series')->whereIn('title', $shortSeries)
            ->update(['format' => 'court', 'format_locked' => true]);
        Media::where('type', 'series')->whereNotIn('title', $shortSeries)
            ->update(['format' => 'moyen', 'format_locked' => true]);
    }

    /** L'avant-première montre aussi des séries (onglets Films / Séries). */
    private function premieres(): void
    {
        $rubrique = Rubrique::where('slug', 'avant-premiere')->first();
        if (! $rubrique) {
            return;
        }

        $series = Media::where('type', 'series')
            ->whereIn('title', ['The Last of Us', 'Severance', 'The Bear'])
            ->pluck('id');

        // Lecture directe du pivot : la relation porte un ORDER BY que
        // PostgreSQL refuse dans un agrégat.
        $next = (int) \Illuminate\Support\Facades\DB::table('media_rubrique')
            ->where('rubrique_id', $rubrique->id)
            ->max('sort_order');
        foreach ($series as $id) {
            $rubrique->media()->syncWithoutDetaching([$id => ['sort_order' => ++$next]]);
        }
    }

    /**
     * Les œuvres adaptables du `RubriqueSeeder` pointent sur des PDF qui
     * n'existent pas sur un poste neuf : le lecteur s'ouvrirait sur une
     * erreur. On génère un extrait lisible pour chacune.
     */
    private function adaptableWorks(): void
    {
        foreach (\App\Models\Oeuvre::whereNotNull('file_path')->get() as $oeuvre) {
            if (\Illuminate\Support\Facades\Storage::disk('local')->exists($oeuvre->file_path)) {
                continue;
            }

            $this->assets->pdf($oeuvre->file_path, $oeuvre->title, [
                ['Résumé', (string) $oeuvre->description],
                ['Auteur', (string) $oeuvre->author],
                ['Extrait', "Le jour se levait à peine quand la ville commença à bruisser. Les premiers vendeurs installaient leurs étals, les taxis klaxonnaient déjà, et dans la lumière encore bleue, tout semblait possible. C'est ce matin-là que tout a commencé."],
                ['Droits d\'adaptation', "Cette œuvre est proposée à l'adaptation audiovisuelle. Contactez l'équipe ABBEV pour connaître les conditions de cession des droits."],
            ], 'ABBEV — Œuvres adaptables');
        }
    }

    // ------------------------------------------------------------------
    //  Talents & agents
    // ------------------------------------------------------------------

    /** @return array<string, Agent> */
    private function agents(): array
    {
        $rows = [
            'maison-kamdem' => ['Rose Kamdem', 'Maison Kamdem Artists', 'acteurs', 'Douala', 'CM', 'gold',
                "Agence de comédiens fondée à Douala en 2011. Représente des premiers rôles du cinéma et des séries camerounaises, et accompagne les jeunes talents jusqu'à leurs premiers castings internationaux.",
                'Douala-based acting agency founded in 2011, representing leading film and TV actors and guiding young talent to their first international castings.'],
            'sahel-talents' => ['Moussa Diarra', 'Sahel Talents', 'acteurs', 'Abidjan', 'CI', 'amber',
                "Bureau d'agents implanté à Abidjan et Dakar. Spécialiste des castings de séries panafricaines et de la publicité.",
                'Agency based in Abidjan and Dakar, specialised in pan-African series and commercial castings.'],
            'crew-connect' => ['Aïcha Mbarga', 'Crew Connect Afrique', 'techniciens', 'Yaoundé', 'CM', 'teal',
                "Agence de techniciens : chefs opérateurs, ingénieurs du son, monteurs et décorateurs, disponibles pour les tournages en Afrique centrale.",
                'Crew agency: cinematographers, sound engineers, editors and production designers available across Central Africa.'],
            'nollywood-bridge' => ['Chidi Okafor', 'Bridge Management', 'mixte', 'Lagos', 'NG', 'violet',
                "Management d'acteurs et de techniciens entre Lagos, Accra et Douala. Coproductions et tournages anglophones.",
                'Talent and crew management across Lagos, Accra and Douala, focused on co-productions and English-language shoots.'],
        ];

        $agents = [];
        foreach ($rows as $slug => [$name, $agency, $represents, $city, $country, $palette, $bio, $bioEn]) {
            $agent = Agent::updateOrCreate(['slug' => $slug], [
                'name' => $name,
                'agency' => $agency,
                'represents' => $represents,
                'email' => 'contact@' . $slug . '.demo',
                'phone' => '+237 6 90 00 ' . str_pad((string) (count($agents) + 11), 2, '0', STR_PAD_LEFT) . ' 00',
                'website_url' => 'https://' . $slug . '.demo',
                'country_code' => $country,
                'city' => $city,
                'photo_path' => $this->assets->portrait($name, $slug, $palette, 'agents'),
                'bio' => $bio,
                'is_published' => true,
            ]);
            $agent->setTranslation('bio', 'en', $bioEn);
            $agents[$slug] = $agent;
        }

        return $agents;
    }

    /** @param array<string, Agent> $agents */
    private function talentsDirectory(array $agents): void
    {
        // [prénom, nom, métier, rang, genre, naissance, ville, pays, agent, palette, accroche, bio, crédits]
        $actors = [
            ['Awa', 'Ndiaye', 'acteur', 'A2', 'femme', 1968, 'Dakar', 'SN', 'sahel-talents', 'gold',
                'Légende du cinéma ouest-africain', "Quarante ans de carrière entre Dakar, Paris et Ouagadougou. Révélée au FESPACO, elle a porté plus de trente longs métrages et reste une référence pour toute une génération d'actrices.",
                [['Le Silence du Baobab', 1994, 'Rôle principal — Mariama'], ['Terre Rouge', 2008, 'Ndèye'], ['La Dernière Reine', 2021, 'Reine Aïssatou']]],
            ['Emmanuel', 'Ekambi', 'acteur', 'A1', 'homme', 1979, 'Douala', 'CM', 'maison-kamdem', 'indigo',
                'Premier rôle incontournable des séries camerounaises', "Figure des séries les plus suivies d'Afrique centrale, il alterne depuis quinze ans drames familiaux et polars urbains. Réputé pour la justesse de son jeu et sa rigueur sur les plateaux.",
                [['Les Héritiers', 2016, 'Paul Mbappé'], ['Wouri Blues', 2019, 'Inspecteur Ngando'], ['Le Fleuve des Promesses', 2025, 'Samuel']]],
            ['Grace', 'Adebayo', 'acteur', 'A1', 'femme', 1985, 'Lagos', 'NG', 'nollywood-bridge', 'violet',
                'Star de Nollywood, à l\'aise en français comme en anglais', "Actrice et productrice, elle enchaîne les succès au box-office nigérian depuis 2012 et s'ouvre aux coproductions francophones.",
                [['Lagos Nights', 2014, 'Adaeze'], ['The Wedding Planner of Ikoyi', 2018, 'Funke'], ['Two Rivers', 2023, 'Dr. Grace Okoro']]],
            ['Stéphane', 'Mvondo', 'acteur', 'B', 'homme', 1990, 'Yaoundé', 'CM', 'maison-kamdem', 'teal',
                'Révélé par un premier rôle viral', "Découvert grâce à la web-série « Mboa Stories », devenue phénomène en ligne, il enchaîne depuis les rôles au cinéma.",
                [['Mboa Stories', 2021, 'Junior'], ['Nuit Blanche à Bonapriso', 2023, 'Kévin']]],
            ['Fatou', 'Traoré', 'acteur', 'B', 'femme', 1993, 'Abidjan', 'CI', 'sahel-talents', 'crimson',
                'Vedette montante de la comédie ivoirienne', "Humoriste passée par le théâtre, elle s'est imposée dans la comédie avec un rôle de cheffe d'entreprise devenu culte.",
                [['Patronne !', 2020, 'Aminata Koné'], ['Maquis Palace', 2022, 'Tantie Rose']]],
            ['Olivier', 'Nkoulou', 'acteur', 'C', 'homme', 1987, 'Douala', 'CM', 'maison-kamdem', 'slate',
                'Comédien de théâtre et de télévision', "Formé au Centre culturel de Douala, il partage son temps entre la scène et les séries quotidiennes.",
                [['Le Quartier', 2018, 'Monsieur Essomba'], ['Les Héritiers', 2016, 'Maître Ondoa']]],
            ['Mireille', 'Tchatchoua', 'acteur', 'C', 'femme', 1991, 'Bafoussam', 'CM', 'maison-kamdem', 'emerald',
                'Comédienne et voix off', "Comédienne polyvalente, également voix off pour la publicité et le doublage.",
                [['Wouri Blues', 2019, 'Clarisse'], ['Mama Africa', 2022, 'Voix de Mama']]],
            ['Koffi', 'Mensah', 'acteur', 'C', 'homme', 1989, 'Lomé', 'TG', null, 'amber',
                'Acteur physique, cascades et combats chorégraphiés', "Ancien athlète, il s'est spécialisé dans les rôles d'action et encadre aussi les cascades.",
                [['La Route de Kpalimé', 2021, 'Kodjo'], ['Frontières', 2024, 'Lieutenant Adjo']]],
            ['Nadège', 'Ewane', 'acteur', 'D', 'femme', 2001, 'Yaoundé', 'CM', null, 'violet',
                'Jeune diplômée d\'école de cinéma', "Sortie en 2024 d'une formation en jeu d'acteur à Yaoundé, elle a tenu son premier rôle dans un court métrage primé.",
                [['Première Pluie', 2024, 'Rôle principal — Élise']]],
            ['Ibrahim', 'Sow', 'acteur', 'D', 'homme', 2002, 'Dakar', 'SN', null, 'teal',
                'Espoir du théâtre dakarois', "Issu d'une troupe de théâtre de quartier, il fait ses premiers pas devant la caméra.",
                [['Sable', 2025, 'Moussa']]],
        ];

        $technicians = [
            ['Jean-Baptiste', 'Ateba', 'realisateur', 'A2', 'homme', 1962, 'Yaoundé', 'CM', 'crew-connect', 'gold',
                'Réalisateur, référence du cinéma d\'Afrique centrale', "Auteur de films présentés dans les grands festivals, il a formé nombre de réalisateurs de la nouvelle génération.",
                [['Le Silence du Baobab', 1994, 'Réalisation'], ['La Dernière Reine', 2021, 'Réalisation']]],
            ['Sandrine', 'Abena', 'directeur-photo', 'A1', 'femme', 1981, 'Douala', 'CM', 'crew-connect', 'indigo',
                'Directrice de la photographie', "Signature visuelle de plusieurs séries à succès, elle travaille la lumière naturelle et les nuits urbaines.",
                [['Wouri Blues', 2019, 'Direction de la photographie'], ['Le Fleuve des Promesses', 2025, 'Direction de la photographie']]],
            ['Paul', 'Etoga', 'ingenieur-son', 'B', 'homme', 1984, 'Yaoundé', 'CM', 'crew-connect', 'teal',
                'Chef opérateur du son', "Prise de son en décors naturels et mixage ; il a assuré le son de plusieurs documentaires primés.",
                [['Les Gardiens du Mont Cameroun', 2023, 'Prise de son']]],
            ['Linda', 'Okonkwo', 'monteur', 'B', 'femme', 1988, 'Lagos', 'NG', 'nollywood-bridge', 'violet',
                'Cheffe monteuse', "Monteuse de longs métrages et de séries, reconnue pour son sens du rythme.",
                [['Two Rivers', 2023, 'Montage'], ['Lagos Nights', 2014, 'Montage']]],
            ['Hervé', 'Nana', 'compositeur', 'B', 'homme', 1983, 'Douala', 'CM', null, 'amber',
                'Compositeur de musiques de films', "Il mêle makossa, orchestre et électronique dans des bandes originales remarquées.",
                [['Wouri Blues', 2019, 'Musique originale'], ['Mboa Stories', 2021, 'Générique']]],
            ['Clarisse', 'Fouda', 'chef-decorateur', 'C', 'femme', 1986, 'Yaoundé', 'CM', 'crew-connect', 'emerald',
                'Cheffe décoratrice', "Décors d'époque et intérieurs contemporains pour le cinéma et la publicité.",
                [['La Dernière Reine', 2021, 'Décors']]],
            ['Aminata', 'Coulibaly', 'costumier', 'C', 'femme', 1990, 'Bamako', 'ML', 'sahel-talents', 'crimson',
                'Créatrice de costumes', "Costumes historiques et contemporains, avec un travail remarqué sur le bogolan.",
                [['La Dernière Reine', 2021, 'Costumes']]],
            ['Serge', 'Owona', 'maquilleur', 'C', 'homme', 1992, 'Douala', 'CM', null, 'slate',
                'Maquillage et effets de maquillage', "Maquillage beauté, vieillissement et prothèses pour le cinéma.",
                [['Frontières', 2024, 'Maquillage']]],
            ['Kevin', 'Tabi', 'effets-speciaux', 'D', 'homme', 1999, 'Buea', 'CM', null, 'indigo',
                'Superviseur effets visuels', "Diplômé en animation 3D, il assure compositing et effets numériques pour les productions indépendantes.",
                [['Frontières', 2024, 'Effets visuels']]],
            ['Brenda', 'Achu', 'scripte', 'D', 'femme', 2000, 'Bamenda', 'CM', null, 'teal',
                'Scripte', "Jeune scripte formée sur des séries quotidiennes, rigoureuse sur la continuité.",
                [['Le Quartier', 2023, 'Scripte']]],
        ];

        foreach ([...$actors, ...$technicians] as $i => $row) {
            [$first, $last, $profession, $tier, $gender, $year, $city, $country, $agentSlug, $palette, $headline, $bio, $credits] = $row;
            $slug = \Illuminate\Support\Str::slug("{$first} {$last}");

            $talent = Talent::updateOrCreate(['slug' => $slug], [
                'kind' => $profession === 'acteur' ? 'acteur' : 'technicien',
                'first_name' => $first,
                'last_name' => $last,
                'tier' => $tier,
                'profession' => $profession,
                'gender' => $gender,
                'birth_year' => $year,
                'country_code' => $country,
                'city' => $city,
                'headline' => $headline,
                'bio' => $bio,
                'photo_path' => $this->assets->portrait("{$first} {$last}", $slug, $palette),
                'playing_age_min' => $profession === 'acteur' ? max(16, (int) now()->year - $year - 8) : null,
                'playing_age_max' => $profession === 'acteur' ? (int) now()->year - $year + 5 : null,
                'height_cm' => $profession === 'acteur' ? ($gender === 'femme' ? 168 : 182) : null,
                'languages' => $country === 'NG' ? ['Anglais', 'Français', 'Yoruba'] : ['Français', 'Anglais'],
                'skills' => $profession === 'acteur'
                    ? ['Improvisation', 'Chant', 'Danse traditionnelle']
                    : ['Tournage en décors naturels', 'Encadrement d\'équipe'],
                'showreel_url' => null,
                'agent_id' => $agentSlug ? ($agents[$agentSlug]->id ?? null) : null,
                'is_featured' => in_array($tier, ['A2', 'A1'], true),
                'is_published' => true,
            ]);

            $talent->credits()->delete();
            foreach ($credits as $order => [$title, $creditYear, $role]) {
                TalentCredit::create([
                    'talent_id' => $talent->id,
                    'title' => $title,
                    'year' => $creditYear,
                    'role' => $role,
                    'sort_order' => $order,
                ]);
            }

            $this->talents[$slug] = $talent;
        }
    }

    // ------------------------------------------------------------------
    //  Casting
    // ------------------------------------------------------------------

    private function castingCalls(): void
    {
        $calls = [
            [
                'slug' => 'casting-les-heritiers-saison-3',
                'title' => 'Série « Les Héritiers » — saison 3',
                'project_title' => 'Les Héritiers',
                'project_type' => 'serie',
                'production_company' => 'Wouri Films Production',
                'director' => 'Jean-Baptiste Ateba',
                'description' => "La saga familiale la plus suivie du Cameroun revient pour une troisième saison de 26 épisodes de 52 minutes. Nous recherchons de nouveaux visages pour la famille Mbappé et son entourage. Tournage à Douala et Kribi.",
                'city' => 'Douala', 'country_code' => 'CM',
                'shooting_starts_on' => '2026-11-16', 'shooting_ends_on' => '2027-02-28',
                'deadline_at' => '2026-10-25 23:59:00',
                'compensation' => 'remunere', 'compensation_details' => 'Cachet selon expérience, défraiement transport',
                'status' => 'open', 'is_featured' => true, 'palette' => 'gold',
                'roles' => [
                    ['Nadia Mbappé', 'acteur', null, 'principal', 'femme', 22, 30, 'C', "Benjamine de la famille, étudiante en droit rentrée de Paris, déterminée à reprendre l'entreprise familiale.", 'Français courant ; anglais apprécié.', 1],
                    ['Maître Ekwalla', 'acteur', null, 'secondaire', 'homme', 45, 60, 'B', "Avocat de la famille, charismatique et ambigu.", null, 1],
                    ['Serveurs du restaurant « Le Wouri »', 'acteur', null, 'figuration', 'indifferent', 18, 45, null, 'Figuration récurrente sur 6 jours de tournage.', null, 8],
                    ['1er assistant opérateur', 'technicien', 'cadreur', null, 'indifferent', null, null, 'C', 'Assistant caméra pour une configuration à deux caméras.', 'Expérience ARRI ou Sony FX9.', 1],
                ],
            ],
            [
                'slug' => 'casting-le-fleuve-des-promesses',
                'title' => 'Long métrage « Le Fleuve des Promesses »',
                'project_title' => 'Le Fleuve des Promesses',
                'project_type' => 'film',
                'production_company' => 'Sanaga Pictures',
                'director' => 'Sandrine Abena',
                'description' => "Drame familial le long de la Sanaga : trois générations de pêcheurs face à la construction d'un barrage. Tournage de 7 semaines entre Édéa et Yaoundé.",
                'city' => 'Édéa', 'country_code' => 'CM',
                'shooting_starts_on' => '2027-01-11', 'shooting_ends_on' => '2027-02-26',
                'deadline_at' => '2026-11-15 23:59:00',
                'compensation' => 'remunere', 'compensation_details' => 'Cachets conventionnés, hébergement pris en charge',
                'status' => 'open', 'is_featured' => true, 'palette' => 'teal',
                'roles' => [
                    ['Samuel (jeune)', 'acteur', null, 'principal', 'homme', 12, 15, null, "Fils de pêcheur, curieux et têtu. Le rôle demande de savoir nager.", 'Autorisation parentale obligatoire.', 1],
                    ['Grand-mère Ngo Biyong', 'acteur', null, 'secondaire', 'femme', 60, 80, 'C', "Gardienne des traditions du village, douce mais inflexible.", 'Bassa parlé souhaité.', 1],
                    ['Ingénieur du son', 'technicien', 'ingenieur-son', null, 'indifferent', null, null, 'B', 'Prise de son en extérieur, milieu fluvial.', null, 1],
                ],
            ],
            [
                'slug' => 'casting-publicite-reseau-mobile',
                'title' => 'Publicité nationale — opérateur mobile',
                'project_title' => 'Campagne « Toujours connectés »',
                'project_type' => 'publicite',
                'production_company' => 'Studio Kamer Pub',
                'director' => null,
                'description' => 'Tournage de trois spots TV et réseaux sociaux. Familles, étudiants et commerçants de marché.',
                'city' => 'Yaoundé', 'country_code' => 'CM',
                'shooting_starts_on' => '2026-10-20', 'shooting_ends_on' => '2026-10-23',
                'deadline_at' => '2026-10-12 18:00:00',
                'compensation' => 'remunere', 'compensation_details' => 'Forfait journalier + droits de diffusion 2 ans',
                'status' => 'open', 'is_featured' => false, 'palette' => 'amber',
                'roles' => [
                    ['Mère de famille', 'acteur', null, 'principal', 'femme', 30, 42, null, 'Chaleureuse et naturelle face caméra.', null, 1],
                    ['Étudiants', 'acteur', null, 'secondaire', 'indifferent', 18, 25, null, 'Groupe de quatre amis.', null, 4],
                ],
            ],
            [
                'slug' => 'casting-nuit-blanche-bonapriso',
                'title' => 'Court métrage « Nuit Blanche à Bonapriso »',
                'project_title' => 'Nuit Blanche à Bonapriso',
                'project_type' => 'court-metrage',
                'production_company' => 'Collectif Lumière 237',
                'director' => 'Stéphane Mvondo',
                'description' => "Comédie nocturne de 18 minutes : une livraison qui tourne mal pendant une panne d'électricité.",
                'city' => 'Douala', 'country_code' => 'CM',
                'shooting_starts_on' => '2026-08-01', 'shooting_ends_on' => '2026-08-05',
                'deadline_at' => '2026-07-15 23:59:00',
                'compensation' => 'non-remunere', 'compensation_details' => 'Repas et transport pris en charge',
                'status' => 'closed', 'is_featured' => false, 'palette' => 'violet',
                'roles' => [
                    ['Le livreur', 'acteur', null, 'principal', 'homme', 20, 30, 'D', 'Débrouillard et bavard.', null, 1],
                ],
            ],
        ];

        foreach ($calls as $data) {
            $roles = $data['roles'];
            $palette = $data['palette'];
            unset($data['roles'], $data['palette']);

            $call = CastingCall::updateOrCreate(['slug' => $data['slug']], $data + [
                'cover_path' => $this->assets->cover($data['project_title'], $data['slug'], 'casting', $palette),
                'contact_email' => 'casting@abbev.demo',
                'published_at' => now()->subDays(6),
            ]);

            $call->roles()->delete();
            foreach ($roles as $order => [$name, $kind, $profession, $importance, $gender, $ageMin, $ageMax, $minTier, $description, $requirements, $positions]) {
                CastingRole::create([
                    'casting_call_id' => $call->id,
                    'name' => $name,
                    'kind' => $kind,
                    'profession' => $profession,
                    'importance' => $importance,
                    'gender' => $gender,
                    'age_min' => $ageMin,
                    'age_max' => $ageMax,
                    'min_tier' => $minTier,
                    'description' => $description,
                    'requirements' => $requirements,
                    'positions' => $positions,
                    'sort_order' => $order,
                ]);
            }
        }
    }

    // ------------------------------------------------------------------
    //  Cours de cinéma
    // ------------------------------------------------------------------

    private function courses(): void
    {
        $sample = 'https://test-streams.mux.dev/x36xhzz/x36xhzz.m3u8';

        $videoCourses = [
            ['les-fondamentaux-de-la-realisation', 'Les fondamentaux de la réalisation', 'realisation', 'debutant', 'Jean-Baptiste Ateba', 'Réalisateur, 30 ans de carrière', 'gold', null,
                "Du découpage technique à la direction d'acteurs : les bases pour réaliser votre premier film.",
                ['Penser en images', 'Le découpage technique', "L'échelle des plans", 'Diriger les comédiens', 'Tourner une scène de dialogue', 'Du tournage au montage']],
            ['jouer-face-camera', 'Jouer face caméra', 'jeu', 'intermediaire', 'Awa Ndiaye', 'Actrice, légende du cinéma ouest-africain', 'crimson', 'classique',
                'Passer de la scène à l\'écran : justesse, regard, continuité et travail avec la caméra.',
                ['La caméra, premier partenaire', 'Le regard et le silence', 'Tenir un personnage sur la durée', 'Le casting : se préparer']],
            ['la-lumiere-naturelle', 'La lumière naturelle', 'image', 'intermediaire', 'Sandrine Abena', 'Directrice de la photographie', 'teal', 'standard',
                "Tirer parti du soleil, de l'ombre et des nuits africaines avec un matériel léger.",
                ['Lire la lumière du jour', 'Les heures dorées', 'Réflecteurs et diffuseurs', 'Tourner la nuit en ville']],
            ['monter-une-scene', 'Monter une scène de dialogue', 'montage', 'avance', 'Linda Okonkwo', 'Cheffe monteuse', 'violet', 'premium',
                'Rythme, raccords et émotion : la méthode d\'une monteuse de longs métrages.',
                ['Dérusher efficacement', 'Champ-contrechamp', 'Le rythme d\'une scène', 'Le son au montage']],
        ];

        foreach ($videoCourses as $order => [$slug, $title, $discipline, $level, $instructor, $instructorTitle, $palette, $tier, $summary, $lessons]) {
            $course = Course::updateOrCreate(['slug' => $slug], [
                'title' => $title,
                'type' => 'video',
                'discipline' => $discipline,
                'level' => $level,
                'instructor_name' => $instructor,
                'instructor_title' => $instructorTitle,
                'summary' => $summary,
                'description' => $summary . "\n\nChaque leçon s'appuie sur des exemples tournés en conditions réelles et se conclut par un exercice pratique.",
                'cover_path' => $this->assets->cover($title, $slug, 'courses', $palette),
                'required_tier' => $tier,
                'is_published' => true,
                'sort_order' => $order,
            ]);

            $course->lessons()->delete();
            foreach ($lessons as $i => $lessonTitle) {
                CourseLesson::create([
                    'course_id' => $course->id,
                    'title' => $lessonTitle,
                    'summary' => null,
                    'sort_order' => $i + 1,
                    'is_preview' => $i === 0,
                    'video_provider' => 'url',
                    'video_url' => $sample,
                    'duration_minutes' => 8 + ($i * 3) % 11,
                ]);
            }
        }

        $documentCourses = [
            ['ecrire-son-premier-court-metrage', 'Écrire son premier court métrage', 'scenario', 'debutant', 'Hervé Nana', 'Scénariste et compositeur', 'emerald', null,
                "De l'idée au scénario prêt à tourner : méthode, structure et exemples commentés.",
                [
                    ['Trouver son idée', [['Partir d\'une image', "Un court métrage naît souvent d'une image forte ou d'une situation simple. Notez chaque jour une situation observée dans la rue, au marché, dans un taxi : c'est votre réserve d'idées."], ['Le pitch en une phrase', "Résumez votre histoire en une phrase : un personnage, un désir, un obstacle. Si vous n'y arrivez pas, l'histoire n'est pas encore claire."]]],
                    ['Structurer le récit', [['Trois actes, même en dix minutes', "Exposition, confrontation, résolution : la structure reste utile dans un format court. Comptez environ une page de scénario par minute de film."], ['Couper ce qui ne sert pas', "Chaque scène doit faire avancer le personnage ou l'intrigue. Relisez en vous demandant : que perd-on si on enlève cette scène ?"]]],
                    ['Mettre en forme', [['Le format professionnel', "Police à chasse fixe, intitulés de séquence (INT./EXT., lieu, moment), dialogues centrés : un format standard facilite la lecture des producteurs."], ['Relire et faire lire', "Faites lire votre scénario à voix haute par des comédiens : les répliques qui sonnent faux s'entendent immédiatement."]]],
                ]],
            ['produire-a-petit-budget', 'Produire un film à petit budget', 'production', 'intermediaire', 'Rose Kamdem', 'Productrice et agente', 'amber', 'classique',
                'Budget, planning, autorisations et financement : produire sans se ruiner.',
                [
                    ['Le budget', [['Poste par poste', "Listez chaque poste (équipe, matériel, décors, transport, repas, post-production) avant de chercher le moindre financement. Prévoyez 10 % d'imprévus."]]],
                    ['Le plan de travail', [['Regrouper par décor', "Tournez toutes les scènes d'un même décor d'affilée, quel que soit leur ordre dans le film : c'est la première source d'économie."]]],
                    ['Financer', [['Combiner les sources', "Aides publiques, partenaires privés, préventes et financement participatif se complètent : un appel à financement sur ABBEV peut compléter votre plan."]]],
                ]],
        ];

        foreach ($documentCourses as $order => [$slug, $title, $discipline, $level, $instructor, $instructorTitle, $palette, $tier, $summary, $chapters]) {
            $course = Course::updateOrCreate(['slug' => $slug], [
                'title' => $title,
                'type' => 'document',
                'discipline' => $discipline,
                'level' => $level,
                'instructor_name' => $instructor,
                'instructor_title' => $instructorTitle,
                'summary' => $summary,
                'description' => $summary . "\n\nChaque chapitre se lit dans l'app, sans téléchargement.",
                'cover_path' => $this->assets->cover($title, $slug, 'courses', $palette),
                'required_tier' => $tier,
                'is_published' => true,
                'sort_order' => 10 + $order,
            ]);

            $course->lessons()->delete();
            foreach ($chapters as $i => [$chapterTitle, $sections]) {
                $pdf = $this->assets->pdf("demo/courses/{$slug}-" . ($i + 1) . '.pdf', $chapterTitle, $sections, 'ABBEV — Cours de cinéma');
                CourseLesson::create([
                    'course_id' => $course->id,
                    'title' => $chapterTitle,
                    'sort_order' => $i + 1,
                    'is_preview' => $i === 0,
                    'file_path' => $pdf['path'],
                    'pages' => $pdf['pages'],
                ]);
            }
        }
    }

    // ------------------------------------------------------------------
    //  Appels à projets
    // ------------------------------------------------------------------

    private function projectCalls(): void
    {
        $rewards = [
            ['amount' => 5000, 'title' => 'Merci au générique', 'description' => 'Votre nom au générique de fin.'],
            ['amount' => 25000, 'title' => 'Affiche dédicacée', 'description' => "L'affiche officielle signée par l'équipe + générique."],
            ['amount' => 100000, 'title' => 'Invitation à l\'avant-première', 'description' => 'Deux places pour l\'avant-première à Douala.'],
            ['amount' => 500000, 'title' => 'Journée sur le tournage', 'description' => 'Une journée sur le plateau avec l\'équipe (transport non inclus).'],
        ];

        $calls = [
            ['financement', 'film', 'financement-le-fleuve-des-promesses', 'Le Fleuve des Promesses', 'Sanaga Pictures', 'teal',
                "Long métrage de fiction : trois générations de pêcheurs face à la construction d'un barrage sur la Sanaga.",
                "Le film est écrit, casté et soutenu par un premier partenaire. Il nous manque la part nécessaire au tournage de 7 semaines et à la post-production. Chaque contribution rapproche le film de l'écran.",
                ['goal_amount' => 45000000, 'min_pledge' => 5000, 'raised_offline' => 18500000, 'rewards' => $rewards],
                '2026-12-15 23:59:00'],
            ['financement', 'serie', 'financement-mboa-stories-saison-2', 'Mboa Stories — saison 2', 'Collectif Lumière 237', 'violet',
                'Web-série culte de la jeunesse de Douala : 10 épisodes de 12 minutes pour la saison 2.',
                "Après 4 millions de vues en ligne, la bande de Junior revient. Votre soutien finance le tournage et la musique originale.",
                ['goal_amount' => 18000000, 'min_pledge' => 2000, 'raised_offline' => 6200000, 'rewards' => array_slice($rewards, 0, 3)],
                '2026-11-30 23:59:00'],
            ['financement', 'documentaire', 'financement-les-gardiens-du-mont-cameroun', 'Les Gardiens du Mont Cameroun', 'Tropiques Docs', 'emerald',
                'Documentaire de 52 minutes sur les porteurs et guides du mont Cameroun.',
                "Une immersion d'une saison avec les guides qui accompagnent chaque année la course de l'Espoir.",
                ['goal_amount' => 12000000, 'min_pledge' => 5000, 'raised_offline' => 3000000, 'rewards' => array_slice($rewards, 0, 2)],
                '2027-01-31 23:59:00'],
            ['ecriture', 'film', 'appel-scenarios-premier-long-2027', 'Premier long métrage 2027', 'ABBEV Studios', 'gold',
                "Appel à scénarios de premier long métrage : le lauréat est accompagné jusqu'au tournage.",
                "ABBEV Studios recherche des scénarios de premier long métrage ancrés en Afrique, tous genres confondus. Le lauréat bénéficie d'un accompagnement à l'écriture, d'une dotation et d'une mise en relation avec des producteurs.",
                ['requirements' => "Pitch (3 lignes), synopsis (1 page), note d'intention, scénario complet en PDF (90 à 120 pages).", 'prize' => 'Dotation de 2 000 000 FCFA + résidence d\'écriture', 'genre' => 'Tous genres', 'max_pages' => 120],
                '2026-12-31 23:59:00'],
            ['ecriture', 'serie', 'appel-feuilleton-quotidien', 'Feuilleton quotidien 26 minutes', 'Wouri Films Production', 'crimson',
                'Concept et premiers épisodes d\'un feuilleton quotidien pour la télévision.',
                "Nous cherchons un concept de feuilleton quotidien familial et urbain, avec une bible de série et les deux premiers épisodes dialogués.",
                ['requirements' => "Bible de série (10 pages max) et épisodes 1 et 2 dialogués.", 'prize' => "Contrat d'écriture pour la saison 1", 'genre' => 'Drame familial / comédie', 'max_pages' => 60],
                '2026-11-20 23:59:00'],
            ['ecriture', 'documentaire', 'appel-documentaires-memoires', 'Mémoires de nos villes', 'Tropiques Docs', 'slate',
                'Projets de documentaires sur la mémoire des quartiers africains.',
                'Un appel ouvert aux auteurs de documentaires : racontez l\'histoire d\'un quartier, d\'un marché, d\'une gare.',
                ['requirements' => 'Note d\'intention, synopsis, repérages (photos) en PDF.', 'prize' => 'Coproduction et diffusion sur ABBEV', 'genre' => 'Documentaire', 'max_pages' => 30],
                '2027-02-15 23:59:00'],
            ['musique', 'cinema', 'appel-musique-bo-fleuve', 'Bande originale « Le Fleuve des Promesses »', 'Sanaga Pictures', 'amber',
                'Compositions originales pour la bande originale du film.',
                "Nous recherchons un thème principal et deux motifs secondaires, entre orchestre, instruments traditionnels (mvet, balafon) et textures électroniques.",
                ['requirements' => "Lien d'écoute (SoundCloud, Drive…) vers 2 à 3 maquettes, courte présentation.", 'prize' => 'Commande de la bande originale complète', 'music_style' => 'Afro-orchestral, mvet, balafon', 'max_duration_minutes' => 4],
                '2026-12-10 23:59:00'],
            ['musique', 'television', 'appel-generique-mboa-stories', 'Générique de « Mboa Stories »', 'Collectif Lumière 237', 'indigo',
                'Le générique de la saison 2, entre afrobeat et makossa.',
                'Un générique de 45 à 60 secondes, énergique et urbain, déclinable en version courte de 15 secondes.',
                ['requirements' => "Lien d'écoute vers une maquette du générique.", 'prize' => '500 000 FCFA + crédit au générique', 'music_style' => 'Afrobeat, makossa', 'max_duration_minutes' => 1],
                '2026-10-31 23:59:00'],
        ];

        foreach ($calls as $order => [$type, $target, $slug, $title, $organizer, $palette, $summary, $description, $specific, $closesAt]) {
            ProjectCall::updateOrCreate(['slug' => $slug], $specific + [
                'type' => $type,
                'target' => $target,
                'title' => $title,
                'summary' => $summary,
                'description' => $description,
                'organizer' => $organizer,
                'cover_path' => $this->assets->cover($title, $slug, 'calls', $palette),
                'currency' => 'XAF',
                'opens_at' => now()->subDays(20),
                'closes_at' => $closesAt,
                'status' => 'open',
                'is_featured' => $order < 2,
                'published_at' => now()->subDays(20),
            ]);
        }

        // Quelques soutiens confirmés et candidatures, pour donner vie aux
        // compteurs de l'app et aux tableaux de l'admin.
        $backers = $this->demoUsers(8);
        $fleuve = ProjectCall::where('slug', 'financement-le-fleuve-des-promesses')->first();
        foreach ($backers as $i => $user) {
            ProjectPledge::updateOrCreate(
                ['project_call_id' => $fleuve->id, 'user_id' => $user->id],
                [
                    'amount' => [25000, 100000, 5000, 500000, 25000, 5000, 100000, 50000][$i],
                    'currency' => 'XAF',
                    'reward_title' => [$rewards[1]['title'], $rewards[2]['title'], $rewards[0]['title'], $rewards[3]['title'], $rewards[1]['title'], $rewards[0]['title'], $rewards[2]['title'], null][$i],
                    'phone' => '+237 6 77 00 00 ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                    'status' => $i < 6 ? 'confirmed' : 'pending',
                    'confirmed_at' => $i < 6 ? now()->subDays(10 - $i) : null,
                ]
            );
        }

        $scenario = ProjectCall::where('slug', 'appel-scenarios-premier-long-2027')->first();
        foreach (array_slice($backers, 0, 3) as $i => $user) {
            ProjectSubmission::updateOrCreate(
                ['project_call_id' => $scenario->id, 'user_id' => $user->id],
                [
                    'title' => ['La Saison des Mangues', 'Le Dernier Train pour Kumba', 'Poto-Poto'][$i],
                    'logline' => ["Une adolescente de Bafia défie sa famille pour participer à un concours de cuisine télévisé.", "Deux frères brouillés doivent convoyer le cercueil de leur père jusqu'à Kumba.", "Dans un quartier inondé de Douala, une sage-femme organise l'évacuation."][$i],
                    'synopsis' => 'Synopsis de démonstration.',
                    'status' => ['received', 'shortlisted', 'received'][$i],
                ]
            );
        }
    }

    // ------------------------------------------------------------------
    //  Lions Head Awards
    // ------------------------------------------------------------------

    private function awards(): void
    {
        $edition = AwardEdition::updateOrCreate(['slug' => 'lions-head-awards-2026'], [
            'name' => 'Lions Head Awards 2026',
            'year' => 2026,
            'tagline' => 'Le public couronne le meilleur du cinéma et de la télévision',
            'description' => "Pour cette édition, le public vote dans les 29 catégories des Lions Head Awards : cinéma, télévision et métiers. Un vote par catégorie, modifiable jusqu'à la clôture. Les lauréats seront dévoilés lors de la cérémonie à Yaoundé.",
            'cover_path' => $this->assets->cover('Lions Head Awards 2026', 'lions-head-awards-2026', 'awards', 'gold'),
            // Heures locales (Yaoundé), comme l'équipe les saisirait dans l'admin.
            'voting_starts_at' => '2026-09-15 00:00:00',
            'voting_ends_at' => '2026-11-15 23:59:00',
            'ceremony_at' => '2026-12-12 19:00:00',
            'ceremony_venue' => 'Palais des Congrès, Yaoundé',
            'results_published_at' => null,
        ]);
        $edition->setTranslation('tagline', 'en', 'The audience crowns the best of film and television');
        $edition->setTranslation('description', 'en', 'For this edition, the public votes in the 29 Lions Head Awards categories: film, television and craft. One vote per category, which can be changed until voting closes. Winners will be revealed at the ceremony in Yaoundé.');
        $edition->makeCurrent();

        AwardCatalog::applyTo($edition);

        $films = Media::where('type', 'movie')->published()
            ->whereIn('title', ['Parasite', 'Everything Everywhere All at Once', 'Oppenheimer', 'Coco', 'The Intouchables'])
            ->get();
        $series = Media::where('type', 'series')->published()
            ->whereIn('title', ['The Bear', 'Severance', 'The Last of Us', 'The Crown', 'Dark'])
            ->get();

        $byProfession = collect($this->talents)->groupBy('profession');
        $actors = collect($this->talents)->where('kind', 'acteur');
        $works = ['Le Fleuve des Promesses', 'Wouri Blues', 'Les Héritiers', 'La Dernière Reine', 'Two Rivers', 'Frontières'];

        foreach ($edition->categories()->get() as $category) {
            $category->nominees()->delete();

            if ($category->nominee_type === 'media') {
                $pool = $category->scope === 'television' ? $series : $films;
                foreach ($pool->values() as $i => $media) {
                    AwardNominee::create([
                        'award_category_id' => $category->id,
                        'media_id' => $media->id,
                        'name' => $media->title,
                        'subtitle' => $media->release_year ? (string) $media->release_year : null,
                        'sort_order' => $i,
                    ]);
                }

                continue;
            }

            $pool = $this->nomineePool($category, $actors, $byProfession);
            foreach ($pool->values() as $i => $talent) {
                AwardNominee::create([
                    'award_category_id' => $category->id,
                    'talent_id' => $talent->id,
                    'name' => $talent->displayName(),
                    // L'œuvre seule : l'app l'affiche avec une icône, sans préposition à traduire.
                    'subtitle' => $works[($i + $category->id) % count($works)],
                    'sort_order' => $i,
                ]);
            }
        }

        // Votes de démonstration : l'admin voit ses tableaux de résultats
        // vivants ; le public, lui, ne verra rien avant la publication.
        $voting = app(AwardVotingService::class);
        $voters = $this->demoUsers(12);
        foreach ($edition->categories()->with('nominees')->get() as $category) {
            $nominees = $category->nominees->values();
            if ($nominees->isEmpty()) {
                continue;
            }
            // Répartition déterministe mais inégale (un favori, des
            // poursuivants), décalée d'une catégorie à l'autre.
            $spread = [0, 0, 0, 1, 2, 1, 0, 3, 1, 0, 2, 0];
            foreach ($voters as $v => $voter) {
                $pick = $nominees[($spread[$v % count($spread)] + $category->id) % $nominees->count()];
                $voting->cast($voter, $pick, '127.0.0.1');
            }
        }
    }

    private function nomineePool(AwardCategory $category, $actors, $byProfession)
    {
        $slug = $category->slug;
        $gender = str_contains($slug, 'actrice') || str_contains($slug, 'feminin') || str_contains($slug, 'feminine') ? 'femme'
            : (str_contains($slug, 'acteur') || str_contains($slug, 'masculin') ? 'homme' : null);

        if (str_contains($slug, 'revelation')) {
            return $actors->where('gender', $gender)->whereIn('tier', ['B', 'C', 'D'])->take(4);
        }
        if ($gender !== null) {
            return $actors->where('gender', $gender)->take(4);
        }

        $profession = match (true) {
            str_contains($slug, 'realisateur') => 'realisateur',
            str_contains($slug, 'photo') => 'directeur-photo',
            str_contains($slug, 'son') => 'ingenieur-son',
            str_contains($slug, 'montage') => 'monteur',
            str_contains($slug, 'musique') => 'compositeur',
            str_contains($slug, 'decors') => 'chef-decorateur',
            str_contains($slug, 'costumes') => 'costumier',
            str_contains($slug, 'effets') => 'effets-speciaux',
            str_contains($slug, 'maquillage') => 'maquilleur',
            default => null,
        };

        // Un seul talent démo par métier : on complète avec d'autres
        // techniciens pour que chaque prix compte plusieurs nommés.
        $own = collect($byProfession->get($profession, []));
        $others = collect($this->talents)
            ->where('kind', 'technicien')
            ->reject(fn ($t) => $t->profession === $profession)
            ->values();

        return $own->concat($others->slice($category->id % max(1, $others->count()), 3))->take(4);
    }

    // ------------------------------------------------------------------
    //  Billetterie : séances à venir et codes cinéma
    // ------------------------------------------------------------------

    private function ticketing(): void
    {
        // Horaires de salle en heure locale (20h à Douala).
        $base = BusinessTime::now()->startOfDay()->addDays(3);

        $screenings = [
            ['Dune', 'Canal Olympia Bessengue', 'Bessengue, Douala', 0, '20:00', [['Standard', 3000, 120], ['VIP', 6000, 30]]],
            ['Coco', 'Canal Olympia Yaoundé', 'Carrefour Warda, Yaoundé', 2, '15:00', [['Standard', 2500, 150], ['Enfant (moins de 12 ans)', 1500, 60]]],
            ['Oppenheimer', 'Cinéma ABBEV Akwa', 'Boulevard de la Liberté, Douala', 5, '19:30', [['Standard', 3500, 90], ['VIP', 7000, 20]]],
            ['Avant-première : Le Fleuve des Promesses', 'Palais des Congrès', 'Tsinga, Yaoundé', 12, '18:00', [['Invitation grand public', 5000, 300], ['Carré or', 15000, 40]]],
        ];

        foreach ($screenings as [$title, $cinema, $location, $days, $time, $tickets]) {
            $media = Media::where('title', $title)->first();
            $screening = Screening::updateOrCreate(
                ['kind' => 'seance', 'movie_title' => $title, 'cinema_name' => $cinema],
                [
                    'media_id' => $media?->id,
                    'location' => $location,
                    'country_code' => 'CM',
                    'starts_at' => $base->copy()->addDays($days)->setTimeFromTimeString($time),
                    'valid_until' => null,
                    'status' => 'published',
                ]
            );
            $this->syncTickets($screening, $tickets);
        }

        $codes = [
            ['Code cinéma — 1 entrée', 'Canal Olympia Bessengue', 'Bessengue, Douala', 90, [['Entrée 2D', 2500, 500], ['Entrée VIP', 5000, 100]]],
            ['Code cinéma — Week-end', 'Cinéma ABBEV Akwa', 'Boulevard de la Liberté, Douala', 120, [['Entrée week-end', 3000, 200]]],
            ['Code cinéma — Étudiant', 'Canal Olympia Yaoundé', 'Carrefour Warda, Yaoundé', 60, [['Entrée étudiant', 1500, 300]]],
        ];

        foreach ($codes as [$title, $cinema, $location, $validDays, $tickets]) {
            $screening = Screening::updateOrCreate(
                ['kind' => 'code', 'movie_title' => $title, 'cinema_name' => $cinema],
                [
                    'location' => $location,
                    'country_code' => 'CM',
                    'starts_at' => BusinessTime::now()->subDay()->startOfDay(),
                    'valid_until' => BusinessTime::now()->addDays($validDays)->endOfDay(),
                    'status' => 'published',
                ]
            );
            $this->syncTickets($screening, $tickets);
        }
    }

    /**
     * Un billet = une personne : le contrôle à l'entrée valide une entrée par
     * billet acheté, d'où l'absence de formules « duo » ou « famille ».
     */
    private function syncTickets(Screening $screening, array $tickets): void
    {
        $english = [
            'Enfant (moins de 12 ans)' => 'Child (under 12)',
            'Invitation grand public' => 'General admission',
            'Carré or' => 'Gold circle',
            'Entrée 2D' => '2D admission',
            'Entrée VIP' => 'VIP admission',
            'Entrée week-end' => 'Weekend admission',
            'Entrée étudiant' => 'Student admission',
        ];

        foreach ($tickets as [$name, $price, $capacity]) {
            $type = TicketType::updateOrCreate(
                ['screening_id' => $screening->id, 'name' => $name],
                ['price' => $price, 'currency' => 'XAF', 'capacity' => $capacity]
            );
            if (isset($english[$name])) {
                $type->setTranslation('name', 'en', $english[$name]);
            }
        }
    }

    // ------------------------------------------------------------------

    /** @return list<User> Comptes de démonstration (votants, soutiens…). */
    private function demoUsers(int $count): array
    {
        $users = [];
        for ($i = 1; $i <= $count; $i++) {
            $users[] = User::firstOrCreate(
                ['email' => sprintf('demo.public%02d@abbev.test', $i)],
                [
                    'name' => sprintf('Public démo %02d', $i),
                    'password' => Hash::make(\Illuminate\Support\Str::random(24)),
                    'role' => 'user',
                    'is_active' => true,
                    'country_code' => 'CM',
                    'currency_code' => 'XAF',
                ]
            );
        }

        return $users;
    }
}
