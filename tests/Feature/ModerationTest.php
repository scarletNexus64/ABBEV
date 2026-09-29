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
 * le producteur (ou son équipe) approuve ou rejette SES contenus, l'admin
 * modère tout et reste seul à fixer le tier.
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

    private function movie(string $status, string $title, ?User $owner = null): Media
    {
        return Media::create([
            'user_id' => $owner?->id,
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

    public function test_admin_approuve_avec_categorie_et_tier(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $movie = $this->movie('pending', 'À Valider');
        $newCat = Category::create(['name' => 'Drame', 'slug' => 'drame']);

        $this->actingAs($admin)
            ->post(route('moderation.approve', $movie->id), [
                'category_id' => $newCat->id,
                'tier' => 'premium',
            ])
            ->assertRedirect(route('moderation.index'));

        $movie->refresh();
        $this->assertSame('approved', $movie->moderation_status);
        $this->assertSame('premium', $movie->tier);
        $this->assertSame($newCat->id, $movie->category_id);
        $this->assertSame($admin->id, $movie->reviewed_by);
        $this->assertNotNull($movie->published_at);
    }

    public function test_le_producteur_approuve_ses_contenus_sans_toucher_au_tier(): void
    {
        $producer = User::factory()->create(['role' => 'producer']);
        $movie = $this->movie('pending', 'Mon Film', $producer);
        $newCat = Category::create(['name' => 'Drame', 'slug' => 'drame']);

        $this->actingAs($producer)
            ->post(route('moderation.approve', $movie->id), [
                'category_id' => $newCat->id,
                'tier' => 'premium', // ignoré : le tier est fixé par l'admin
            ])
            ->assertRedirect(route('moderation.index'));

        $movie->refresh();
        $this->assertSame('approved', $movie->moderation_status);
        $this->assertSame('classique', $movie->tier);
        $this->assertSame($newCat->id, $movie->category_id);
        $this->assertSame($producer->id, $movie->reviewed_by);
    }

    public function test_le_producteur_ne_modere_pas_les_contenus_d_un_autre(): void
    {
        $producer = User::factory()->create(['role' => 'producer']);
        $other = User::factory()->create(['role' => 'producer']);
        $mine = $this->movie('pending', 'Le Mien', $producer);
        $theirs = $this->movie('pending', 'Le Sien', $other);

        $this->actingAs($producer)->get(route('moderation.index'))
            ->assertOk()->assertSee('Le Mien')->assertDontSee('Le Sien');

        $this->actingAs($producer)->get(route('moderation.show', $theirs->id))->assertNotFound();
        $this->actingAs($producer)
            ->post(route('moderation.reject', $theirs->id), ['rejection_reason' => 'Non.'])
            ->assertNotFound();
        $this->assertSame('pending', $theirs->fresh()->moderation_status);
    }

    public function test_un_membre_d_equipe_rejette_avec_motif(): void
    {
        $producer = User::factory()->create(['role' => 'producer']);
        $member = User::factory()->create(['role' => 'producer']);
        $member->forceFill(['producer_id' => $producer->id, 'permissions' => ['moderation']])->save();
        $movie = $this->movie('pending', 'À Rejeter', $producer);

        $this->actingAs($member)
            ->post(route('moderation.reject', $movie->id), [
                'rejection_reason' => 'Qualité insuffisante.',
            ])->assertRedirect();

        $movie->refresh();
        $this->assertSame('rejected', $movie->moderation_status);
        $this->assertSame('Qualité insuffisante.', $movie->rejection_reason);
    }

    public function test_un_membre_sans_le_module_ne_modere_pas(): void
    {
        $producer = User::factory()->create(['role' => 'producer']);
        $member = User::factory()->create(['role' => 'producer']);
        $member->forceFill(['producer_id' => $producer->id, 'permissions' => ['contents']])->save();

        $this->actingAs($member)->get(route('moderation.index'))->assertForbidden();
    }

    public function test_examen_charge_un_lecteur_pret_pour_une_video_locale(): void
    {
        Storage::fake('local');
        $path = Storage::disk('local')->putFile('videos', UploadedFile::fake()->create('t.mp4', 10, 'video/mp4'));

        $admin = User::factory()->create(['role' => 'admin']);
        $movie = Media::create([
            'category_id' => $this->category->id,
            'type' => 'movie',
            'title' => 'Locale',
            'slug' => 'locale-' . uniqid(),
            'moderation_status' => 'pending',
            'video_provider' => 'local',
            'video_path' => $path,
        ]);

        $this->actingAs($admin)
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
