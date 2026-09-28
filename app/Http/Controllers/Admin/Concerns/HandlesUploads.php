<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Téléversements de l'admin.
 *
 *  - images (portraits, visuels) : disque PUBLIC, servies par la route CORS
 *    `/media/img/…` (cf. ResolvesMediaUrls) ;
 *  - documents (PDF de cours, pièces de candidature) : disque PRIVÉ, jamais
 *    exposés sans URL signée ou session admin.
 *
 * Le fichier remplacé est supprimé APRÈS l'enregistrement du nouveau : un
 * échec d'écriture ne fait jamais perdre l'image en place.
 */
trait HandlesUploads
{
    protected function replaceImage(Request $request, string $field, string $folder, ?string $current): ?string
    {
        if (! $request->hasFile($field)) {
            return $current;
        }

        $path = $request->file($field)->store($folder, 'public');
        $this->forgetPublic($current);

        return $path;
    }

    protected function replacePrivateFile(Request $request, string $field, string $folder, ?string $current): ?string
    {
        if (! $request->hasFile($field)) {
            return $current;
        }

        $path = $request->file($field)->store($folder, 'local');
        if ($current && ! str_starts_with($current, 'demo/')) {
            Storage::disk('local')->delete($current);
        }

        return $path;
    }

    protected function forgetPublic(?string $path): void
    {
        // Les URLs externes (CDN, TMDB) ne sont pas à nous : on n'y touche pas.
        if ($path && ! str_starts_with($path, 'http')) {
            Storage::disk('public')->delete($path);
        }
    }
}
