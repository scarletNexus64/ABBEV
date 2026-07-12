<?php

namespace App\Http\Resources\Concerns;

/**
 * Construit une URL absolue exploitable par l'app mobile à partir d'un
 * chemin de stockage.
 *
 * Le disque "public" peut déjà être configuré avec une URL absolue
 * (filesystems.disks.public.url) : dans ce cas Storage::url() renvoie
 * "http://host/storage/...". On ne doit alors PAS re-préfixer avec
 * config('app.url') sous peine d'obtenir "http://localhosthttp://localhost/...".
 */
trait ResolvesMediaUrls
{
    protected function absoluteUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        // Déjà une URL complète (ex. lien externe ou CDN Bunny).
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        // Image locale : on la sert via la route CORS `/media/img/{path}` pour
        // qu'elle s'affiche aussi sur Flutter Web (CanvasKit exige le CORS).
        // Fonctionne également sur mobile (Android/iOS).
        return rtrim(config('app.url'), '/') . '/media/img/' . ltrim($path, '/');
    }
}
