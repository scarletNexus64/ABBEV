<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MovieResource;
use App\Http\Resources\SerieResource;
use App\Models\Media;
use App\Models\UserListItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * "Ma liste" : liste unique (films + séries) propre à l'utilisateur connecté.
 * Toutes les routes sont protégées par `auth:sanctum`.
 */
class MyListApiController extends Controller
{
    /**
     * GET /auth/my-list
     * Renvoie le contenu de la liste, dans le même format que le reste de
     * l'API (MovieResource / SerieResource) pour réutiliser les modèles front.
     */
    public function index(Request $request): JsonResponse
    {
        $media = Media::query()
            ->whereIn('id', $this->mediaIds($request))
            ->with('category')
            ->latest('id')
            ->get();

        $data = $media->map(fn (Media $m) => $m->type === 'series'
            ? (new SerieResource($m))->toArray($request)
            : (new MovieResource($m))->toArray($request));

        return response()->json(['data' => $data->values()]);
    }

    /**
     * POST /auth/my-list  { media_id }
     * Ajoute un média à la liste (idempotent : pas de doublon).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'media_id' => 'required|integer|exists:media,id',
        ]);

        UserListItem::firstOrCreate([
            'user_id' => $request->user()->id,
            'media_id' => $data['media_id'],
        ]);

        return response()->json([
            'message' => 'Ajouté à votre liste.',
            'in_list' => true,
        ], 201);
    }

    /**
     * DELETE /auth/my-list/{media}
     * Retire un média de la liste.
     */
    public function destroy(Request $request, Media $media): JsonResponse
    {
        UserListItem::where('user_id', $request->user()->id)
            ->where('media_id', $media->id)
            ->delete();

        return response()->json([
            'message' => 'Retiré de votre liste.',
            'in_list' => false,
        ]);
    }

    /**
     * GET /auth/my-list/{media}/status
     * Indique si un média précis est déjà dans la liste (pour l'état du
     * bouton "Ma liste" sur l'écran détails).
     */
    public function status(Request $request, Media $media): JsonResponse
    {
        $inList = UserListItem::where('user_id', $request->user()->id)
            ->where('media_id', $media->id)
            ->exists();

        return response()->json(['in_list' => $inList]);
    }

    /**
     * @return array<int,int> Les media_id présents dans la liste de l'user.
     */
    private function mediaIds(Request $request): array
    {
        return UserListItem::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->pluck('media_id')
            ->all();
    }
}
