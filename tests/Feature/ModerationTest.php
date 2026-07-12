<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Modération : le contenu non approuvé est invisible au catalogue public ;
 * l'assistant/admin approuve (catégorie + tier) ou rejette ; l'accès au panneau
 * est réservé aux rôles admin/assistant.
 */
class ModerationTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::firstOrCreate(['slug' => 'action'], ['name' => 'Action']);
    }

    private function movie(string $status, string $title): Media
    {
        return Media::create([
            'category_id' => $this->category->id,
            'type' => 'movie',
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title) . '-' . uniqid(),
            'moderation_status' => $status,
            'video_provider' => 'bunny',
            'video_id' => 'guid-' . uniqid(),
        ]);
    }

    public function test_catalogue_public_masque_le_contenu_non_approuve(): void
    {
        $this->movie('approved', 'Film Public');
        $this->movie('pending', 'Film En Attente');
        $this->movie('rejected', 'Film Rejeté');

        $res = $this->getJson('/api/v1/movies')->assertOk();
        $titles = collect($res->json('data'))->pluck('title');

        $this->assertContains('Film Public', $titles);
        $this->assertNotContains('Film En Attente', $titles);
        $this->assertNotContains('Film Rejeté', $titles);
    }

    public function test_detail_d_un_contenu_non_approuve_renvoie_404(): void
    {
        $pending = $this->movie('pending', 'Caché');

        $this->getJson('/api/v1/movies/' . $pending->getRouteKey())->assertNotFound();
    }

    public function test_assistant_approuve_avec_categorie_et_tier(): void
    {
        $assistant = User::factory()->create(['role' => 'assistant']);
        $movie = $this->movie('pending', 'À Valider');
        $newCat = Category::create(['name' => 'Drame', 'slug' => 'drame']);

        $this->actingAs($assistant)
            ->post(route('moderation.approve', $movie->id), [
                'category_id' => $newCat->id,
                'tier' => 'premium',
            ])
            ->assertRedirect(route('moderation.index'));

        $movie->refresh();
        $this->assertSame('approved', $movie->moderation_status);
        $this->assertSame('premium', $movie->tier);
        $this->assertSame($newCat->id, $movie->category_id);
        $this->assertSame($assistant->id, $movie->reviewed_by);
        $this->assertNotNull($movie->published_at);
    }

    public function test_assistant_rejette_avec_motif(): void
    {
        $assistant = User::factory()->create(['role' => 'assistant']);
        $movie = $this->movie('pending', 'À Rejeter');

        $this->actingAs($assistant)
            ->post(route('moderation.reject', $movie->id), [
                'rejection_reason' => 'Qualité insuffisante.',
            ])->assertRedirect();

        $movie->refresh();
        $this->assertSame('rejected', $movie->moderation_status);
        $this->assertSame('Qualité insuffisante.', $movie->rejection_reason);
    }

    public function test_examen_charge_un_lecteur_pret_pour_une_video_locale(): void
    {
        Storage::fake('local');
        $path = Storage::disk('local')->putFile('videos', UploadedFile::fake()->create('t.mp4', 10, 'video/mp4'));

        $assistant = User::factory()->create(['role' => 'assistant']);
        $movie = Media::create([
            'category_id' => $this->category->id,
            'type' => 'movie',
            'title' => 'Locale',
            'slug' => 'locale-' . uniqid(),
            'moderation_status' => 'pending',
            'video_provider' => 'local',
            'video_path' => $path,
        ]);

        $this->actingAs($assistant)
            ->get(route('moderation.show', $movie->id))
            ->assertOk()
            ->assertSee('watch/local/movie', false)
            ->assertSee('<video', false);
    }

    public function test_un_utilisateur_standard_ne_peut_pas_moderer(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $this->actingAs($user)->get(route('moderation.index'))->assertForbidden();
    }
}
