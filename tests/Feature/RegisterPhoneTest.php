<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Currency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'inscription capture le numéro de téléphone (avec indicatif) pour la future
 * vérification par OTP (activable côté admin).
 */
class RegisterPhoneTest extends TestCase
{
    use RefreshDatabase;

    private function seedLocale(): void
    {
        Currency::create([
            'code' => 'XAF', 'name' => 'Franc CFA', 'symbol' => 'FCFA',
            'rate_from_xof' => 1.0, 'decimals' => 0, 'is_active' => true,
        ]);
        Country::create([
            'code' => 'CM', 'name' => 'Cameroun', 'flag_emoji' => '🇨🇲',
            'phone_code' => '+237', 'currency_code' => 'XAF', 'is_active' => true,
        ]);
    }

    public function test_inscription_enregistre_le_telephone(): void
    {
        $this->seedLocale();

        $res = $this->postJson('/api/v1/auth/register', [
            'name' => 'Stael',
            'email' => 'stael@abbev.tv',
            'password' => 'motdepasse8',
            'password_confirmation' => 'motdepasse8',
            'phone' => '+237690000001',
            'country_code' => 'CM',
        ]);

        $res->assertCreated()->assertJsonStructure(['user', 'token']);
        $this->assertDatabaseHas('users', [
            'email' => 'stael@abbev.tv',
            'phone' => '+237690000001',
        ]);
    }

    public function test_telephone_optionnel(): void
    {
        $this->seedLocale();

        $this->postJson('/api/v1/auth/register', [
            'name' => 'Sans Tel',
            'email' => 'sanstel@abbev.tv',
            'password' => 'motdepasse8',
            'password_confirmation' => 'motdepasse8',
            'country_code' => 'CM',
        ])->assertCreated();

        $this->assertNull(User::where('email', 'sanstel@abbev.tv')->first()->phone);
    }
}
