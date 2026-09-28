<?php

namespace Tests\Feature;

use App\Models\Agent;
use App\Models\CastingApplication;
use App\Models\CastingCall;
use App\Models\CastingRole;
use App\Models\Talent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Talents & casting : annuaire trié par rang (A² → D), agents par
 * sous-catégorie, annonces et candidatures depuis l'app, revue dans l'admin.
 */
class CastingAndTalentsTest extends TestCase
{
    use RefreshDatabase;

    private function talent(string $first, string $tier, array $extra = []): Talent
    {
        return Talent::create($extra + [
            'kind' => 'acteur', 'first_name' => $first, 'last_name' => 'Test',
            'tier' => $tier, 'profession' => 'acteur', 'is_published' => true,
        ]);
    }

    private function openCall(array $extra = []): CastingCall
    {
        $call = CastingCall::create($extra + [
            'title' => 'Série « Les Héritiers »', 'project_title' => 'Les Héritiers',
            'status' => 'open', 'deadline_at' => now()->addWeek(), 'published_at' => now(),
        ]);
        $call->roles()->create(['name' => 'Nadia', 'kind' => 'acteur', 'gender' => 'femme', 'age_min' => 22, 'age_max' => 30]);

        return $call;
    }

    public function test_l_annuaire_trie_par_rang_et_masque_les_brouillons(): void
    {
        $this->talent('Delta', 'D');
        $this->talent('Icone', 'A2');
        $this->talent('Bravo', 'B');
        $this->talent('Cache', 'A1', ['is_published' => false]);

        $names = collect($this->getJson('/api/v1/talents')->assertOk()->json('data'))->pluck('first_name');

        $this->assertSame(['Icone', 'Bravo', 'Delta'], $names->all());
    }

    public function test_filtre_par_type_et_par_rang(): void
    {
        $this->talent('Acteur', 'B');
        $this->talent('Opérateur', 'B', ['kind' => 'technicien', 'profession' => 'directeur-photo']);

        $tech = $this->getJson('/api/v1/talents?kind=technicien&tier=B')->assertOk()->json('data');

        $this->assertCount(1, $tech);
        $this->assertSame('directeur-photo', $tech[0]['profession']);
    }

    public function test_fiche_complete_avec_agent_et_filmographie(): void
    {
        $agent = Agent::create(['name' => 'Rose Kamdem', 'represents' => 'acteurs', 'is_published' => true]);
        $talent = $this->talent('Awa', 'A2', ['agent_id' => $agent->id, 'bio' => 'Quarante ans de carrière.']);
        $talent->credits()->create(['title' => 'Terre Rouge', 'year' => 2008, 'role' => 'Ndèye']);

        $this->getJson("/api/v1/talents/{$talent->id}")
            ->assertOk()
            ->assertJsonPath('data.bio', 'Quarante ans de carrière.')
            ->assertJsonPath('data.agent.name', 'Rose Kamdem')
            ->assertJsonPath('data.credits.0.title', 'Terre Rouge');
    }

    public function test_un_agent_mixte_apparait_dans_les_deux_sous_categories(): void
    {
        Agent::create(['name' => 'Agent acteurs', 'represents' => 'acteurs', 'is_published' => true]);
        Agent::create(['name' => 'Agent mixte', 'represents' => 'mixte', 'is_published' => true]);
        Agent::create(['name' => 'Agent techniciens', 'represents' => 'techniciens', 'is_published' => true]);

        $tech = collect($this->getJson('/api/v1/agents?represents=techniciens')->json('data'))->pluck('name');

        $this->assertEqualsCanonicalizing(['Agent mixte', 'Agent techniciens'], $tech->all());
    }

