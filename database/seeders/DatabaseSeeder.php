<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            CountrySeeder::class,
            AdminUserSeeder::class,
            CategorySeeder::class,
            MediaSeeder::class,
            AppleInAppPurchasePlanSeeder::class,
            ConfigurationSeeder::class,
            ScreeningSeeder::class,
            RubriqueSeeder::class,

            // EN DERNIER : traduit le contenu créé par les seeders ci-dessus.
            // Placé ailleurs, il ne trouverait rien à traduire.
            TranslationSeeder::class,
        ]);
    }
}
