<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\BunnyUpload;
use App\Models\Category;
use App\Models\Media;
use App\Models\Screening;
use App\Models\Talent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Transfert de données (admin) : plateforme ou producteur → producteur.
 */
class DataTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function producer(string $name): User
    {
        return User::factory()->create(['role' => 'producer', 'name' => $name]);
    }

    private function talent(string $first, ?User $owner = null, ?Agent $agent = null): Talent
    {
        $talent = Talent::create([
            'kind' => 'acteur', 'first_name' => $first, 'last_name' => 'Test',
            'tier' => 'B', 'profession' => 'acteur', 'is_published' => true, 'agent_id' => $agent?->id,
        ]);
        $talent->forceFill(['producer_id' => $owner?->id])->save();

        return $talent;
    }

    private function movie(string $title, ?User $owner, array $extra = []): Media
    {
        return Media::create($extra + [
            'category_id' => Category::firstOrCreate(['slug' => 'drame'], ['name' => 'Drame'])->id,
            'user_id' => $owner?->id,
            'type' => 'movie',
            'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title) . '-' . uniqid(),
            'moderation_status' => 'approved',
            'video_provider' => 'bunny',
            'video_id' => 'guid-' . uniqid(),
        ]);
    }

    private function transfer(?User $source, User $target, array $items, array $options = [])
    {
        return $this->actingAs($this->admin)->post(route('transfers.store'), $options + [
            'source' => $source ? $source->getRouteKey() : 'platform',
            'target' => $target->getRouteKey(),
            'items' => $items,
            'include_agents' => '1',
            'reset_views' => '1',
        ]);
    }

    public function test_l_ecran_liste_les_donnees_de_la_source(): void
    {
        $target = $this->producer('Destinataire');
        $this->talent('Plateforme');
        $this->talent('Ailleurs', $this->producer('Autre'));
        $this->movie('Film Admin', $this->admin);

        $this->actingAs($this->admin)
            ->get(route('transfers.index', ['target' => $target->getRouteKey()]))
            ->assertOk()
            ->assertSee('Plateforme Test')
            ->assertSee('Film Admin')
            ->assertDontSee('Ailleurs Test');
    }

    public function test_transfert_de_la_plateforme_vers_un_producteur(): void
    {
        $target = $this->producer('Destinataire');
        $agent = Agent::create(['name' => 'Agence Lumière', 'represents' => 'acteurs']);
        $talent = $this->talent('Awa', null, $agent);
        $untouched = $this->talent('Reste');

        $this->transfer(null, $target, ['talents' => [$talent->id]])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame($target->id, $talent->fresh()->producer_id);
        $this->assertSame($target->id, $agent->fresh()->producer_id, "l'agent suit son talent");
        $this->assertNull($untouched->fresh()->producer_id);

        // Le producteur le gère désormais depuis son espace.
        $this->actingAs($target)->get(route('talents.edit', $talent))->assertOk();
    }

    public function test_les_contenus_emportent_leurs_uploads_et_repartent_a_zero_en_revenus(): void
    {
        $source = $this->producer('Ancien');
        $target = $this->producer('Nouveau');
        $movie = $this->movie('Film Transféré', $source, ['producer_views' => 120]);
        $upload = BunnyUpload::create([
            'user_id' => $source->id, 'original_filename' => 'film.mp4', 'title' => 'Film',
            'size_bytes' => 1000, 'status' => 'ready', 'bunny_guid' => $movie->video_id,
        ]);

        $this->transfer($source, $target, ['media' => [$movie->id]])->assertSessionHas('success');

        $movie->refresh();
        $this->assertSame($target->id, $movie->user_id);
        $this->assertSame(0, (int) $movie->producer_views);
        $this->assertSame($target->id, $upload->fresh()->user_id, "l'ancien propriétaire ne peut plus supprimer la vidéo");
    }

    public function test_les_vues_remunerees_peuvent_suivre_le_contenu(): void
    {
        $target = $this->producer('Nouveau');
        $movie = $this->movie('Film', $this->admin, ['producer_views' => 50]);

        $this->transfer(null, $target, ['media' => [$movie->id]], ['reset_views' => '0']);

        $this->assertSame(50, (int) $movie->fresh()->producer_views);
    }

    public function test_seuls_les_elements_de_la_source_sont_transferes(): void
    {
        $source = $this->producer('Source');
        $target = $this->producer('Cible');
        $foreign = $this->talent('Intrus', $this->producer('Tiers'));

        $this->transfer($source, $target, ['talents' => [$foreign->id]])->assertSessionHas('error');

        $this->assertNotSame($target->id, $foreign->fresh()->producer_id);
    }

    public function test_les_liens_vers_un_autre_espace_sont_signales(): void
    {
        $target = $this->producer('Cible');
        $movie = $this->movie('Film resté', $this->admin);
        $screening = Screening::create([
            'media_id' => $movie->id, 'kind' => 'seance', 'movie_title' => 'Film resté',
            'cinema_name' => 'Canal Olympia', 'location' => 'Douala', 'country_code' => 'CM', 'starts_at' => now()->addWeek(), 'status' => 'published',
        ]);

        $this->transfer(null, $target, ['screenings' => [$screening->id]])
            ->assertSessionHas('transfer_warnings', fn (array $w) => str_contains($w[0], 'séance'));
    }

    public function test_reserve_a_l_admin(): void
    {
        $producer = $this->producer('Prod');

        $this->actingAs($producer)->get(route('transfers.index'))->assertForbidden();
        $this->actingAs($producer)->post(route('transfers.store'), [])->assertForbidden();
    }
}
