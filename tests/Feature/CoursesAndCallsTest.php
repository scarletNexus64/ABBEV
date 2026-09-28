<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\ProjectCall;
use App\Models\ProjectPledge;
use App\Models\ProjectSubmission;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Cours de cinéma (vidéos, documents) et appels à projets (financement,
 * écriture, musique).
 */
class CoursesAndCallsTest extends TestCase
{
    use RefreshDatabase;

    private function subscribe(User $user, string $tier): void
    {
        $plan = SubscriptionPlan::create(['name' => ucfirst($tier), 'tier' => $tier, 'price' => 5000, 'duration_days' => 30, 'is_active' => true]);
        UserSubscription::create([
            'user_id' => $user->id, 'subscription_plan_id' => $plan->id,
            'starts_at' => now()->subDay(), 'expires_at' => now()->addMonth(), 'status' => 'active',
        ]);
    }

    private function videoCourse(?string $tier): Course
    {
        $course = Course::create(['title' => 'Jouer face caméra', 'type' => 'video', 'required_tier' => $tier, 'is_published' => true]);
        $course->lessons()->create(['title' => 'Aperçu', 'sort_order' => 1, 'is_preview' => true, 'video_provider' => 'url', 'video_url' => 'https://example.com/a.m3u8']);
        $course->lessons()->create(['title' => 'Leçon 2', 'sort_order' => 2, 'video_provider' => 'url', 'video_url' => 'https://example.com/b.m3u8']);

        return $course->load('lessons');
    }

    // ---------------------------------------------------------------- Cours

    public function test_la_fiche_indique_les_lecons_accessibles(): void
    {
        $course = $this->videoCourse('standard');
        $user = User::factory()->create();

        $lessons = $this->actingAs($user, 'sanctum')->getJson("/api/v1/courses/{$course->id}")
            ->assertOk()->assertJsonPath('data.is_unlocked', false)->json('data.lessons');

        $this->assertTrue($lessons[0]['is_accessible'], 'aperçu ouvert à tout compte');
        $this->assertFalse($lessons[1]['is_accessible']);
    }

    public function test_l_acces_a_une_lecon_suit_le_forfait(): void
    {
        $course = $this->videoCourse('standard');
        [$preview, $locked] = $course->lessons->all();
        $user = User::factory()->create();

        $this->getJson("/api/v1/courses/{$course->id}/lessons/{$preview->id}/access")->assertUnauthorized();

        $this->actingAs($user, 'sanctum')->getJson("/api/v1/courses/{$course->id}/lessons/{$preview->id}/access")
            ->assertOk()->assertJsonPath('data.video_url', 'https://example.com/a.m3u8');
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/courses/{$course->id}/lessons/{$locked->id}/access")
            ->assertForbidden()->assertJsonPath('required_tier', 'standard');

        // Premium ouvre ce qui est réservé au Standard.
        $this->subscribe($user, 'premium');
        $this->actingAs($user, 'sanctum')->getJson("/api/v1/courses/{$course->id}/lessons/{$locked->id}/access")->assertOk();
    }

    public function test_une_lecon_document_est_servie_par_url_signee(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('courses/lessons/cours.pdf', '%PDF-1.4 test');
        $course = Course::create(['title' => 'Écrire son court', 'type' => 'document', 'is_published' => true]);
        $lesson = $course->lessons()->create(['title' => 'Chapitre 1', 'sort_order' => 1, 'file_path' => 'courses/lessons/cours.pdf', 'pages' => 3]);

        $url = $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson("/api/v1/courses/{$course->id}/lessons/{$lesson->id}/access")
            ->assertOk()->assertJsonPath('data.type', 'document')->json('data.file_url');

        $this->get($url)->assertOk();
        $this->get(strtok($url, '?'))->assertForbidden();
    }

    public function test_un_cours_non_publie_est_introuvable(): void
    {
        $course = Course::create(['title' => 'Brouillon', 'type' => 'video', 'is_published' => false]);

        $this->assertEmpty($this->getJson('/api/v1/courses')->json('data'));
        $this->getJson("/api/v1/courses/{$course->id}")->assertNotFound();
    }

    // ------------------------------------------------------- Appels à projets

    private function projectCall(string $type, array $extra = []): ProjectCall
    {
        return ProjectCall::create($extra + [
            'type' => $type,
            'target' => $type === 'musique' ? 'cinema' : 'film',
            'title' => 'Appel ' . $type,
            'status' => 'open',
            'closes_at' => now()->addMonth(),
            'currency' => 'XAF',
        ]);
    }

