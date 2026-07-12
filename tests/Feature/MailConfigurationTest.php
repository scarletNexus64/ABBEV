<?php

namespace Tests\Feature;

use App\Models\Configuration;
use App\Models\User;
use App\Support\RuntimeMailConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Configuration email pilotée depuis le dashboard (groupe « email ») :
 * le pont RuntimeMailConfig doit appliquer les valeurs DB par-dessus le .env,
 * et l'action de test doit refuser le mailer « log » et livrer sinon.
 */
class MailConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function setEmailConfig(array $values): void
    {
        foreach ($values as $key => $value) {
            Configuration::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => 'email', 'is_secret' => false],
            );
        }
    }

    public function test_applique_la_config_smtp_de_la_base(): void
    {
        $this->setEmailConfig([
            'mail_mailer'       => 'smtp',
            'mail_host'         => 'smtp.test.com',
            'mail_port'         => '587',
            'mail_username'     => 'hello@test.com',
            'mail_password'     => 'secret',
            'mail_encryption'   => 'tls',
            'mail_from_address' => 'no-reply@abbev.tv',
            'mail_from_name'    => 'ABBEV',
        ]);

        RuntimeMailConfig::apply();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.test.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('hello@test.com', config('mail.mailers.smtp.username'));
        $this->assertSame('tls', config('mail.mailers.smtp.encryption'));
        $this->assertSame('no-reply@abbev.tv', config('mail.from.address'));
        $this->assertSame('ABBEV', config('mail.from.name'));
    }

    public function test_chiffrement_aucun_donne_null(): void
    {
        $this->setEmailConfig([
            'mail_mailer'     => 'smtp',
            'mail_encryption' => 'none',
        ]);

        RuntimeMailConfig::apply();

        $this->assertNull(config('mail.mailers.smtp.encryption'));
    }

    public function test_champs_vides_n_ecrasent_pas_le_env(): void
    {
        config(['mail.mailers.smtp.host' => 'env-host.com']);

        $this->setEmailConfig([
            'mail_mailer' => 'smtp',
            'mail_host'   => '', // vide → ne doit pas écraser
        ]);

        RuntimeMailConfig::apply();

        $this->assertSame('env-host.com', config('mail.mailers.smtp.host'));
    }

    public function test_test_mail_refuse_le_mailer_log(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->setEmailConfig(['mail_mailer' => 'log']);

        $res = $this->actingAs($admin)->post(route('configuration.testMail'));

        $res->assertSessionHas('error');
        $res->assertSessionHas('active_tab', 'email');
        Mail::assertNothingOutgoing();
    }

    public function test_test_mail_envoie_avec_smtp(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['role' => 'admin', 'email' => 'admin@abbev.tv']);
        $this->setEmailConfig([
            'mail_mailer' => 'smtp',
            'mail_host'   => 'smtp.test.com',
        ]);

        $res = $this->actingAs($admin)->post(route('configuration.testMail'));

        $res->assertSessionHas('success');
        $res->assertSessionHas('active_tab', 'email');
    }
}
