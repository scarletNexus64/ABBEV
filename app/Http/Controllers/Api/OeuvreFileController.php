<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Oeuvre;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sert le fichier PDF d'une oeuvre depuis le disque prive.
 *
 * La route est protegee par une signature temporaire (URL signee) :
 * l'OeuvreResource genere l'URL signee, le client la consomme sans
 * avoir besoin d'envoyer de header Authorization — ce qui est requis
 * car SfPdfViewer.network() ne supporte pas les headers personnalises
 * de maniere fiable sur toutes les plateformes.
 */
class OeuvreFileController extends Controller
{
    public function __invoke(Oeuvre $oeuvre): BinaryFileResponse
    {
        abort_unless($oeuvre->is_active && $oeuvre->file_path, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($oeuvre->file_path), 404);

        return response()->file($disk->path($oeuvre->file_path), [
            'Content-Type' => 'application/pdf',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