    public function test_une_promesse_respecte_le_minimum_et_la_contrepartie(): void
    {
        $call = $this->projectCall('financement', ['goal_amount' => 1000000, 'min_pledge' => 5000, 'rewards' => [['amount' => 25000, 'title' => 'Affiche', 'description' => '']]]);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/calls/{$call->id}/pledges", ['amount' => 1000, 'phone' => '+237600000000'])
            ->assertStatus(422)->assertJsonValidationErrors('amount');
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/calls/{$call->id}/pledges", ['amount' => 25000, 'phone' => '+237600000000', 'reward_title' => 'Inventée'])
            ->assertStatus(422)->assertJsonValidationErrors('reward_title');
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/calls/{$call->id}/pledges", ['amount' => 25000, 'phone' => '+237600000000', 'reward_title' => 'Affiche'])
            ->assertCreated()->assertJsonPath('participation.status', 'pending');
    }

    public function test_seules_les_promesses_confirmees_remplissent_la_jauge(): void
    {
        $call = $this->projectCall('financement', ['goal_amount' => 100000, 'raised_offline' => 10000]);
        $admin = User::factory()->create(['role' => 'admin']);
        $pledge = ProjectPledge::create(['project_call_id' => $call->id, 'user_id' => User::factory()->create()->id, 'amount' => 40000, 'currency' => 'XAF']);

        $this->getJson("/api/v1/calls/{$call->id}")->assertJsonPath('data.raised_amount', 10000)->assertJsonPath('data.progress_percent', 10);

        $this->actingAs($admin)->patch("/admin/calls/{$call->getRouteKey()}/pledges/{$pledge->id}", ['status' => 'confirmed'])->assertRedirect();

        $this->getJson("/api/v1/calls/{$call->id}")
            ->assertJsonPath('data.raised_amount', 50000)
            ->assertJsonPath('data.progress_percent', 50)
            ->assertJsonPath('data.backers_count', 1);
        $this->assertNotNull($pledge->fresh()->confirmed_at);
    }

    public function test_une_promesse_confirmee_survit_a_la_suppression_du_compte(): void
    {
        $call = $this->projectCall('financement', ['goal_amount' => 100000]);
        $backer = User::factory()->create();
        $pledge = ProjectPledge::create([
            'project_call_id' => $call->id, 'user_id' => $backer->id, 'amount' => 40000,
            'currency' => 'XAF', 'status' => 'confirmed', 'confirmed_at' => now(),
        ]);

        $backer->delete();

        $this->assertNull($pledge->fresh()->user_id, 'détachée du compte, pas effacée');
        $this->getJson("/api/v1/calls/{$call->id}")->assertJsonPath('data.raised_amount', 40000);
    }

    public function test_supprimer_son_compte_efface_ses_scenarios_et_photos(): void
    {
        Storage::fake('local');
        $call = $this->projectCall('ecriture');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->post("/api/v1/calls/{$call->id}/submissions", [
                'title' => 'Le Fleuve', 'synopsis' => 'Un récit.',
                'file' => UploadedFile::fake()->create('scenario.pdf', 200, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();
        $path = ProjectSubmission::first()->file_path;
        Storage::disk('local')->assertExists($path);

        $user->delete();

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseCount('project_submissions', 0);
    }

    public function test_une_candidature_musique_exige_un_lien_d_ecoute(): void
    {
        $call = $this->projectCall('musique');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/calls/{$call->id}/submissions", ['title' => 'Thème'])
            ->assertStatus(422)->assertJsonValidationErrors('link_url');
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/calls/{$call->id}/submissions", ['title' => 'Thème', 'link_url' => 'https://soundcloud.com/x'])
            ->assertCreated();
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/calls/{$call->id}/submissions", ['title' => 'Bis', 'link_url' => 'https://soundcloud.com/y'])
            ->assertStatus(409);
    }

    public function test_une_candidature_ecriture_accepte_un_scenario_pdf(): void
    {
        Storage::fake('local');
        $call = $this->projectCall('ecriture');

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->post("/api/v1/calls/{$call->id}/submissions", [
                'title' => 'La Saison des Mangues',
                'logline' => 'Une adolescente défie sa famille.',
                'file' => UploadedFile::fake()->create('scenario.pdf', 200, 'application/pdf'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $this->assertNotNull($call->submissions()->first()->file_path);
    }

    public function test_un_appel_clos_refuse_toute_participation(): void
    {
        $funding = $this->projectCall('financement', ['goal_amount' => 1000, 'closes_at' => now()->subDay()]);
        $music = $this->projectCall('musique', ['status' => 'closed']);
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')->postJson("/api/v1/calls/{$funding->id}/pledges", ['amount' => 5000, 'phone' => '1'])->assertStatus(422);
        $this->actingAs($user, 'sanctum')->postJson("/api/v1/calls/{$music->id}/submissions", ['title' => 'X', 'link_url' => 'https://x.y'])->assertStatus(422);
    }

    public function test_filtre_des_appels_par_famille(): void
    {
        $this->projectCall('financement', ['goal_amount' => 1000]);
        $this->projectCall('musique');
        $this->projectCall('ecriture', ['status' => 'draft']);

        $this->assertSame(['musique'], collect($this->getJson('/api/v1/calls?type=musique')->json('data'))->pluck('type')->all());
        $this->assertCount(2, $this->getJson('/api/v1/calls')->json('data'), 'le brouillon reste caché');
    }
}
