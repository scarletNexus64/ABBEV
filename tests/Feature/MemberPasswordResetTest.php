<?php

namespace Tests\Feature;

use App\Models\EmailVerification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Flux « mot de passe oublié » membre (#6) : code par email → vérification →
 * nouveau mot de passe. Réutilise le mécanisme EmailVerification de l'OTP.
 */
class MemberPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $email = 'membre@abbev.tv'): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => Hash::make('ancien-mot-de-passe'),
            'role' => 'user',
            'is_active' => true,
        ]);
    }

    public function test_forgot_password_cree_un_code_pour_un_compte_existant(): void
    {
        Mail::fake();
        $this->member();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'membre@abbev.tv'])
            ->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseHas('email_verifications', ['email' => 'membre@abbev.tv']);
    }

    public function test_forgot_password_anti_enumeration_sur_email_inconnu(): void
    {
        Mail::fake();

        // Même réponse générique, mais AUCUN code n'est créé.
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'inconnu@abbev.tv'])
            ->assertOk();

        $this->assertDatabaseMissing('email_verifications', ['email' => 'inconnu@abbev.tv']);
    }

    public function test_verify_reset_code_valide_sans_consommer(): void
    {
        $this->member();
        EmailVerification::create([
            'email' => 'membre@abbev.tv',
            'code' => '654321',
            'expires_at' => now()->addMinutes(15),
            'verified' => false,
        ]);

        $this->postJson('/api/v1/auth/verify-reset-code', [
            'email' => 'membre@abbev.tv',
            'code' => '654321',
        ])->assertOk()->assertJson(['valid' => true]);

        // Le code n'est PAS consommé (toujours utilisable pour reset).
        $this->assertDatabaseHas('email_verifications', [
            'email' => 'membre@abbev.tv',
            'verified' => false,
        ]);
    }

    public function test_reset_password_change_le_mot_de_passe_et_retourne_un_token(): void
    {
        $user = $this->member();
        EmailVerification::create([
            'email' => 'membre@abbev.tv',
            'code' => '654321',
            'expires_at' => now()->addMinutes(15),
            'verified' => false,
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'membre@abbev.tv',
            'code' => '654321',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertOk()->assertJsonStructure(['user', 'token']);

        $user->refresh();
        $this->assertTrue(Hash::check('nouveau-mot-de-passe', $user->password));
        // Le code est consommé.
        $this->assertDatabaseHas('email_verifications', [
            'email' => 'membre@abbev.tv',
            'verified' => true,
        ]);
    }

    public function test_reset_password_refuse_un_code_invalide(): void
    {
        $this->member();

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'membre@abbev.tv',
            'code' => '000000',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertStatus(422);
    }

    public function test_reset_password_refuse_un_code_expire(): void
    {
        $this->member();
        EmailVerification::create([
            'email' => 'membre@abbev.tv',
            'code' => '654321',
            'expires_at' => now()->subMinute(),
            'verified' => false,
        ]);

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'membre@abbev.tv',
            'code' => '654321',
            'password' => 'nouveau-mot-de-passe',
            'password_confirmation' => 'nouveau-mot-de-passe',
        ])->assertStatus(422);
    }
}
