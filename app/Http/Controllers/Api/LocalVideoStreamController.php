<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Episode;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Sert une vidéo LOCALE (video_provider = 'local') via une URL SIGNÉE à durée
 * de vie courte, générée par le WatchApiController après vérification de
 * l'abonnement. Remplace l'ancienne URL publique permanente (storage/uploads/…)
 * qui était partageable et téléchargeable à l'infini — vecteur de scraping.
 *
 * La signature (middleware `signed`) EST le contrôle d'accès : pas d'en-tête
 * d'auth possible depuis un lecteur vidéo (comme les URLs signées Bunny).
 * Supporte les requêtes Range (seek) via BinaryFileResponse.
 */
class LocalVideoStreamController extends Controller
{
    private const MIME = [
        'mp4'  => 'video/mp4',
        'm4v'  => 'video/mp4',
        'webm' => 'video/webm',
        'mov'  => 'video/quicktime',
        'mkv'  => 'video/x-matroska',
        'avi'  => 'video/x-msvideo',
        'ts'   => 'video/mp2t',
    ];

    public function __invoke(Request $request, string $type, int $id): BinaryFileResponse
    {
        $model = $type === 'episode' ? Episode::find($id) : Media::find($id);

        abort_unless($model && $model->video_provider === 'local' && $model->video_path, 404);

        $path = Storage::disk('local')->path($model->video_path);
        abort_unless(is_file($path), 404);

        $ext  = strtolower(pathinfo($model->video_path, PATHINFO_EXTENSION));
        $mime = self::MIME[$ext] ?? 'application/octet-stream';

        // Téléchargement offline (dl=1) → pièce jointe ; sinon lecture inline.
        if ($request->boolean('dl')) {
            $filename = $this->safeName($model->title ?? 'video') . '.' . ($ext ?: 'mp4');

            return response()->download($path, $filename, ['Content-Type' => $mime]);
        }

        return response()->file($path, ['Content-Type' => $mime]);
    }

    private function safeName(string $label): string
    {
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $label);

        return trim((string) $name, '_') ?: 'video';
    }
}
