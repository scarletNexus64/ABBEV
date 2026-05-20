<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Category;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Catégories principales ABBEV
        $mainCategories = [
            [
                'name' => 'Films',
                'slug' => 'films',
                'description' => 'Catalogue de films - Tous genres confondus',
            ],
            [
                'name' => 'Séries',
                'slug' => 'series',
                'description' => 'Catalogue de séries TV et web-séries',
            ],
            [
                'name' => 'Réservation Cinéma',
                'slug' => 'reservation-cinema',
                'description' => 'Réservez vos tickets de cinéma dans les salles partenaires à travers le monde',
            ],
            [
                'name' => 'Cours de Cinéma',
                'slug' => 'cours-cinema',
                'description' => 'Formation en ligne dispensée par des professionnels et vedettes du cinéma',
            ],
            [
                'name' => 'Financement de Projets',
                'slug' => 'financement-projets',
                'description' => 'Plateforme de financement participatif pour vos projets de films et scénarios',
            ],
            [
                'name' => 'Lions Head Awards',
                'slug' => 'lions-head-awards',
                'description' => 'Votez pour vos acteurs et films préférés de manière transparente',
            ],
        ];

        foreach ($mainCategories as $category) {
            Category::firstOrCreate(['slug' => $category['slug']], $category);
        }

        // Genres de Films et Séries
        $genres = [
            [
                'name' => 'Action',
                'slug' => 'action',
                'description' => 'Films et séries d\'action avec scènes d\'intensité, combats et poursuites',
            ],
            [
                'name' => 'Comédie',
                'slug' => 'comedie',
                'description' => 'Films et séries humoristiques pour vous faire rire',
            ],
            [
                'name' => 'Drame',
                'slug' => 'drame',
                'description' => 'Histoires dramatiques et émotionnelles',
            ],
            [
                'name' => 'Horreur',
                'slug' => 'horreur',
                'description' => 'Films et séries d\'épouvante et de terreur',
            ],
            [
                'name' => 'Science-Fiction',
                'slug' => 'science-fiction',
                'description' => 'Univers futuristes, technologies avancées et mondes imaginaires',
            ],
            [
                'name' => 'Romance',
                'slug' => 'romance',
                'description' => 'Histoires d\'amour et relations romantiques',
            ],
            [
                'name' => 'Thriller',
                'slug' => 'thriller',
                'description' => 'Suspense, tension et mystère',
            ],
            [
                'name' => 'Documentaire',
                'slug' => 'documentaire',
                'description' => 'Films et séries documentaires sur des sujets réels',
            ],
            [
                'name' => 'Animation',
                'slug' => 'animation',
                'description' => 'Films et séries d\'animation pour tous les âges',
            ],
            [
                'name' => 'Anime',
                'slug' => 'anime',
                'description' => 'Anime et manga japonais',
            ],
            [
                'name' => 'Aventure',
                'slug' => 'aventure',
                'description' => 'Explorations, quêtes et voyages épiques',
            ],
            [
                'name' => 'Crime',
                'slug' => 'crime',
                'description' => 'Enquêtes policières et histoires criminelles',
            ],
            [
                'name' => 'Fantastique',
                'slug' => 'fantastique',
                'description' => 'Mondes magiques, créatures fantastiques et mythologie',
            ],
            [
                'name' => 'Guerre',
                'slug' => 'guerre',
                'description' => 'Conflits militaires et batailles historiques',
            ],
            [
                'name' => 'Historique',
                'slug' => 'historique',
                'description' => 'Reconstitutions d\'événements historiques',
            ],
            [
                'name' => 'Musical',
                'slug' => 'musical',
                'description' => 'Films et séries avec chansons et chorégraphies',
            ],
            [
                'name' => 'Mystère',
                'slug' => 'mystere',
                'description' => 'Énigmes et investigations mystérieuses',
            ],
            [
                'name' => 'Western',
                'slug' => 'western',
                'description' => 'Far West américain, cowboys et duels',
            ],
            [
                'name' => 'Famille',
                'slug' => 'famille',
                'description' => 'Contenu adapté à toute la famille',
            ],
            [
                'name' => 'Sport',
                'slug' => 'sport',
                'description' => 'Films et séries sur le sport et les compétitions',
            ],
            [
                'name' => 'Biographie',
                'slug' => 'biographie',
                'description' => 'Histoires de vie de personnalités célèbres',
            ],
        ];

        foreach ($genres as $genre) {
            Category::firstOrCreate(['slug' => $genre['slug']], $genre);
        }

        // Catégories spécifiques aux Cours de Cinéma
        $courseCategories = [
            [
                'name' => 'Réalisation',
                'slug' => 'cours-realisation',
                'description' => 'Apprenez les techniques de réalisation avec des professionnels',
            ],
            [
                'name' => 'Scénarisation',
                'slug' => 'cours-scenarisation',
                'description' => 'Maîtrisez l\'art d\'écrire des scénarios captivants',
            ],
            [
                'name' => 'Acteur/Actrice',
                'slug' => 'cours-acteur',
                'description' => 'Formation au jeu d\'acteur par des vedettes du cinéma',
            ],
            [
                'name' => 'Production',
                'slug' => 'cours-production',
                'description' => 'Gestion et production de projets cinématographiques',
            ],
            [
                'name' => 'Montage',
                'slug' => 'cours-montage',
                'description' => 'Techniques de montage vidéo et post-production',
            ],
            [
                'name' => 'Photographie',
                'slug' => 'cours-photographie',
                'description' => 'Direction de la photographie et éclairage',
            ],
            [
                'name' => 'Son & Musique',
                'slug' => 'cours-son-musique',
                'description' => 'Design sonore et composition musicale pour le cinéma',
            ],
        ];

        foreach ($courseCategories as $course) {
            Category::firstOrCreate(['slug' => $course['slug']], $course);
        }

        // Catégories pour les projets de financement
        $projectCategories = [
            [
                'name' => 'Court-métrage',
                'slug' => 'projet-court-metrage',
                'description' => 'Projets de courts-métrages en recherche de financement',
            ],
            [
                'name' => 'Long-métrage',
                'slug' => 'projet-long-metrage',
                'description' => 'Projets de longs-métrages en recherche de financement',
            ],
            [
                'name' => 'Web-série',
                'slug' => 'projet-web-serie',
                'description' => 'Projets de séries web en recherche de financement',
            ],
            [
                'name' => 'Film Documentaire',
                'slug' => 'projet-documentaire',
                'description' => 'Projets de documentaires en recherche de financement',
            ],
            [
                'name' => 'Film d\'Animation',
                'slug' => 'projet-animation',
                'description' => 'Projets de films d\'animation en recherche de financement',
            ],
        ];

        foreach ($projectCategories as $project) {
            Category::firstOrCreate(['slug' => $project['slug']], $project);
        }

        // Catégories Lions Head Awards
        $awardsCategories = [
            [
                'name' => 'Meilleur Acteur',
                'slug' => 'award-meilleur-acteur',
                'description' => 'Votez pour le meilleur acteur de l\'année',
            ],
            [
                'name' => 'Meilleure Actrice',
                'slug' => 'award-meilleure-actrice',
                'description' => 'Votez pour la meilleure actrice de l\'année',
            ],
            [
                'name' => 'Meilleur Film',
                'slug' => 'award-meilleur-film',
                'description' => 'Votez pour le meilleur film de l\'année',
            ],
            [
                'name' => 'Meilleure Série',
                'slug' => 'award-meilleure-serie',
                'description' => 'Votez pour la meilleure série de l\'année',
            ],
            [
                'name' => 'Meilleur Réalisateur',
                'slug' => 'award-meilleur-realisateur',
                'description' => 'Votez pour le meilleur réalisateur de l\'année',
            ],
            [
                'name' => 'Meilleur Scénario',
                'slug' => 'award-meilleur-scenario',
                'description' => 'Votez pour le meilleur scénario de l\'année',
            ],
            [
                'name' => 'Révélation de l\'année',
                'slug' => 'award-revelation',
                'description' => 'Votez pour la révélation de l\'année',
            ],
        ];

        foreach ($awardsCategories as $award) {
            Category::firstOrCreate(['slug' => $award['slug']], $award);
        }
    }
}
