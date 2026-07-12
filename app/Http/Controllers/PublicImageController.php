<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sert une image PUBLIQUE (poster/cover/banner/thumbnail uploadée localement)
 * AVEC des en-têtes CORS.
 *
 * Pourquoi ne pas servir directement via /storage/... ? Sur Flutter Web
 * (moteur CanvasKit), les images cross-origin sont dessinées sur un canvas et
 * deviennent « tainted » si la réponse ne porte pas d'en-tête
 * `Access-Control-Allow-Origin` → elles ne s'affichent pas. Les fichiers
 * statiques du symlink public/storage n'ont pas ces en-têtes. Cette route,
 * elle, passe par Laravel et les ajoute. Sur mobile (Android/iOS) l'URL
 * fonctionne aussi (le CORS y est simplement ignoré).
 */
class PublicImageController extends Controller
{
    public function __invoke(Request $request, string $path): BinaryFileResponse
    {
        // Anti-traversal : on refuse tout chemin qui tente de sortir du disque.
        abort_if(str_contains($path, '..'), 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($path), 404);

        return response()->file($disk->path($path), [
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
