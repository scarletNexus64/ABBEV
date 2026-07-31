<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MovieResource;
use App\Http\Resources\OeuvreResource;
use App\Http\Resources\RubriqueResource;
use App\Http\Resources\SerieResource;
use App\Models\Rubrique;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rubriques thématiques affichées en chips au-dessus du catalogue mobile
 * (« Avant Première », « Œuvre adaptable »…).
 *
 * Le contrat correspond à ce que consomme déjà l'app Flutter :
 *  - `GET /rubriques`                → { data: [RubriqueResource] }
 *  - `GET /rubriques/{id}/contents`  → { type: 'oeuvre', data: [OeuvreResource] }
 *                                      ou { type: 'media', data: { movies, series } }
 *
 * Les routes sont publiques mais reconnaissent l'utilisateur s'il présente un
 * token (middleware `auth:sanctum` optionnel) : les rubriques verrouillées
 * par `required_tier` sont alors filtrées selon son abonnement.
 */
class RubriqueApiController extends Controller
{
    /**
     * Utilisateur courant, s'il en présente un.
     *
     * Ces routes sont volontairement PUBLIQUES (un visiteur non connecté doit
     * voir les rubriques ouvertes), donc aucun middleware `auth:sanctum` ne
     * les traverse et `$request->user()` resterait `null` même avec un token
     * valide. On interroge donc le guard explicitement : porteur d'un token →
     * utilisateur résolu, sinon `null`.
     */
    private function resolveUser(Request $request): ?\App\Models\User
    {
        return $request->user('sanctum');
    }

    /**
     * Rubriques ACCESSIBLES à l'appelant. Celles dont le tier dépasse son
     * abonnement sont masquées — le mobile n'affiche donc jamais une chip
     * qu'il ne pourrait pas ouvrir.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);

        $rubriques = Rubrique::active()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (Rubrique $r) => $r->isAccessibleBy($user))
            ->values();

        return response()->json([
            'data' => RubriqueResource::collection($rubriques),
        ]);
    }

    /**
     * Contenus d'une rubrique. La forme de `data` dépend de `content_type`,
     * d'où le champ `type` en tête de réponse qui indique au client comment
     * la désérialiser.
     */
    public function contents(Request $request, Rubrique $rubrique): JsonResponse
    {
        if (! $rubrique->is_active) {
            return response()->json(['message' => 'Rubrique introuvable.'], 404);
        }

        // Re-vérifié ici et pas seulement dans index() : un ID de rubrique
        // verrouillée pourrait être appelé directement.
        if (! $rubrique->isAccessibleBy($this->resolveUser($request))) {
            return response()->json([
                'message' => 'Cette rubrique nécessite un abonnement supérieur.',
                'required_tier' => $rubrique->required_tier,
            ], 403);
        }

        if ($rubrique->isOeuvre()) {
            return response()->json([
                'type' => 'oeuvre',
                'data' => OeuvreResource::collection(
                    $rubrique->oeuvres()->active()->get()
                ),
            ]);
        }

        // content_type = 'media' : on ne renvoie que les contenus réellement
        // visibles (publiés + modération approuvée), séparés par type pour
        // coller aux deux listes distinctes de l'écran mobile.
        $media = $rubrique->media()->published()->get();

        return response()->json([
            'type' => 'media',
            'data' => [
                'movies' => MovieResource::collection(
                    $media->where('type', 'movie')->values()
                ),
                'series' => SerieResource::collection(
                    $media->where('type', 'series')->values()
                ),
            ],
        ]);
    }
}
