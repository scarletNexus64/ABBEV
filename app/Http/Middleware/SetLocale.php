<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applique la langue de l'appelant à toute la requête.
 *
 * L'app mobile envoie `Accept-Language: fr,en;q=0.8` (ou l'inverse) sur chaque
 * appel : sans ce middleware, l'en-tête est ignoré et TOUS les messages
 * repartent en français, y compris vers un utilisateur anglophone.
 *
 * Ordre de résolution, du plus explicite au plus permissif :
 *   1. `?lang=en` — surcharge ponctuelle, pratique pour tester en curl ;
 *   2. `Accept-Language` — le cas normal, envoyé par l'app ;
 *   3. `config('app.locale')` — repli.
 *
 * Toute langue hors [supportedLocales] est ignorée : on ne veut pas que
 * `Accept-Language: de` fasse basculer Laravel sur un dossier `lang/de`
 * inexistant et renvoie les clés brutes (« messages.payment.failed ») à
 * l'utilisateur.
 */
class SetLocale
{
    /** Langues réellement traduites (un fichier `lang/{code}.json` existe). */
    public const SUPPORTED = ['fr', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        App::setLocale($locale);

        $response = $next($request);

        // Indique aux caches/proxies que la réponse dépend de la langue
        // demandée : sans ce Vary, une réponse FR mise en cache pourrait être
        // resservie à un client qui a demandé EN.
        $response->headers->set('Content-Language', $locale);
        $existingVary = $response->headers->get('Vary');
        if (! $existingVary || ! str_contains(strtolower($existingVary), 'accept-language')) {
            $response->headers->set(
                'Vary',
                $existingVary ? $existingVary.', Accept-Language' : 'Accept-Language'
            );
        }

        return $response;
    }

    /** Détermine la langue à appliquer pour cette requête. */
    private function resolve(Request $request): string
    {
        // 1. Surcharge explicite par query string.
        $explicit = $this->normalize((string) $request->query('lang', ''));
        if ($explicit !== null) {
            return $explicit;
        }

        // 2. Négociation standard. `getPreferredLanguage` respecte les
        //    facteurs de qualité (q=) et retombe sur le 1er argument si aucune
        //    des langues proposées ne correspond.
        $header = $request->header('Accept-Language');
        if ($header) {
            $preferred = $request->getPreferredLanguage(self::SUPPORTED);
            $normalized = $this->normalize((string) $preferred);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        // 3. Repli sur la config.
        return $this->normalize((string) config('app.locale')) ?? 'fr';
    }

    /**
     * Ramène « fr_FR », « en-US », « EN » … à « fr » / « en ».
     * Renvoie null si la langue n'est pas supportée.
     */
    private function normalize(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $code = strtolower(substr(str_replace('_', '-', $value), 0, 2));

        return in_array($code, self::SUPPORTED, true) ? $code : null;
    }
}
