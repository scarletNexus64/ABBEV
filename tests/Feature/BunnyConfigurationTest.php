<?php

namespace Tests\Feature;

use App\Models\Configuration;
use App\Models\User;
use App\Support\RuntimeBunnyConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Configuration Bunny Stream pilotée depuis le dashboard (groupe « bunny ») :
 * le pont RuntimeBunnyConfig applique les valeurs DB par-dessus le .env.
 */
class BunnyConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private function setBunnyConfig(array $values): void
    {
        foreach ($values as $key => $value) {
            Configuration::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => 'bunny', 'is_secret' => false],
            );
        }
    }

    public function test_applique_la_config_bunny_de_la_base(): void
    {
        $this->setBunnyConfig([
            'bunny_library_id'   => '664138',
            'bunny_api_key'      => 'la-vraie-cle',
            'bunny_cdn_hostname' => 'vz-abcd.b-cdn.net',
            'bunny_token_key'    => 'token-secret',
            'bunny_token_ttl'    => '7200',
        ]);

        RuntimeBunnyConfig::apply();

        $this->assertSame('664138', config('services.bunny.library_id'));
        $this->assertSame('la-vraie-cle', config('services.bunny.api_key'));
        $this->assertSame('vz-abcd.b-cdn.net', config('services.bunny.cdn_hostname'));
        $this->assertSame('token-secret', config('services.bunny.token_key'));
        $this->assertSame(7200, config('services.bunny.token_ttl'));
    }

    public function test_champs_vides_n_ecrasent_pas_le_env(): void
    {
        config(['services.bunny.api_key' => 'cle-env']);
        $this->setBunnyConfig(['bunny_api_key' => '']); // vide → ne doit pas écraser

        RuntimeBunnyConfig::apply();

        $this->assertSame('cle-env', config('services.bunny.api_key'));
    }

    public function test_test_bunny_refuse_si_non_configure(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        // Aucune valeur bunny → non configuré.
        config(['services.bunny.library_id' => '', 'services.bunny.api_key' => '', 'services.bunny.cdn_hostname' => '']);

        $res = $this->actingAs($admin)->post(route('configuration.testBunny'));

        $res->assertSessionHas('error');
        $res->assertSessionHas('active_tab', 'bunny');
    }
}
