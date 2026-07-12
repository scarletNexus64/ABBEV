<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Hashid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Masquage des ids : encodage réversible + résolution de route qui accepte
 * l'id encodé (web) ET l'id brut (API/mobile), sans ambiguïté.
 */
class HashidRouteKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_encode_decode_roundtrip(): void
    {
        foreach ([1, 5, 42, 100, 999, 123456, 2147483647] as $id) {
            $hash = Hashid::encode($id);
            $this->assertSame('k', $hash[0], "l'id encodé doit être préfixé");
            $this->assertNotSame((string) $id, $hash, "l'id encodé ne doit pas être l'id brut");
            $this->assertSame($id, Hashid::decode($hash));
        }
    }

    public function test_les_ids_sont_non_sequentiels(): void
    {
        // Deux ids consécutifs ne doivent pas donner des codes consécutifs.
        $this->assertNotSame(
            substr(Hashid::encode(1000), 0, -1),
            substr(Hashid::encode(1001), 0, -1),
        );
        $this->assertNotSame(Hashid::encode(1), Hashid::encode(2));
    }

    public function test_decode_rejette_id_brut_et_garbage(): void
    {
        $this->assertNull(Hashid::decode('5'), 'un id brut décimal n\'est pas un hash');
        $this->assertNull(Hashid::decode('123'));
        $this->assertNull(Hashid::decode('nimportequoi'));
        $this->assertNull(Hashid::decode(''));
    }

    public function test_url_web_utilise_id_encode(): void
    {
        $user = User::factory()->create(['role' => 'user']);

        $url = route('users.show', $user);

        $this->assertStringContainsString('/'.Hashid::encode($user->id), $url);
        $this->assertStringNotContainsString('/users/'.$user->id, $url);
    }

    public function test_resolution_accepte_encode_et_brut(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'user']);

        // Web : id encodé.
        $this->actingAs($admin)
            ->get(route('users.show', $target))
            ->assertOk();

        // API/mobile : id brut → toujours résolu (repli).
        $this->actingAs($admin)
            ->get('/admin/users/'.$target->id)
            ->assertOk();
    }
}