    public function test_candidature_unique_par_role_avec_photo_privee(): void
    {
        Storage::fake('local');
        $call = $this->openCall();
        $role = $call->roles->first();
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->post("/api/v1/casting-roles/{$role->id}/apply", [
                'full_name' => 'Nadège Ewane', 'email' => 'nadege@example.com', 'age' => 24,
                'photo' => UploadedFile::fake()->image('portrait.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('application.status', 'pending');

        $application = CastingApplication::first();
        Storage::disk('local')->assertExists($application->photo_path);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/v1/casting-roles/{$role->id}/apply", ['full_name' => 'Nadège Ewane', 'email' => 'nadege@example.com'])
            ->assertStatus(409);

        // La fiche de l'annonce indique désormais l'état de la candidature.
        $this->actingAs($user, 'sanctum')
            ->getJson("/api/v1/casting-calls/{$call->id}")
            ->assertJsonPath('data.roles.0.my_application_status', 'pending');
    }

    public function test_candidature_refusee_apres_la_date_limite(): void
    {
        $call = $this->openCall(['deadline_at' => now()->subHour()]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/v1/casting-roles/{$call->roles->first()->id}/apply", ['full_name' => 'X', 'email' => 'x@example.com'])
            ->assertStatus(422);
    }

    public function test_un_brouillon_reste_invisible(): void
    {
        $draft = $this->openCall(['status' => 'draft', 'title' => 'Brouillon']);

        $this->assertEmpty($this->getJson('/api/v1/casting-calls')->json('data'));
        $this->getJson("/api/v1/casting-calls/{$draft->id}")->assertNotFound();
    }

    public function test_l_admin_cree_une_annonce_avec_ses_roles_et_evalue_une_candidature(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/admin/castings', [
            'title' => 'Long métrage « Le Fleuve »', 'project_title' => 'Le Fleuve', 'project_type' => 'film',
            'compensation' => 'remunere', 'status' => 'open', 'is_featured' => 0,
            'roles' => [
                ['name' => 'Samuel', 'kind' => 'acteur', 'importance' => 'principal', 'gender' => 'homme', 'positions' => 1],
                ['name' => 'Ingénieur du son', 'kind' => 'technicien', 'profession' => 'ingenieur-son', 'gender' => 'indifferent', 'positions' => 1],
                ['name' => ''],
            ],
        ])->assertRedirect();

        $call = CastingCall::where('project_title', 'Le Fleuve')->firstOrFail();
        $this->assertSame(['Samuel', 'Ingénieur du son'], $call->roles()->pluck('name')->all(), 'la ligne vide est ignorée');
        $this->assertNotNull($call->published_at);

        $application = CastingApplication::create([
            'casting_call_id' => $call->id, 'casting_role_id' => $call->roles()->first()->id,
            'user_id' => User::factory()->create()->id, 'full_name' => 'Koffi Mensah', 'email' => 'k@example.com',
        ]);

        $this->actingAs($admin)
            ->patch("/admin/castings/{$call->getRouteKey()}/applications/{$application->id}", ['status' => 'shortlisted', 'admin_note' => 'Très bon essai'])
            ->assertRedirect();

        $this->assertSame('shortlisted', $application->fresh()->status);
        $this->assertNotNull($application->fresh()->reviewed_at);
    }

    public function test_l_admin_cree_une_fiche_talent_avec_sa_filmographie(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post('/admin/talents', [
            'kind' => 'technicien', 'first_name' => 'Sandrine', 'last_name' => 'Abena', 'tier' => 'A1',
            'profession' => 'directeur-photo', 'languages' => 'Français, Anglais, français',
            'is_published' => 1, 'is_featured' => 0,
            'credits' => [['title' => 'Wouri Blues', 'year' => 2019, 'role' => 'Image'], ['title' => '']],
        ])->assertRedirect();

        $talent = Talent::where('last_name', 'Abena')->firstOrFail();
        $this->assertSame('directeur-photo', $talent->profession);
        $this->assertSame(['Français', 'Anglais'], $talent->languages, 'doublons retirés');
        $this->assertSame(1, $talent->credits()->count());
    }

    public function test_les_pages_talents_sont_reservees_aux_admins(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'producer']))
            ->get('/admin/talents')
            ->assertForbidden();
    }
}
