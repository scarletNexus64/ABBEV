<?php

namespace Tests\Feature;

use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

/**
 * Localisation de l'API (#i18n).
 *
 * Deux risques couverts :
 *   1. le middleware ignore `Accept-Language` → un utilisateur anglophone
 *      reçoit des messages en français ;
 *   2. une clé existe en FR mais pas en EN → Laravel renvoie la clé brute
 *      (« messages.payment.failed ») directement à l'écran.
 */
class LocalizationTest extends TestCase
{
    // ---- Middleware -----------------------------------------------------

    public function test_accept_language_anglais_bascule_la_locale(): void
    {
        $this->withHeader('Accept-Language', 'en')->getJson('/api/v1/countries');

        $this->assertSame('en', App::getLocale());
    }

    public function test_accept_language_francais_bascule_la_locale(): void
    {
        $this->withHeader('Accept-Language', 'fr')->getJson('/api/v1/countries');

        $this->assertSame('fr', App::getLocale());
    }

    public function test_entete_complet_de_lapp_mobile_est_respecte(): void
    {
        // Exactement ce qu'envoie ApiClient côté Flutter.
        $this->withHeader('Accept-Language', 'en,fr;q=0.8')->getJson('/api/v1/countries');
        $this->assertSame('en', App::getLocale());

        $this->withHeader('Accept-Language', 'fr,en;q=0.8')->getJson('/api/v1/countries');
        $this->assertSame('fr', App::getLocale());
    }

    public function test_variantes_regionales_sont_normalisees(): void
    {
        $this->withHeader('Accept-Language', 'en-US')->getJson('/api/v1/countries');
        $this->assertSame('en', App::getLocale());

        $this->withHeader('Accept-Language', 'fr-CA')->getJson('/api/v1/countries');
        $this->assertSame('fr', App::getLocale());
    }

    public function test_langue_non_supportee_retombe_sur_le_defaut(): void
    {
        // « de » n'a pas de fichier de langue : on ne doit PAS basculer dessus,
        // sinon les traductions manquantes sortiraient en clés brutes.
        $this->withHeader('Accept-Language', 'de')->getJson('/api/v1/countries');

        $this->assertContains(App::getLocale(), SetLocale::SUPPORTED);
    }

    public function test_parametre_lang_surcharge_lentete(): void
    {
        $this->withHeader('Accept-Language', 'fr')
            ->getJson('/api/v1/countries?lang=en');

        $this->assertSame('en', App::getLocale());
    }

    public function test_reponse_annonce_la_langue_et_varie_dessus(): void
    {
        $response = $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/v1/countries');

        $response->assertHeader('Content-Language', 'en');
        $this->assertStringContainsStringIgnoringCase(
            'Accept-Language',
            (string) $response->headers->get('Vary')
        );
    }

    // ---- Fichiers de langue ---------------------------------------------

    public function test_les_deux_langues_exposent_les_memes_cles(): void
    {
        $fr = $this->flatten(require lang_path('fr/messages.php'));
        $en = $this->flatten(require lang_path('en/messages.php'));

        $this->assertSame([], array_values(array_diff($fr, $en)),
            'clés présentes en FR mais absentes en EN');
        $this->assertSame([], array_values(array_diff($en, $fr)),
            'clés présentes en EN mais absentes en FR');
    }

    public function test_aucune_traduction_vide(): void
    {
        foreach (['fr', 'en'] as $locale) {
            $this->assertNoEmptyValue(require lang_path("{$locale}/messages.php"), $locale);
        }
    }

    public function test_les_placeholders_correspondent_entre_langues(): void
    {
        $fr = require lang_path('fr/messages.php');
        $en = require lang_path('en/messages.php');

        foreach ($this->flatten($fr) as $key) {
            $frValue = data_get($fr, $key);
            $enValue = data_get($en, $key);

            preg_match_all('/:(\w+)/', $frValue, $frMatches);
            preg_match_all('/:(\w+)/', $enValue, $enMatches);

            sort($frMatches[1]);
            sort($enMatches[1]);

            $this->assertSame($frMatches[1], $enMatches[1],
                "placeholders différents pour la clé « {$key} »");
        }
    }

    public function test_une_cle_traduite_rend_bien_les_deux_langues(): void
    {
        App::setLocale('fr');
        $this->assertSame('Le paiement a échoué.', __('messages.payment.failed'));

        App::setLocale('en');
        $this->assertSame('The payment failed.', __('messages.payment.failed'));
    }

    public function test_les_parametres_sont_substitues(): void
    {
        App::setLocale('en');

        $this->assertStringContainsString(
            'Cameroun',
            __('messages.payment.operator_unavailable', ['country' => 'Cameroun'])
        );
    }

    // ---- Helpers ---------------------------------------------------------

    /** Aplatit un tableau imbriqué en clés « groupe.cle ». */
    private function flatten(array $items, string $prefix = ''): array
    {
        $keys = [];
        foreach ($items as $key => $value) {
            $full = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $keys = array_merge($keys, $this->flatten($value, $full));
            } else {
                $keys[] = $full;
            }
        }
        sort($keys);

        return $keys;
    }

    private function assertNoEmptyValue(array $items, string $locale, string $prefix = ''): void
    {
        foreach ($items as $key => $value) {
            $full = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $this->assertNoEmptyValue($value, $locale, $full);
            } else {
                $this->assertNotSame('', trim((string) $value),
                    "traduction vide : {$locale} / {$full}");
            }
        }
    }
}
