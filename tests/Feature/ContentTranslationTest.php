<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\SubscriptionPlan;
use Database\Seeders\TranslationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Traduction du CONTENU (données admin/API), par opposition aux messages
 * d'API couverts par [LocalizationTest].
 *
 * Le comportement critique est le **repli** : un contenu non traduit doit
 * continuer à s'afficher dans sa langue d'origine. Un champ vide à l'écran
 * serait pire que du français chez un anglophone.
 */
class ContentTranslationTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_modele_renvoie_la_traduction_dans_la_langue_courante(): void
    {
        $category = Category::create(['name' => 'Comédie', 'slug' => 'comedie']);
        $category->setTranslation('name', 'en', 'Comedy');

        App::setLocale('fr');
        $this->assertSame('Comédie', $category->t('name'));

        App::setLocale('en');
        $this->assertSame('Comedy', $category->fresh()->t('name'));
    }

    public function test_sans_traduction_on_retombe_sur_la_valeur_dorigine(): void
    {
        $category = Category::create(['name' => 'Nanar', 'slug' => 'nanar']);

        App::setLocale('en');

        // Jamais de chaîne vide : l'app afficherait un libellé blanc.
        $this->assertSame('Nanar', $category->t('name'));
    }

    public function test_une_traduction_vide_ne_masque_pas_loriginal(): void
    {
        $category = Category::create(['name' => 'Drame', 'slug' => 'drame']);
        $category->setTranslation('name', 'en', '');

        App::setLocale('en');
        $this->assertSame('Drame', $category->fresh()->t('name'));
    }

    public function test_un_champ_non_declare_traduisible_est_ignore(): void
    {
        $category = Category::create(['name' => 'Action', 'slug' => 'action']);
        $category->setTranslation('slug', 'en', 'hacked');

        App::setLocale('en');

        // `slug` n'est pas dans $translatable : la valeur d'origine prime.
        $this->assertSame('action', $category->fresh()->t('slug'));
    }

    public function test_les_champs_json_sont_traduits_ligne_a_ligne(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Premium', 'price' => 5000, 'duration_days' => 30,
            'features' => ['Sans publicité', 'Qualité HD'],
        ]);
        $plan->setTranslation('features', 'en', json_encode(['Ad-free', 'HD quality']));

        App::setLocale('en');
        $this->assertSame(['Ad-free', 'HD quality'], $plan->fresh()->tArray('features'));

        App::setLocale('fr');
        $this->assertSame(['Sans publicité', 'Qualité HD'], $plan->fresh()->tArray('features'));
    }

    public function test_setTranslation_est_idempotent(): void
    {
        $category = Category::create(['name' => 'Horreur', 'slug' => 'horreur']);

        $category->setTranslation('name', 'en', 'Horror');
        $category->setTranslation('name', 'en', 'Horror');
        $category->setTranslation('name', 'en', 'Scary');

        $this->assertSame(1, $category->translations()->where('field', 'name')->count());
        App::setLocale('en');
        $this->assertSame('Scary', $category->fresh()->t('name'));
    }

    public function test_le_seeder_traduit_le_contenu_et_reste_idempotent(): void
    {
        Category::create(['name' => 'Comédie', 'slug' => 'comedie']);
        Category::create(['name' => 'Science-Fiction', 'slug' => 'sf']);

        $this->seed(TranslationSeeder::class);
        $countAfterFirstRun = DB::table('translations')->count();

        $this->seed(TranslationSeeder::class);

        $this->assertSame($countAfterFirstRun, DB::table('translations')->count(),
            'le seeder relancé a créé des doublons');

        App::setLocale('en');
        $this->assertSame('Comedy', Category::where('slug', 'comedie')->first()->t('name'));
        $this->assertSame('Science Fiction', Category::where('slug', 'sf')->first()->t('name'));
    }

    public function test_lapi_renvoie_les_categories_traduites(): void
    {
        Category::create(['name' => 'Comédie', 'slug' => 'comedie']);
        $this->seed(TranslationSeeder::class);

        $en = $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->json('data');

        $fr = $this->withHeader('Accept-Language', 'fr')
            ->getJson('/api/v1/categories')
            ->assertOk()
            ->json('data');

        $this->assertContains('Comedy', array_column($en, 'name'));
        $this->assertContains('Comédie', array_column($fr, 'name'));
    }

    public function test_pas_de_requete_n_plus_1_sur_une_liste_traduite(): void
    {
        foreach (['Comédie', 'Drame', 'Horreur', 'Action', 'Romance'] as $i => $name) {
            Category::create(['name' => $name, 'slug' => 'c'.$i]);
        }
        $this->seed(TranslationSeeder::class);

        App::setLocale('en');
        DB::enableQueryLog();
        Category::all()->each(fn (Category $c) => $c->t('name'));
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        // 1 requête catégories + 1 requête traductions (eager loading).
        // Sans le scope global, on aurait 1 + 5.
        $this->assertLessThanOrEqual(2, $queries,
            "N+1 détecté : {$queries} requêtes pour 5 catégories");
    }
}
