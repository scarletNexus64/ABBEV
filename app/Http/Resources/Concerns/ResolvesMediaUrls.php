<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Support\Facades\Storage;

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

        $url = Storage::disk('public')->url($path);

        // Storage::url() a déjà renvoyé une URL absolue : on la garde telle quelle.
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        // Sinon c'est un chemin relatif (/storage/...) : on le rend absolu.
        return rtrim(config('app.url'), '/') . '/' . ltrim($url, '/');
    }
}
