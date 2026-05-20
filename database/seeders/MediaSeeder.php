<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Episode;
use App\Models\Media;
use App\Models\Season;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Seeder de démonstration : 100 films + 50 séries.
 *
 * - Les visuels (affiches / bannières) pointent vers le CDN public TMDB
 *   (image.tmdb.org), aucune clé API requise — voir database/seeders/data/catalog.php.
 *   En fallback (entrée sans poster TMDB), un SVG local est généré dans
 *   storage/app/public/{posters,banners}/.
 * - Toutes les vidéos (films ET épisodes) utilisent la SEULE vidéo longue
 *   de la library Bunny (~62 min) : c'est la seule assez longue pour être
 *   réaliste, conformément au choix produit.
 * - video_provider / video_id / video_library_id / video_metadata sont
 *   remplis exactement comme le fait MediaController::store(), afin que la
 *   lecture (iframe Bunny) fonctionne partout dans le dashboard.
 *
 * Idempotent : on purge médias/saisons/épisodes de démo avant de réinsérer.
 */
class MediaSeeder extends Seeder
{
    use WithoutModelEvents;

    /** GUID de l'unique vidéo Bunny longue (~62 min) utilisée partout. */
    private const BUNNY_GUID = '0b0553cf-e006-4456-b9c4-92f47daf6b62';

    /** Durée réelle de cette vidéo Bunny, en secondes. */
    private const BUNNY_LENGTH = 3711;

