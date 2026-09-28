<?php

namespace Tests\Feature;

use App\Models\AwardEdition;
use App\Models\Category;
use App\Models\Media;
use App\Models\Reservation;
use App\Models\Screening;
use App\Models\TicketType;
use App\Models\User;
use App\Services\TicketCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * Billetterie (séances et codes cinéma, contrôle à l'entrée) et actions
 * clés de l'admin : genres, sélections, éditions des Awards.
 */
class TicketingAndAdminModulesTest extends TestCase
{
    use RefreshDatabase;

    private function offer(string $kind, array $extra = []): Screening
    {
        $screening = Screening::create($extra + [
            'kind' => $kind,
            'movie_title' => $kind === 'code' ? 'Code cinéma — Duo' : 'Dune',
            'cinema_name' => 'Cinéma ABBEV Akwa',
            'location' => 'Douala',
            'country_code' => 'CM',
            'starts_at' => $kind === 'code' ? now()->subDay() : now()->addDays(2),
            'valid_until' => $kind === 'code' ? now()->addMonth() : null,
            'status' => 'published',
        ]);
        TicketType::create(['screening_id' => $screening->id, 'name' => 'Standard', 'price' => 3000, 'currency' => 'XAF', 'capacity' => 50]);

        return $screening;
    }

    private function confirmedReservation(Screening $screening, int $quantity = 2): Reservation
    {
        return Reservation::create([
            'reference' => 'ABBEV-' . strtoupper(Str::random(8)),
            'user_id' => User::factory()->create()->id,
            'screening_id' => $screening->id,
            'ticket_type_id' => $screening->ticketTypes()->first()->id,
            'quantity' => $quantity,
            'unit_price' => 3000,
            'total_amount' => 3000 * $quantity,
            'currency' => 'XAF',
            'status' => 'confirmed',
            'confirmed_at' => now(),
        ]);
    }

    public function test_seances_et_codes_sont_deux_listes_distinctes(): void
    {
        $this->offer('seance');
        $this->offer('code');
        $this->offer('code', ['movie_title' => 'Code expiré', 'valid_until' => now()->subDay()]);

        $seances = $this->getJson('/api/v1/screenings')->assertOk()->json('data');
        $codes = $this->getJson('/api/v1/screenings?kind=code')->assertOk()->json('data');

        $this->assertSame(['seance'], collect($seances)->pluck('kind')->unique()->values()->all());
        $this->assertSame(['Code cinéma — Duo'], collect($codes)->pluck('movie_title')->all(), 'le code expiré n\'est plus en vente');
        $this->assertNotNull($codes[0]['valid_until']);
    }

    public function test_une_seance_saisie_a_20h_a_douala_reste_a_20h_sur_le_telephone(): void
    {
        $day = now()->addDays(5)->format('Y-m-d');
        $screening = $this->offer('seance', ['starts_at' => "{$day}T20:00"]);

        $this->assertSame("{$day} 19:00:00", (string) DB::table('screenings')->where('id', $screening->id)->value('starts_at'), 'stockée en UTC');
        $this->assertSame('20:00', $screening->fresh()->starts_at->format('H:i'), "relue à 20h dans l'admin");
        $this->assertSame("{$day}T20:00:00+01:00", $this->getJson('/api/v1/screenings')->assertOk()->json('data.0.starts_at'));
    }

    public function test_un_code_duo_se_valide_en_deux_fois_puis_plus_jamais(): void
    {
        $reservation = $this->confirmedReservation($this->offer('code'), 2);
        $staff = User::factory()->create(['role' => 'admin']);
        $service = app(TicketCheckService::class);

        $this->assertSame($reservation->id, $service->find(Str::after($reservation->reference, 'ABBEV-'))?->id, 'saisie sans préfixe acceptée');

        $service->redeem($reservation, 1, $staff);
        $this->assertSame(1, $reservation->fresh()->remainingEntries());

        $service->redeem($reservation, 5, $staff);
        $this->assertSame(0, $reservation->fresh()->remainingEntries(), 'borné au reste disponible');

        $this->expectException(RuntimeException::class);
        $service->redeem($reservation->fresh(), 1, $staff);
    }

    public function test_un_code_expire_ou_un_billet_impaye_est_refuse(): void
    {
        $service = app(TicketCheckService::class);

        $expired = $this->confirmedReservation($this->offer('code', ['valid_until' => now()->subDay()]));
        $this->assertFalse($service->verdict($expired->load('screening'))['ok']);

        $unpaid = $this->confirmedReservation($this->offer('seance'));
        $unpaid->update(['status' => 'pending']);
        $this->assertFalse($service->verdict($unpaid->fresh()->load('screening'))['ok']);
    }

    public function test_un_billet_deja_utilise_ne_peut_plus_etre_annule(): void
    {
        $reservation = $this->confirmedReservation($this->offer('code'), 2);
        app(TicketCheckService::class)->redeem($reservation, 1, User::factory()->create(['role' => 'admin']));

        $this->actingAs($reservation->user, 'sanctum')
            ->postJson("/api/v1/reservations/{$reservation->id}/cancel")
            ->assertStatus(422);

        $this->assertSame('confirmed', $reservation->fresh()->status, 'la place consommée reste vendue');
    }

    public function test_le_controle_des_billets_depuis_l_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $reservation = $this->confirmedReservation($this->offer('seance'), 3);

        $this->actingAs($admin)->get('/admin/tickets/check?code=' . $reservation->reference)
            ->assertOk()->assertSee('Billet valable');

        $this->actingAs($admin)->post("/admin/tickets/{$reservation->id}/redeem", ['entries' => 2])->assertRedirect();

        $this->assertSame(2, $reservation->fresh()->redeemed_quantity);
        $this->assertSame($admin->id, $reservation->fresh()->redeemed_by);
    }

    public function test_supprimer_un_genre_non_vide_exige_une_reaffectation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $western = Category::create(['name' => 'Western', 'slug' => 'western', 'family' => 'genre']);
        $drame = Category::create(['name' => 'Drame', 'slug' => 'drame', 'family' => 'genre']);
        $film = Media::create(['category_id' => $western->id, 'type' => 'movie', 'title' => 'Le Duel', 'slug' => 'le-duel', 'moderation_status' => 'approved']);

        $this->actingAs($admin)->delete("/categories/{$western->getRouteKey()}")->assertRedirect();
        $this->assertModelExists($western);
        $this->assertModelExists($film);

        $this->actingAs($admin)->delete("/categories/{$western->getRouteKey()}", ['move_to' => $drame->id])->assertRedirect();
        $this->assertModelMissing($western);
        $this->assertSame($drame->id, $film->fresh()->category_id, 'le film a survécu au changement de genre');
    }

    public function test_creer_une_edition_genere_la_grille_et_devient_courante(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/admin/awards', [
            'name' => 'Lions Head Awards 2027', 'year' => 2027,
            'voting_starts_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'voting_ends_at' => now()->addMonth()->format('Y-m-d\TH:i'),
            'is_current' => 1, 'apply_template' => 1,
        ])->assertRedirect();

        $edition = AwardEdition::where('year', 2027)->firstOrFail();
        $this->assertTrue($edition->is_current);
        $this->assertSame(29, $edition->categories()->count());
        $this->assertSame('upcoming', $edition->status());
    }

    public function test_ajouter_un_nomme_depuis_le_catalogue(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $edition = AwardEdition::create(['name' => 'LHA', 'year' => 2026]);
        $category = $edition->categories()->create(['name' => 'Meilleur film', 'slug' => 'meilleur-film', 'scope' => 'cinema', 'nominee_type' => 'media']);
        $genre = Category::create(['name' => 'Drame', 'slug' => 'drame', 'family' => 'genre']);
        $film = Media::create(['category_id' => $genre->id, 'type' => 'movie', 'title' => 'Le Fleuve', 'slug' => 'le-fleuve', 'release_year' => 2025, 'moderation_status' => 'approved']);

        $this->actingAs($admin)->post("/admin/award-categories/{$category->getRouteKey()}/nominees", [
            'source' => 'media', 'media_id' => $film->id,
        ])->assertRedirect();

        $nominee = $category->nominees()->firstOrFail();
        $this->assertSame('Le Fleuve', $nominee->name, 'nom repris de l\'œuvre');
        $this->assertSame('2025', $nominee->subtitle);
    }

    public function test_les_pages_des_modules_s_affichent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        AwardEdition::create(['name' => 'LHA', 'year' => 2026, 'is_current' => true]);

        foreach ([
            '/admin/dashboard', '/categories', '/admin/rubriques', '/admin/rubriques/a-la-une',
            '/admin/talents', '/admin/talents/create', '/admin/agents', '/admin/castings', '/admin/castings/create',
            '/admin/awards', '/admin/awards/create', '/admin/courses', '/admin/courses/create',
            '/admin/calls', '/admin/calls/create?type=musique', '/screenings?kind=code', '/admin/tickets/check',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}
