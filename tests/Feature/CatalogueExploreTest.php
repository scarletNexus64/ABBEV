<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Episode;
use App\Models\Media;
use App\Models\Rubrique;
use App\Models\Season;
use App\Support\MediaFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Catalogue réorganisé selon cat.md : les 15 genres, les formats de durée
 * (film court/moyen/long, série très court/court/moyen) et le sommaire de
 * l'écran « Explorer ».
 */
class CatalogueExploreTest extends TestCase
{
    use RefreshDatabase;

    private Category $drame;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\CategorySeeder::class);
        $this->drame = Category::where('slug', 'drame')->firstOrFail();
    }

    private function media(string $type, ?int $seconds = null, array $extra = []): Media
    {
        return Media::create($extra + [
            'category_id' => $this->drame->id,
            'type' => $type,
            'title' => 'Titre ' . Str::random(6),
            'slug' => Str::random(12),
            'duration' => $seconds,
            'moderation_status' => 'approved',
            'published_at' => now()->subDay(),
        ]);
    }

    public function test_le_referentiel_compte_exactement_les_15_genres_de_cat_md(): void
    {
        $slugs = collect($this->getJson('/api/v1/categories')->assertOk()->json('data'))->pluck('slug');

        $this->assertCount(15, $slugs);
        $this->assertSame(array_keys(Category::REFERENCE_GENRES), $slugs->all(), 'ordre éditorial de cat.md');
    }

    public function test_seuils_des_formats_de_film(): void
    {
        $this->assertSame('court', MediaFormat::forMovieSeconds(29 * 60 + 59));
        $this->assertSame('moyen', MediaFormat::forMovieSeconds(30 * 60));
        $this->assertSame('moyen', MediaFormat::forMovieSeconds(59 * 60 + 59));
        $this->assertSame('long', MediaFormat::forMovieSeconds(60 * 60));
        $this->assertNull(MediaFormat::forMovieSeconds(null));
    }

    public function test_seuils_des_formats_de_serie(): void
    {
        $this->assertSame('tres-court', MediaFormat::forEpisodeSeconds(12 * 60));
        $this->assertSame('court', MediaFormat::forEpisodeSeconds(13 * 60));
        $this->assertSame('court', MediaFormat::forEpisodeSeconds(30 * 60));
        $this->assertSame('moyen', MediaFormat::forEpisodeSeconds(31 * 60));
    }

    public function test_le_format_d_une_serie_suit_ses_episodes(): void
    {
        $series = $this->media('series');
        $season = Season::create(['media_id' => $series->id, 'season_number' => 1]);

        Episode::create(['season_id' => $season->id, 'episode_number' => 1, 'title' => 'Ép. 1', 'duration' => 26 * 60]);
        $this->assertSame('court', $series->fresh()->format);

        Episode::create(['season_id' => $season->id, 'episode_number' => 2, 'title' => 'Ép. 2', 'duration' => 52 * 60]);
        $this->assertSame('moyen', $series->fresh()->format, 'moyenne 39 min');
    }

    public function test_un_format_fixe_a_la_main_n_est_plus_recalcule(): void
    {
        $movie = $this->media('movie', 95 * 60, ['format' => 'court', 'format_locked' => true]);

        MediaFormat::refresh($movie);

        $this->assertSame('court', $movie->fresh()->format);
    }

    public function test_filtre_des_films_par_format(): void
    {
        MediaFormat::refresh($short = $this->media('movie', 15 * 60));
        MediaFormat::refresh($this->media('movie', 110 * 60));

        $ids = collect($this->getJson('/api/v1/movies?format=court')->assertOk()->json('data'))->pluck('id');

        $this->assertSame([(string) $short->id], $ids->all());
    }

    public function test_un_genre_se_consulte_aussi_par_son_slug(): void
    {
        $drame = Category::where('slug', 'drame')->firstOrFail();

        $this->getJson("/api/v1/categories/{$drame->id}/media")->assertOk();
        $this->getJson('/api/v1/categories/drame/media')->assertOk()->assertJsonPath('category.slug', 'drame');
        $this->getJson('/api/v1/categories/inconnu/media')->assertNotFound();
    }

    public function test_le_sommaire_explorer_compte_chaque_section(): void
    {
        MediaFormat::refresh($this->media('movie', 45 * 60));
        // Créée par migration : Sport et Jeux existent dès l'installation.
        $rubrique = Rubrique::where('slug', 'sport')->firstOrFail();
        $rubrique->media()->attach($this->media('series')->id);

        $data = $this->getJson('/api/v1/explore')->assertOk()->json('data');

        $this->assertSame(1, $data['movies']['formats']['moyen']);
        $this->assertSame(['tres-court', 'court', 'moyen'], array_keys($data['series']['formats']));
        $this->assertCount(15, $data['genres']);
        $this->assertSame(1, $data['rubriques']['sport']['series_count']);
        $this->assertFalse($data['rubriques']['sport']['locked']);
        $this->assertNull($data['awards'], 'aucune édition en cours');
        $this->assertSame(['financement', 'ecriture', 'musique'], array_keys($data['calls']));
    }
}
