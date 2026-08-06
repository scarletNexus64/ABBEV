<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\Category;
use App\Models\Season;
use App\Models\Episode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Peuple le catalogue avec des contenus de demonstration ABBEV.
 * Reutilise les visuels existants (banners/thumbnails) pour eviter
 * tout contenu tiers — productions originales ABBEV uniquement.
 *
 * Idempotent : verifie le slug avant d'inserer.
 */
class DemoCatalogSeeder extends Seeder
{
    // Visuels existants qu'on reutilise
    private const BANNERS = [
        'banners/melchisedech-banner.jpg',
        'banners/therapist-banner.jpg',
        'banners/sphinx-prophecy-banner.jpg',
    ];

    private const POSTERS = [
        'thumbnails/melchisedech-poster.jpg',
        'thumbnails/therapist-poster.jpg',
        'thumbnails/sphinx-prophecy-poster.jpg',
    ];

    private const EPISODE_THUMBS = [
        'thumbnails/sphinx-ep13.jpg',
        'thumbnails/sphinx-ep14.jpg',
        'thumbnails/sphinx-ep16.jpg',
    ];

    public function run(): void
    {
        $movies = [
            [
                'title' => 'Le Royaume des Ombres',
                'description' => 'Dans les ruelles sombres de Douala, un jeune avocat decouvre un complot qui menace les fondements memes de la justice. Entre corruption et loyaute, il devra choisir son camp.',
                'category' => 'drame',
                'tier' => 'classique',
                'duration' => 5400,
                'release_year' => 2025,
                'visual_set' => 0,
            ],
            [
                'title' => 'Destins Croises',
                'description' => 'Deux familles que tout oppose se retrouvent liees par un secret vieux de trente ans. Un drame poignant sur l\'heritage, le pardon et la reconciliation.',
                'category' => 'drame',
                'tier' => 'standard',
                'duration' => 6200,
                'release_year' => 2025,
                'visual_set' => 1,
            ],
            [
                'title' => 'La Derniere Promesse',
                'description' => 'Aisha, une jeune medecin, revient dans son village natal pour honorer la derniere volonte de son pere. Elle y decouvre une communaute en pleine mutation et un amour inattendu.',
                'category' => 'romance',
                'tier' => 'classique',
                'duration' => 5800,
                'release_year' => 2026,
                'visual_set' => 2,
            ],
            [
                'title' => 'Ceux qui restent',
                'description' => 'Apres la disparition mysterieuse de leur frere aine, trois soeurs doivent reprendre l\'entreprise familiale tout en demêlant un reseau de mensonges.',
                'category' => 'thriller',
                'tier' => 'standard',
                'duration' => 7200,
                'release_year' => 2026,
                'visual_set' => 0,
            ],
            [
                'title' => 'L\'Etoile de Bamenda',
                'description' => 'L\'histoire vraie d\'une joueuse de football camerounaise qui, contre toute attente, se hisse au sommet du sport continental.',
                'category' => 'biographie',
                'tier' => 'premium',
                'duration' => 6800,
                'release_year' => 2025,
                'visual_set' => 1,
            ],
            [
                'title' => 'Sous le Baobab',
                'description' => 'Un grand-pere raconte a ses petits-enfants les legendes ancestrales du peuple Bamileke. Un voyage poetique entre passe et present.',
                'category' => 'famille',
                'tier' => 'classique',
                'duration' => 4800,
                'release_year' => 2026,
                'visual_set' => 2,
            ],
            [
                'title' => 'Mémoires de Kribi',
                'description' => 'Trois amis d\'enfance se retrouvent vingt ans plus tard sur les plages de Kribi. Les retrouvailles ravivent des souvenirs douloureux et des verites enfouies.',
                'category' => 'drame',
                'tier' => 'standard',
                'duration' => 5600,
                'release_year' => 2025,
                'visual_set' => 0,
            ],
            [
                'title' => 'Le Cercle des Initiés',
                'description' => 'Un journaliste d\'investigation infiltre une societe secrete influente a Yaounde. Plus il s\'enfonce, plus la frontiere entre enquête et initiation s\'efface.',
                'category' => 'thriller',
                'tier' => 'premium',
                'duration' => 7400,
                'release_year' => 2026,
                'visual_set' => 1,
            ],
            [
                'title' => 'Terre Rouge',
                'description' => 'Dans le Cameroun des annees 1950, une famille de planteurs lutte pour sa terre face aux grands proprietaires coloniaux. Un film historique puissant.',
                'category' => 'historique',
                'tier' => 'premium',
                'duration' => 8100,
                'release_year' => 2025,
                'visual_set' => 2,
            ],
            [
                'title' => 'La Voix du Wouri',
                'description' => 'Une chanteuse de Makossa reve de conquérir la scene internationale. Entre Douala et Paris, elle devra faire face a ses demons et a l\'industrie musicale.',
                'category' => 'musical',
                'tier' => 'classique',
                'duration' => 5200,
                'release_year' => 2026,
                'visual_set' => 0,
            ],
            [
                'title' => 'Nuit Blanche a Douala',
                'description' => 'Un commissaire a une seule nuit pour retrouver un temoin cle avant un proces historique. Course contre la montre dans les quartiers de Douala.',
                'category' => 'action',
                'tier' => 'standard',
                'duration' => 5900,
                'release_year' => 2026,
                'visual_set' => 1,
            ],
            [
                'title' => 'Les Fils du Mont Cameroun',
                'description' => 'Documentaire sur les coureurs du Mont Cameroun, entre tradition seculaire et competition moderne. Un regard intime sur le depassement de soi.',
                'category' => 'documentaire',
                'tier' => 'classique',
                'duration' => 4500,
                'release_year' => 2025,
                'visual_set' => 2,
            ],
        ];

        $series = [
            [
                'title' => 'Quartier Général',
                'description' => 'La vie quotidienne d\'un quartier populaire de Douala, entre rires, drames et solidarite. Chaque episode est une tranche de vie authentique.',
                'category' => 'comedie',
                'tier' => 'classique',
                'seasons_count' => 2,
                'episodes_per_season' => 5,
                'visual_set' => 1,
            ],
            [
                'title' => 'Les Héritiers',
                'description' => 'A la mort d\'un magnat de l\'immobilier, ses cinq enfants se disputent un empire. Trahisons, alliances et coups bas dans le Yaounde des affaires.',
                'category' => 'drame',
                'tier' => 'standard',
                'seasons_count' => 2,
                'episodes_per_season' => 6,
                'visual_set' => 0,
            ],
            [
                'title' => 'Code Rouge',
                'description' => 'Le quotidien d\'une equipe de medecins urgentistes a l\'hopital central. Chaque garde apporte son lot de defis medicaux et humains.',
                'category' => 'drame',
                'tier' => 'standard',
                'seasons_count' => 1,
                'episodes_per_season' => 8,
                'visual_set' => 2,
            ],
            [
                'title' => 'Ombres et Lumieres',
                'description' => 'Une inspectrice de police traque un tueur en serie dans les grandes villes du Cameroun. Chaque saison, une nouvelle enquête.',
                'category' => 'crime',
                'tier' => 'premium',
                'seasons_count' => 2,
                'episodes_per_season' => 6,
                'visual_set' => 1,
            ],
            [
                'title' => 'Campus Life',
                'description' => 'Les aventures d\'un groupe d\'etudiants a l\'Universite de Douala. Amities, amours et ambitions dans le monde universitaire camerounais.',
                'category' => 'comedie',
                'tier' => 'classique',
                'seasons_count' => 1,
                'episodes_per_season' => 7,
                'visual_set' => 2,
            ],
        ];

        $this->command->info('Insertion des films...');

        foreach ($movies as $movie) {
            $slug = Str::slug($movie['title']);

            if (Media::where('slug', $slug)->exists()) {
                $this->command->warn("  Skip (existe deja) : {$movie['title']}");
                continue;
            }

            $cat = Category::where('slug', $movie['category'])->first();

            Media::create([
                'category_id' => $cat?->id,
                'type' => 'movie',
                'title' => $movie['title'],
                'slug' => $slug,
                'description' => $movie['description'],
                'duration' => $movie['duration'],
                'release_year' => $movie['release_year'],
                'banner_path' => self::BANNERS[$movie['visual_set']],
                'thumbnail_path' => self::POSTERS[$movie['visual_set']],
                'cover_path' => self::POSTERS[$movie['visual_set']],
                'video_provider' => 'local',
                'video_path' => null,
                'published_at' => now(),
                'is_featured' => in_array($movie['visual_set'], [0, 1]),
                'tier' => $movie['tier'],
                'moderation_status' => 'approved',
                'views_count' => rand(10, 500),
            ]);

            $this->command->info("  + {$movie['title']}");
        }

        $this->command->info('Insertion des series...');

        foreach ($series as $serie) {
            $slug = Str::slug($serie['title']);

            if (Media::where('slug', $slug)->exists()) {
                $this->command->warn("  Skip (existe deja) : {$serie['title']}");
                continue;
            }

            $cat = Category::where('slug', $serie['category'])->first();

            $media = Media::create([
                'category_id' => $cat?->id,
                'type' => 'series',
                'title' => $serie['title'],
                'slug' => $slug,
                'description' => $serie['description'],
                'seasons' => $serie['seasons_count'],
                'banner_path' => self::BANNERS[$serie['visual_set']],
                'thumbnail_path' => self::POSTERS[$serie['visual_set']],
                'cover_path' => self::POSTERS[$serie['visual_set']],
                'video_provider' => 'local',
                'published_at' => now(),
                'is_featured' => $serie['visual_set'] === 0,
                'tier' => $serie['tier'],
                'moderation_status' => 'approved',
                'views_count' => rand(20, 800),
            ]);

            for ($s = 1; $s <= $serie['seasons_count']; $s++) {
                $season = Season::create([
                    'media_id' => $media->id,
                    'season_number' => $s,
                    'title' => "Saison {$s}",
                ]);

                for ($ep = 1; $ep <= $serie['episodes_per_season']; $ep++) {
                    Episode::create([
                        'season_id' => $season->id,
                        'episode_number' => $ep,
                        'title' => "Episode {$ep}",
                        'description' => "Saison {$s}, Episode {$ep} de {$serie['title']}.",
                        'duration' => rand(1200, 2700),
                        'thumbnail_path' => self::EPISODE_THUMBS[($ep - 1) % 3],
                        'video_provider' => 'local',
                        'video_path' => null,
                    ]);
                }
            }

            $totalEps = $serie['seasons_count'] * $serie['episodes_per_season'];
            $this->command->info("  + {$serie['title']} ({$serie['seasons_count']} saisons, {$totalEps} episodes)");
        }

        $this->command->info('Catalogue demo ABBEV peuple avec succes.');
    }
}