    public function run(): void
    {
        $catalog = require database_path('seeders/data/catalog.php');

        $libraryId = (string) config('services.bunny.library_id');

        // Métadonnées Bunny réelles de la vidéo (snapshot de l'API getVideo).
        // C'est ce que MediaController stocke dans video_metadata.
        $bunnyMeta = [
            'videoLibraryId'       => 664138,
            'guid'                 => self::BUNNY_GUID,
            'title'                => 'ABBEV Demo Stream',
            'length'               => self::BUNNY_LENGTH,
            'status'               => 4,
            'framerate'            => 25,
            'width'                => 640,
            'height'               => 360,
            'availableResolutions' => '360p,240p',
            'thumbnailFileName'    => 'thumbnail.jpg',
            'hasMP4Fallback'       => true,
        ];

        $filmsCategory  = Category::where('slug', 'films')->firstOrFail();
        $seriesCategory = Category::where('slug', 'series')->firstOrFail();

        // Map genre-slug -> Category, pour rattacher chaque titre à son genre.
        $genres = Category::whereIn('slug', collect($catalog['movies'])
            ->pluck('genre')
            ->merge(collect($catalog['series'])->pluck('genre'))
            ->unique()
            ->all())
            ->get()
            ->keyBy('slug');

        $this->purgePreviousDemoData();

        /* ---------------------------------------------------------------
         |  FILMS — on garde les 100 premiers du catalogue
         * --------------------------------------------------------------- */
        $movies = array_slice($catalog['movies'], 0, 100);

        foreach ($movies as $i => $m) {
            $category = $genres[$m['genre']] ?? $filmsCategory;

            Media::create([
                'category_id'      => $category->id,
                'type'             => 'movie',
                'title'            => $m['title'],
                'slug'             => $this->uniqueSlug($m['title'], $m['year']),
                'description'      => $m['desc'],
                'duration'         => $m['duration'],
                'release_year'     => $m['year'],
                'seasons'          => null,
                'video_path'       => null,
                'video_provider'   => 'bunny',
                'video_id'         => self::BUNNY_GUID,
                'video_library_id' => $libraryId,
                'video_metadata'   => $bunnyMeta,
                'thumbnail_path'   => $this->poster($m['title'], $m['genre'], $m['poster'] ?? null),
                'cover_path'       => $this->poster($m['title'], $m['genre'], $m['poster'] ?? null),
                'banner_path'      => $this->banner($m['title'], $m['genre'], $m['backdrop'] ?? null),
                'published_at'     => now()->subDays(rand(1, 720)),
                'is_featured'      => $i < 8, // les 8 premiers à la une
                'views_count'      => rand(120, 95000),
            ]);
        }

        /* ---------------------------------------------------------------
         |  SÉRIES — chaque série a ses saisons + épisodes
         * --------------------------------------------------------------- */
        $seriesList = array_slice($catalog['series'], 0, 50);

        // Nombre fixe d'épisodes par saison (demande client).
        $episodesPerSeason = 12;

        foreach ($seriesList as $i => $s) {
            $category    = $genres[$s['genre']] ?? $seriesCategory;
            $seasonCount = count($s['seasons']); // on garde le nb de saisons défini

            $media = Media::create([
                'category_id'      => $category->id,
                'type'             => 'series',
                'title'            => $s['title'],
                'slug'             => $this->uniqueSlug($s['title'], $s['year']),
                'description'      => $s['desc'],
                'duration'         => null,
                'release_year'     => $s['year'],
                'seasons'          => $seasonCount,
                'video_path'       => null,
                // Une série n'a pas de vidéo directe : ses épisodes la portent.
                'video_provider'   => null,
                'video_id'         => null,
                'video_library_id' => null,
                'video_metadata'   => null,
                'thumbnail_path'   => $this->poster($s['title'], $s['genre'], $s['poster'] ?? null),
                'cover_path'       => $this->poster($s['title'], $s['genre'], $s['poster'] ?? null),
                'banner_path'      => $this->banner($s['title'], $s['genre'], $s['backdrop'] ?? null),
                'published_at'     => now()->subDays(rand(1, 720)),
                'is_featured'      => $i < 6,
                'views_count'      => rand(500, 180000),
            ]);

            for ($seasonNumber = 1; $seasonNumber <= $seasonCount; $seasonNumber++) {
                $season = Season::create([
                    'media_id'       => $media->id,
                    'season_number'  => $seasonNumber,
                    'title'          => "Saison {$seasonNumber}",
                    'description'    => "Saison {$seasonNumber} de « {$s['title']} ».",
                    'thumbnail_path' => $this->banner($s['title'], $s['genre'], $s['backdrop'] ?? null),
                    'release_year'   => $s['year'] + ($seasonNumber - 1),
                    'episodes_count' => $episodesPerSeason,
                ]);

                for ($ep = 1; $ep <= $episodesPerSeason; $ep++) {
                    Episode::create([
                        'season_id'        => $season->id,
                        'episode_number'   => $ep,
                        'title'            => "Épisode {$ep}",
                        'description'      => "Saison {$seasonNumber}, épisode {$ep} de « {$s['title']} ».",
                        'duration'         => self::BUNNY_LENGTH,
                        'video_path'       => 'bunny://' . self::BUNNY_GUID,
                        'video_provider'   => 'bunny',
                        'video_id'         => self::BUNNY_GUID,
                        'video_library_id' => $libraryId,
                        'video_metadata'   => $bunnyMeta,
                        'thumbnail_path'   => $this->banner($s['title'], $s['genre'], $s['backdrop'] ?? null),
                        'published_at'     => now()->subDays(rand(1, 600)),
                        'views_count'      => rand(50, 40000),
                    ]);
                }
            }
        }

        $this->command?->info(sprintf(
            'Seed terminé : %d films, %d séries (%d saisons, %d épisodes).',
            count($movies),
            count($seriesList),
            Season::count(),
            Episode::count(),
        ));
    }

    /**
     * Supprime les données de démo existantes pour rendre le seed rejouable.
     * Les épisodes/saisons partent en cascade via les FK.
     */
    private function purgePreviousDemoData(): void
    {
        Episode::query()->delete();
        Season::query()->delete();
        Media::query()->delete();
    }

    private function uniqueSlug(string $title, int $year): string
    {
        $base = Str::slug($title . '-' . $year);
        $slug = $base;
        $n    = 1;

        while (Media::where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$n);
        }

