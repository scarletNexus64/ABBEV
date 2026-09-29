<?php

namespace Tests\Feature;

use App\Mail\TeamInvitationMail;
use App\Models\AwardEdition;
use App\Models\Category;
use App\Models\Course;
use App\Models\Media;
use App\Models\Talent;
use App\Models\User;
use App\Models\WatchHistory;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Espaces producteurs : chaque producteur gère tous les modules sur SES
 * données, invite son équipe et lui délègue des modules ; l'admin voit tout.
 */
class ProducerWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function producer(string $name = 'Prod'): User
    {
        return User::factory()->create(['role' => 'producer', 'name' => $name]);
    }

    private function member(User $producer, array $permissions): User
    {
        $member = User::factory()->create(['role' => 'producer']);
        $member->forceFill(['producer_id' => $producer->id, 'permissions' => $permissions])->save();

        return $member;
    }

    private function talent(string $first, ?User $owner): Talent
    {
        $talent = Talent::create([
            'kind' => 'acteur', 'first_name' => $first, 'last_name' => 'Test',
            'tier' => 'B', 'profession' => 'acteur', 'is_published' => true,
        ]);
        $talent->forceFill(['producer_id' => $owner?->id])->save();

        return $talent;
    }

    private function movie(string $title, ?User $owner): Media
    {
        return Media::create([
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

    /* ---------------------------------------------------------------
     |  Cloisonnement
     * --------------------------------------------------------------- */

    public function test_un_producteur_ne_voit_que_les_donnees_de_son_espace(): void
    {
        $alpha = $this->producer('Alpha');
        $beta = $this->producer('Beta');
        $this->talent('Alice', $alpha);
        $bob = $this->talent('Bobby', $beta);
        $this->talent('Plateforme', null);

        $this->actingAs($alpha)->get('/admin/talents')
            ->assertOk()->assertSee('Alice')->assertDontSee('Bobby')->assertDontSee('Plateforme');

        // Un modèle d'un autre espace passé dans l'URL n'existe pas.
        $this->actingAs($alpha)->get(route('talents.edit', $bob))->assertNotFound();
        $this->actingAs($alpha)->delete(route('talents.destroy', $bob))->assertNotFound();
        $this->assertModelExists($bob);

        // L'admin voit tout.
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/talents')
            ->assertOk()->assertSee('Alice')->assertSee('Bobby')->assertSee('Plateforme');
    }

    public function test_les_modeles_enfants_heritent_du_cloisonnement(): void
    {
        $alpha = $this->producer('Alpha');
        $beta = $this->producer('Beta');
        $course = Course::create(['title' => 'Cours de Beta', 'type' => 'video', 'is_published' => true]);
        $course->forceFill(['producer_id' => $beta->id])->save();
        $lesson = $course->lessons()->create(['title' => 'Leçon', 'sort_order' => 1, 'video_provider' => 'url', 'video_url' => 'https://example.com/a.m3u8']);

        $this->actingAs($alpha)->delete(route('courses.lessons.destroy', $lesson))->assertNotFound();
        $this->assertModelExists($lesson);
    }

    public function test_une_creation_est_rattachee_a_l_espace_du_producteur(): void
    {
        $producer = $this->producer();
        $member = $this->member($producer, ['courses']);

        $this->actingAs($member)->post(route('courses.store'), [
            'title' => 'Cours créé par un membre',
            'type' => 'video',
            'discipline' => 'realisation',
            'level' => 'debutant',
            'is_published' => '0',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $course = Course::where('title', 'Cours créé par un membre')->firstOrFail();
        $this->assertSame($producer->id, $course->producer_id, "rattaché au producteur, pas au membre");
    }

    public function test_impossible_de_rattacher_une_donnee_d_un_autre_espace(): void
    {
        $alpha = $this->producer('Alpha');
        $beta = $this->producer('Beta');
        $foreignMovie = $this->movie('Film de Beta', $beta);

        $this->actingAs($alpha)->post(route('talents.store'), [
            'kind' => 'acteur', 'first_name' => 'Nouveau', 'last_name' => 'Talent',
            'tier' => 'B', 'profession' => 'acteur',
            'credits' => [['title' => 'X', 'media_id' => $foreignMovie->id]],
        ])->assertSessionHasErrors('credits.0.media_id');
    }

    public function test_le_contexte_est_leve_apres_la_requete(): void
    {
        $producer = $this->producer();
        $this->actingAs($producer)->get('/admin/talents')->assertOk();

        $this->assertNull(Workspace::id());
    }

    public function test_l_api_mobile_n_est_pas_cloisonnee(): void
    {
        $this->talent('Alice', $this->producer('Alpha'));
        $this->talent('Bobby', $this->producer('Beta'));

        $names = collect($this->getJson('/api/v1/talents')->assertOk()->json('data'))->pluck('first_name');
        $this->assertEqualsCanonicalizing(['Alice', 'Bobby'], $names->all());
    }

    /* ---------------------------------------------------------------
     |  Permissions des membres
     * --------------------------------------------------------------- */

    public function test_un_membre_n_accede_qu_aux_modules_delegues(): void
    {
        $producer = $this->producer();
        $member = $this->member($producer, ['talents', 'tickets']);

        $this->actingAs($member)->get('/admin/talents')->assertOk();
        $this->actingAs($member)->get('/admin/tickets/check')->assertOk();
        $this->actingAs($member)->get('/admin/courses')->assertForbidden();
        $this->actingAs($member)->get('/screenings')->assertForbidden();
        $this->actingAs($member)->get('/media')->assertForbidden();

        // La navigation ne montre que ses modules.
        $this->actingAs($member)->get(route('admin.dashboard'))->assertOk()
            ->assertSee(route('talents.index'), false)
            ->assertDontSee(route('courses.index'), false)
            ->assertDontSee(route('team.index'), false);
    }

    public function test_un_membre_voit_les_donnees_de_son_producteur(): void
    {
        $producer = $this->producer();
        $member = $this->member($producer, ['talents']);
        $this->talent('Alice', $producer);

        $this->actingAs($member)->get('/admin/talents')->assertOk()->assertSee('Alice');
    }

    public function test_seul_l_admin_accede_a_la_gestion_plateforme(): void
    {
        $producer = $this->producer();

        foreach (['/admin/users', '/admin/producers', '/admin/configuration', '/admin/rubriques', '/categories'] as $url) {
            $this->actingAs($producer)->get($url)->assertForbidden();
        }
    }

    /* ---------------------------------------------------------------
     |  Équipe
     * --------------------------------------------------------------- */

    public function test_le_producteur_invite_un_nouveau_membre(): void
    {
        Mail::fake();
        $producer = $this->producer();

        $this->actingAs($producer)->post(route('team.store'), [
            'name' => 'Awa Monteuse',
            'email' => 'Awa@Example.com',
            'permissions' => ['contents', 'moderation'],
        ])->assertRedirect(route('team.index'));

        $member = User::where('email', 'awa@example.com')->firstOrFail();
        $this->assertSame('producer', $member->role);
        $this->assertSame($producer->id, $member->producer_id);
        $this->assertSame(['contents', 'moderation'], $member->permissions);
        Mail::assertSent(TeamInvitationMail::class, fn ($mail) => $mail->hasTo('awa@example.com') && $mail->password !== null);
    }

    public function test_un_abonne_existant_est_rattache_sans_changer_son_mot_de_passe(): void
    {
        Mail::fake();
        $producer = $this->producer();
        $subscriber = User::factory()->create(['role' => 'user', 'email' => 'fan@example.com', 'password' => Hash::make('secret-app')]);

        $this->actingAs($producer)->post(route('team.store'), [
            'name' => 'Ignoré', 'email' => 'fan@example.com', 'permissions' => ['tickets'],
        ])->assertRedirect(route('team.index'));

        $subscriber->refresh();
        $this->assertSame($producer->id, $subscriber->producer_id);
        $this->assertTrue(Hash::check('secret-app', $subscriber->password));
        Mail::assertSent(TeamInvitationMail::class, fn ($mail) => $mail->password === null);
    }

    public function test_un_compte_du_panel_ne_peut_pas_etre_invite(): void
    {
        $producer = $this->producer();
        $other = $this->producer('Autre');

        $this->actingAs($producer)->post(route('team.store'), [
            'name' => 'X', 'email' => $other->email, 'permissions' => ['contents'],
        ])->assertSessionHasErrors('email');

        $this->assertNull($other->fresh()->producer_id);
    }

    public function test_seul_le_titulaire_gere_l_equipe(): void
    {
        $producer = $this->producer();
        $member = $this->member($producer, ['contents']);

        $this->actingAs($member)->get(route('team.index'))->assertForbidden();
        $this->actingAs($member)->put(route('team.update', $member), ['permissions' => array_keys(User::MODULES)])
            ->assertForbidden();
        $this->assertSame(['contents'], $member->fresh()->permissions);
    }

    public function test_le_producteur_modifie_et_retire_un_membre_de_son_equipe_seulement(): void
    {
        $producer = $this->producer();
        $member = $this->member($producer, ['contents']);
        $foreign = $this->member($this->producer('Autre'), ['contents']);

        $this->actingAs($producer)->put(route('team.update', $member), ['permissions' => ['awards', 'calls']])
            ->assertRedirect(route('team.index'));
        $this->assertSame(['awards', 'calls'], $member->fresh()->permissions);

        $this->actingAs($producer)->put(route('team.update', $foreign), ['permissions' => ['awards']])->assertNotFound();
        $this->actingAs($producer)->delete(route('team.destroy', $foreign))->assertNotFound();

        $this->actingAs($producer)->delete(route('team.destroy', $member))->assertRedirect(route('team.index'));
        $member->refresh();
        $this->assertSame('user', $member->role);
        $this->assertNull($member->producer_id);
    }

    public function test_supprimer_un_producteur_retire_son_equipe_du_panel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $producer = $this->producer();
        $member = $this->member($producer, ['contents']);
        $talent = $this->talent('Alice', $producer);

        $this->actingAs($admin)->delete(route('producers.destroy', $producer))->assertRedirect();

        $this->assertSame('user', $member->fresh()->role);
        $this->assertNull($talent->fresh()->producer_id, 'la donnée revient à la plateforme');
    }

    public function test_la_liste_admin_des_producteurs_exclut_les_membres(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $producer = $this->producer('Titulaire');
        $member = $this->member($producer, ['contents']);
        $member->update(['name' => 'Membre Discret']);

        $this->actingAs($admin)->get(route('producers.index'))
            ->assertOk()->assertSee('Titulaire')->assertDontSee('Membre Discret');
    }

    public function test_les_pages_de_l_espace_s_affichent(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $producer = $this->producer();
        $member = $this->member($producer, ['contents', 'awards']);

        foreach ([route('team.index'), route('team.create'), route('team.edit', $member), route('awards.create'), route('audience.index'), route('moderation.index')] as $url) {
            $this->actingAs($producer)->get($url)->assertOk();
        }
        $this->actingAs($member)->get(route('awards.create'))->assertOk()->assertDontSee('name="is_current"', false);

        $this->actingAs($admin)->get(route('producers.create'))->assertOk()->assertSee('Lions Head Awards');
        $this->actingAs($admin)->get(route('producers.show', $producer))->assertOk()->assertSee($member->email);
        $this->actingAs($admin)->get(route('awards.create'))->assertOk()->assertSee('name="is_current"', false);
    }

    /* ---------------------------------------------------------------
     |  Awards & audience
     * --------------------------------------------------------------- */

    public function test_seul_l_admin_choisit_l_edition_affichee_dans_l_app(): void
    {
        $current = AwardEdition::create(['name' => 'Édition plateforme', 'year' => 2026, 'is_current' => true]);
        $producer = $this->producer();

        $this->actingAs($producer)->post(route('awards.store'), [
            'name' => 'Édition du producteur', 'year' => 2027, 'is_current' => '1', 'apply_template' => '0',
        ])->assertRedirect();

        $edition = AwardEdition::where('name', 'Édition du producteur')->firstOrFail();
        $this->assertFalse($edition->is_current);
        $this->assertTrue($current->fresh()->is_current);

        $this->actingAs($producer)->post(route('awards.current', $edition))->assertForbidden();
    }

    public function test_l_audience_ne_montre_que_les_spectateurs_des_contenus_de_l_espace(): void
    {
        $producer = $this->producer();
        $mine = $this->movie('Mon Film', $producer);
        $theirs = $this->movie('Autre Film', $this->producer('Autre'));

        $fan = User::factory()->create(['name' => 'Spectateur Fidèle']);
        $stranger = User::factory()->create(['name' => 'Spectateur Ailleurs']);
        WatchHistory::create(['user_id' => $fan->id, 'media_id' => $mine->id, 'watched_seconds' => 3600]);
        WatchHistory::create(['user_id' => $stranger->id, 'media_id' => $theirs->id, 'watched_seconds' => 60]);

        $this->actingAs($producer)->get(route('audience.index'))
            ->assertOk()
            ->assertSee('Spectateur Fidèle')
            ->assertDontSee('Spectateur Ailleurs')
            ->assertDontSee($fan->email);
    }
}
