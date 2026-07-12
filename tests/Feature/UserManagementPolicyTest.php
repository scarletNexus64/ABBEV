<?php

namespace Tests\Feature;

use App\Mail\AdminPasswordResetMail;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Modification des comptes utilisateur depuis le dashboard, encadrée par la
 * UserPolicy : seuls les admins agissent, avec garde-fous anti auto-verrouillage.
 */
class UserManagementPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function member(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => 'user'], $attrs));
    }

    // ---------- Policy ----------

    public function test_admin_a_tous_les_droits_sur_un_autre_compte(): void
    {
        $admin = $this->admin();
        $target = $this->member();

        $this->assertTrue($admin->can('update', $target));
        $this->assertTrue($admin->can('updateStatus', $target));
        $this->assertTrue($admin->can('resetPassword', $target));
        $this->assertTrue($admin->can('manageSubscription', $target));
        $this->assertTrue($admin->can('delete', $target));
    }

    public function test_admin_ne_peut_pas_se_suspendre_ni_se_supprimer(): void
    {
        $admin = $this->admin();

        $this->assertFalse($admin->can('updateStatus', $admin));
        $this->assertFalse($admin->can('delete', $admin));
        // Mais il peut éditer ses propres infos.
        $this->assertTrue($admin->can('update', $admin));
    }

    public function test_un_non_admin_ne_peut_rien_modifier(): void
    {
        $producer = User::factory()->create(['role' => 'producer']);
        $target = $this->member();

        $this->assertFalse($producer->can('update', $target));
        $this->assertFalse($producer->can('updateStatus', $target));
        $this->assertFalse($producer->can('resetPassword', $target));
        $this->assertFalse($producer->can('delete', $target));
    }

    public function test_seul_un_admin_peut_creer_un_utilisateur(): void
    {
        $this->assertTrue($this->admin()->can('create', User::class));
        $this->assertFalse(User::factory()->create(['role' => 'producer'])->can('create', User::class));
        $this->assertFalse($this->member()->can('create', User::class));
    }

    // ---------- Actions HTTP ----------

    public function test_admin_cree_un_membre_standard(): void
    {
        $admin = $this->admin();

        $res = $this->actingAs($admin)->post(route('users.store'), [
            'name'  => 'Jean Membre',
            'email' => 'jean@abbev.tv',
        ]);

        $this->assertDatabaseHas('users', [
            'email'     => 'jean@abbev.tv',
            'role'      => 'user',
            'is_active' => true,
        ]);
        $res->assertRedirect(route('users.show', User::where('email', 'jean@abbev.tv')->first()));
    }

    public function test_admin_modifie_nom_et_email(): void
    {
        $admin = $this->admin();
        $member = $this->member(['name' => 'Ancien', 'email' => 'ancien@abbev.tv']);

        $res = $this->actingAs($admin)->put(route('users.update', $member), [
            'name'  => 'Nouveau Nom',
            'email' => 'nouveau@abbev.tv',
        ]);

        $res->assertRedirect(route('users.show', $member));
        $this->assertDatabaseHas('users', [
            'id'    => $member->id,
            'name'  => 'Nouveau Nom',
            'email' => 'nouveau@abbev.tv',
        ]);
    }

    public function test_admin_suspend_puis_reactive_un_compte(): void
    {
        $admin = $this->admin();
        $member = $this->member(['is_active' => true]);

        $this->actingAs($admin)->patch(route('users.status', $member), ['is_active' => 0]);
        $this->assertFalse($member->fresh()->is_active);

        $this->actingAs($admin)->patch(route('users.status', $member), ['is_active' => 1]);
        $this->assertTrue($member->fresh()->is_active);
    }

    public function test_reset_password_envoie_un_email_et_change_le_hash(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $member = $this->member(['password' => Hash::make('ancien')]);
        $oldHash = $member->password;

        $res = $this->actingAs($admin)->post(route('users.resetPassword', $member));

        $res->assertSessionHas('success');
        $this->assertNotSame($oldHash, $member->fresh()->password);
        Mail::assertSent(AdminPasswordResetMail::class, fn ($m) => $m->hasTo($member->email));
    }

    public function test_prolongation_abonnement(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $sub = $this->activeSubscription($member, now()->addDays(10));

        $this->actingAs($admin)->post(route('users.subscription.extend', $member), ['days' => 30]);

        $this->assertEquals(40, (int) now()->startOfDay()->diffInDays($sub->fresh()->expires_at->startOfDay()));
    }

    public function test_annulation_abonnement(): void
    {
        $admin = $this->admin();
        $member = $this->member();
        $sub = $this->activeSubscription($member, now()->addDays(10));

        $this->actingAs($admin)->delete(route('users.subscription.cancel', [$member, $sub]));

        $this->assertSame('cancelled', $sub->fresh()->status);
    }

    // ---------- Blocage login ----------

    public function test_un_staff_suspendu_ne_peut_pas_se_connecter_au_dashboard(): void
    {
        $admin = User::factory()->create([
            'role'      => 'admin',
            'email'     => 'sus@abbev.tv',
            'password'  => Hash::make('secret123'),
            'is_active' => false,
        ]);

        $res = $this->post(route('admin.login.submit'), [
            'email'    => 'sus@abbev.tv',
            'password' => 'secret123',
        ]);

        $res->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    private function activeSubscription(User $user, $expiresAt): UserSubscription
    {
        $plan = SubscriptionPlan::create([
            'name'          => 'Mensuel',
            'price'         => 1000,
            'duration_days' => 30,
        ]);

        return UserSubscription::create([
            'user_id'              => $user->id,
            'subscription_plan_id' => $plan->id,
            'starts_at'            => now(),
            'expires_at'           => $expiresAt,
            'status'               => 'active',
        ]);
    }
}