        return $slug;
    }

    /**
     * Palette de couleurs (fond / accent) par genre — pour des affiches
     * lisibles et cohérentes visuellement.
     *
     * @return array{0:string,1:string}
     */
    private function palette(string $genre): array
    {
        $map = [
            'action'          => ['0b1d3a', 'ff4d4d'],
            'aventure'        => ['1b3a1b', 'ffcc33'],
            'comedie'         => ['3a2e0b', 'ffd23f'],
            'drame'           => ['2a2a2a', 'e0e0e0'],
            'horreur'         => ['1a0000', 'b30000'],
            'science-fiction' => ['001a33', '00d4ff'],
            'romance'         => ['3a0b22', 'ff6f9c'],
            'thriller'        => ['12121a', 'ff8c1a'],
            'documentaire'    => ['10261f', '2ecc71'],
            'animation'       => ['1a0b3a', 'b14dff'],
            'anime'           => ['2a0b3a', 'ff4dd2'],
            'crime'           => ['1a1a1a', 'c0392b'],
            'fantastique'     => ['0b1a3a', '6f8cff'],
            'guerre'          => ['262616', '8a7f3f'],
            'historique'      => ['2a1a0b', 'd4a017'],
            'musical'         => ['2a0b2a', 'ff5fd2'],
            'mystere'         => ['0b0b1a', '7f8cff'],
            'western'         => ['2a1605', 'd98032'],
            'biographie'      => ['0b1f2a', '3fb6c6'],
            'sport'           => ['072a16', '2ecc71'],
        ];

        return $map[$genre] ?? ['1a1a2e', 'e94560'];
    }

    /**
     * Affiche verticale (2:3).
     * Priorité : URL TMDB (image.tmdb.org/t/p/w500{poster}) si dispo dans le
     * catalogue. Sinon, fallback SVG local pour ne jamais avoir d'image cassée.
     */
    private function poster(string $title, string $genre, ?string $tmdbPath = null): string
    {
        if ($tmdbPath) {
            return 'https://image.tmdb.org/t/p/w500' . $tmdbPath;
        }

        return $this->generateSvg($title, $genre, 500, 750, 'posters');
    }

    /**
     * Bannière horizontale (16:9).
     * Priorité : URL TMDB (image.tmdb.org/t/p/w1280{backdrop}). Sinon fallback SVG.
     */
    private function banner(string $title, string $genre, ?string $tmdbPath = null): string
    {
        if ($tmdbPath) {
            return 'https://image.tmdb.org/t/p/w1280' . $tmdbPath;
        }

        return $this->generateSvg($title, $genre, 1280, 720, 'banners');
    }

    /**
     * Génère un fichier SVG sur disk public et retourne son chemin relatif
     * (compatible avec asset('storage/...')). Réutilise le fichier s'il existe.
     */
    private function generateSvg(string $title, string $genre, int $w, int $h, string $folder): string
    {
        [$bg, $fg] = $this->palette($genre);
        $label     = $this->labelFor($title);
        $slug      = Str::slug($title) ?: 'untitled';
        $path      = "{$folder}/{$slug}-{$w}x{$h}.svg";

        $disk = Storage::disk('public');
        if (! $disk->exists($path)) {
            $fontSize  = (int) round(min($w, $h) / max(8, mb_strlen($label) / 2));
            $fontSize  = max(28, min($fontSize, 72));
            $safeLabel = htmlspecialchars($label, ENT_XML1, 'UTF-8');

            $svg = <<<SVG
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$w} {$h}" width="{$w}" height="{$h}">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#{$bg}"/>
      <stop offset="100%" stop-color="#000000"/>
    </linearGradient>
  </defs>
  <rect width="100%" height="100%" fill="url(#g)"/>
  <text x="50%" y="50%" font-family="Helvetica, Arial, sans-serif" font-weight="700"
        font-size="{$fontSize}" fill="#{$fg}" text-anchor="middle" dominant-baseline="middle">{$safeLabel}</text>
</svg>
SVG;
            $disk->put($path, $svg);
        }

        return $path;
    }

    /**
     * Label lisible pour le visuel — accents aplatis, ponctuation enlevée.
     */
    private function labelFor(string $title): string
    {
        $clean = Str::ascii($title);
        $clean = preg_replace('/[^A-Za-z0-9 ]/', '', $clean);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        return $clean;
    }
}
