<?php

namespace Tests\Feature;

use App\Mail\ProducerCredentialsMail;
use App\Models\Category;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Création / renvoi des comptes producteur : le mot de passe est généré puis
 * ENVOYÉ PAR EMAIL. L'admin ne le voit pas (sauf fallback si l'envoi échoue).
 */
class ProducerCredentialsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_creation_envoie_les_identifiants_par_email(): void
    {
        Mail::fake();
        $admin = $this->admin();

        $res = $this->actingAs($admin)->post(route('producers.store'), [
            'name'  => 'Studio Lumière',
            'email' => 'studio@example.com',
        ]);

        $res->assertRedirect(route('producers.index'));
        // L'admin ne voit PAS le mot de passe : pas de flash new_producer.
        $res->assertSessionMissing('new_producer');
        $res->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'email' => 'studio@example.com',
            'role'  => 'producer',
        ]);

        Mail::assertSent(ProducerCredentialsMail::class, function ($mail) {
            return $mail->hasTo('studio@example.com')
                && $mail->producerEmail === 'studio@example.com'
                && strlen($mail->password) >= 12;
        });
    }

    public function test_fallback_affiche_le_mot_de_passe_si_email_echoue(): void
    {
        $admin = $this->admin();

        // Simule un échec d'envoi (SMTP down, config manquante, etc.).
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP down'));

        $res = $this->actingAs($admin)->post(route('producers.store'), [
            'name'  => 'Studio Panne',
            'email' => 'panne@example.com',
        ]);

        $res->assertRedirect(route('producers.index'));
        // Fallback : le mot de passe est renvoyé à l'admin pour transmission manuelle.
        $res->assertSessionHas('new_producer');
        $np = session('new_producer');
        $this->assertSame('panne@example.com', $np['email']);
        $this->assertTrue($np['mail_failed']);
        $this->assertNotEmpty($np['password']);

        // Le compte a quand même été créé (le producteur n'est pas bloqué).
        $this->assertDatabaseHas('users', ['email' => 'panne@example.com', 'role' => 'producer']);
    }

    public function test_renvoi_regenere_le_mot_de_passe_et_reenvoie_email(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $producer = User::factory()->create([
            'role'     => 'producer',
            'email'    => 'renvoi@example.com',
            'password' => Hash::make('ancien-mot-de-passe'),
        ]);
        $oldHash = $producer->password;

        $res = $this->actingAs($admin)->post(route('producers.resend', $producer));

        $res->assertRedirect(route('producers.index'));
        $res->assertSessionHas('success');

        // Le mot de passe a bien changé (ancien invalidé).
        $this->assertNotSame($oldHash, $producer->fresh()->password);
        $this->assertFalse(Hash::check('ancien-mot-de-passe', $producer->fresh()->password));

        Mail::assertSent(ProducerCredentialsMail::class, fn ($mail) => $mail->hasTo('renvoi@example.com'));
    }

    public function test_renvoi_refuse_un_non_producteur(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $autre = User::factory()->create(['role' => 'user']);

        $res = $this->actingAs($admin)->post(route('producers.resend', $autre));

        $res->assertSessionHas('error');
        Mail::assertNothingSent();
    }

    public function test_fiche_producteur_liste_ses_contenus(): void
    {
        $admin = $this->admin();
        $producer = User::factory()->create(['role' => 'producer', 'name' => 'Studio X']);
        $category = Category::firstOrCreate(['slug' => 'action'], ['name' => 'Action']);

        Media::create([
            'category_id'    => $category->id,
            'user_id'        => $producer->id,
            'title'          => 'Mon Super Film',
            'slug'           => 'mon-super-film',
            'type'           => 'movie',
            'video_provider' => 'local',
        ]);

        $res = $this->actingAs($admin)->get(route('producers.show', $producer));

        $res->assertOk();
        $res->assertSee('Studio X');
        $res->assertSee('Mon Super Film');
    }

    public function test_fiche_producteur_refuse_un_non_producteur(): void
    {
        $admin = $this->admin();
        $membre = User::factory()->create(['role' => 'user']);

        $res = $this->actingAs($admin)->get(route('producers.show', $membre));

        $res->assertRedirect(route('producers.index'));
        $res->assertSessionHas('error');
    }
}
