<?php

namespace Tests\Feature;

use App\Models\AwardCategory;
use App\Models\AwardEdition;
use App\Models\AwardNominee;
use App\Models\User;
use App\Services\AwardVotingService;
use App\Support\AwardCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Lions Head Awards : grille cat.md, vote ouvert (un compte = une voix par
 * prix, modifiable jusqu'à la clôture), résultats tenus secrets jusqu'à la
 * publication du palmarès.
 */
class LionsHeadAwardsTest extends TestCase
{
    use RefreshDatabase;

    private AwardEdition $edition;

    private AwardCategory $category;

    /** @var list<AwardNominee> */
    private array $nominees = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->edition = AwardEdition::create([
            'name' => 'Lions Head Awards 2026',
            'year' => 2026,
            'voting_starts_at' => now()->subDay(),
            'voting_ends_at' => now()->addWeek(),
            'is_current' => true,
        ]);
        $this->category = $this->edition->categories()->create([
            'name' => 'Meilleure actrice', 'slug' => 'meilleure-actrice-cinema', 'scope' => 'cinema', 'nominee_type' => 'person',
        ]);
        foreach (['Awa', 'Grace', 'Fatou'] as $i => $name) {
            $this->nominees[] = $this->category->nominees()->create(['name' => $name, 'sort_order' => $i]);
        }
    }

    public function test_la_grille_cat_md_compte_29_prix_et_reste_idempotente(): void
    {
        $edition = AwardEdition::create(['name' => 'Édition test', 'year' => 2027]);

        $this->assertSame(29, AwardCatalog::applyTo($edition));
        $this->assertSame(0, AwardCatalog::applyTo($edition), 'une seconde application ne duplique rien');

        $scopes = $edition->categories()->get()->countBy('scope');
        $this->assertSame(13, $scopes['cinema']);
        $this->assertSame(13, $scopes['television']);
        $this->assertSame(3, $scopes['metiers']);
    }

    public function test_voter_exige_un_compte(): void
    {
        $this->postJson("/api/v1/awards/nominees/{$this->nominees[0]->id}/vote")->assertUnauthorized();
    }

    public function test_un_compte_une_voix_par_prix_et_changement_de_vote(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/awards/nominees/{$this->nominees[0]->id}/vote")
            ->assertOk()->assertJsonPath('nominee_id', $this->nominees[0]->id);

        // Même nommé : idempotent.
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/awards/nominees/{$this->nominees[0]->id}/vote")->assertOk();
        $this->assertSame(1, $this->nominees[0]->fresh()->votes_count);

        // Changement d'avis : la voix est DÉPLACÉE, jamais dupliquée.
        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/awards/nominees/{$this->nominees[1]->id}/vote")
            ->assertOk();

        $this->assertSame(0, $this->nominees[0]->fresh()->votes_count);
        $this->assertSame(1, $this->nominees[1]->fresh()->votes_count);
        $this->assertDatabaseCount('award_votes', 1);
    }

    public function test_le_palmares_ne_compte_plus_les_votes_d_un_compte_supprime(): void
    {
        [$awa, $grace] = [$this->nominees[0], $this->nominees[1]];
        $leaving = User::factory()->create();
        foreach ([$leaving, User::factory()->create()] as $voter) {
            $this->actingAs($voter, 'sanctum')->postJson("/api/v1/awards/nominees/{$awa->id}/vote")->assertOk();
        }
        foreach (User::factory()->count(3)->create() as $voter) {
            $this->actingAs($voter, 'sanctum')->postJson("/api/v1/awards/nominees/{$grace->id}/vote")->assertOk();
        }

        // Les bulletins partent avec le compte (cascade), pas le compteur.
        $leaving->delete();
        $this->assertSame(2, $awa->fresh()->votes_count);

        app(AwardVotingService::class)->publishResults($this->edition);

        $this->assertSame(1, $awa->fresh()->votes_count, 'recompté depuis les bulletins');
        $this->assertSame(3, $grace->fresh()->votes_count);
        $this->assertTrue($grace->fresh()->is_winner);
        $this->assertFalse($awa->fresh()->is_winner);
    }

    public function test_vote_refuse_hors_periode(): void
    {
        $this->edition->update(['voting_ends_at' => now()->subMinute()]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/v1/awards/nominees/{$this->nominees[0]->id}/vote")
            ->assertStatus(422);
    }

    public function test_les_resultats_restent_secrets_jusqu_a_la_publication(): void
    {
        $voting = app(AwardVotingService::class);
        foreach ([0, 0, 1] as $pick) {
            $voting->cast(User::factory()->create(), $this->nominees[$pick]);
        }

        $before = $this->getJson('/api/v1/awards/current')->assertOk()->json('data.categories.0.nominees');
        $this->assertNull($before[0]['votes']);
        $this->assertNull($before[0]['percent']);
        $this->assertFalse($before[0]['is_winner']);

        $voting->publishResults($this->edition->fresh());

        $after = collect($this->getJson('/api/v1/awards/current')->json('data.categories.0.nominees'))->keyBy('name');
        $this->assertTrue($after['Awa']['is_winner'], 'le plus voté l\'emporte');
        $this->assertSame(2, $after['Awa']['votes']);
        $this->assertEqualsWithDelta(66.7, $after['Awa']['percent'], 0.1);
        $this->assertFalse($after['Grace']['is_winner']);
    }

    public function test_le_choix_du_jury_prime_sur_le_vote(): void
    {
        $voting = app(AwardVotingService::class);
        $voting->cast(User::factory()->create(), $this->nominees[0]);
        $this->nominees[2]->update(['is_winner' => true]);

        $voting->publishResults($this->edition->fresh());

        $this->assertFalse($this->nominees[0]->fresh()->is_winner);
        $this->assertTrue($this->nominees[2]->fresh()->is_winner);
    }

    public function test_un_prix_sans_nomme_n_est_pas_expose(): void
    {
        $this->edition->categories()->create([
            'name' => 'Meilleur son', 'slug' => 'meilleur-son-cinema', 'scope' => 'cinema', 'nominee_type' => 'person',
        ]);

        $names = collect($this->getJson('/api/v1/awards/current')->json('data.categories'))->pluck('name');

        $this->assertContains('Meilleure actrice', $names);
        $this->assertNotContains('Meilleur son', $names);
    }

    public function test_mes_votes_sont_renvoyes_a_l_utilisateur_connecte(): void
    {
        $user = User::factory()->create();
        app(AwardVotingService::class)->cast($user, $this->nominees[1]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/awards/current')
            ->assertOk()
            ->assertJsonPath('data.my_votes_count', 1)
            ->assertJsonPath('data.categories.0.my_vote', $this->nominees[1]->id);
    }
}
