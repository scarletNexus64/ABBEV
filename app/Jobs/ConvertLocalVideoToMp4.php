<?php

namespace App\Jobs;

use App\Models\Episode;
use App\Models\Media;
use App\Services\VideoTranscoder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Convertit en MP4 (H.264/AAC) la vidéo locale d'un Media (film) ou d'un
 * Episode dont le fichier n'est pas déjà lisible partout (.webm, .mkv…).
 *
 * Tourne sur la file **bunny** (même worker que les transferts Bunny), qui est
 * lancée avec `--timeout=0` : indispensable, un encodage ffmpeg de long-métrage
 * peut durer plusieurs minutes.
 *
 *   php artisan queue:work --queue=bunny --timeout=0
 *
 * Idempotent : si la vidéo est déjà en MP4 (ou le fichier absent), le job ne
 * fait rien. Met à jour `video_path` vers le nouveau .mp4 puis supprime
 * l'original.
 */
class ConvertLocalVideoToMp4 implements ShouldQueue
{
    use Queueable;

    /** Encodage potentiellement long → aucune borne côté worker. */
    public int $timeout = 0;

    /** Un seul essai : un échec ffmpeg n'est pas transitoire. */
    public int $tries = 1;

    /**
     * @param string $modelType 'movie' (Media) | 'episode' (Episode)
     */
    public function __construct(public string $modelType, public int $modelId)
    {
        // File dédiée « transcode » : l'encodage ffmpeg (long, CPU) ne doit pas
        // bloquer les transferts réseau de la file « bunny ».
        $this->onConnection('transcode');
        $this->onQueue('transcode');
    }

    public function handle(VideoTranscoder $transcoder): void
    {
        /** @var Media|Episode|null $model */
        $model = $this->modelType === 'episode'
            ? Episode::find($this->modelId)
            : Media::find($this->modelId);

        if (! $model) {
            return;
        }

        if ($model->video_provider !== 'local' || ! $model->video_path) {
            return; // passé chez Bunny entre-temps, ou pas de fichier local
        }

        if (! VideoTranscoder::needsTranscode($model->video_path)) {
            return; // déjà en MP4
        }

        // Disque PRIVÉ (storage/app/private/videos) : où vivent les vidéos locales.
        $absolute = Storage::disk('local')->path($model->video_path);
        if (! is_file($absolute)) {
            Log::warning('[ConvertLocalVideoToMp4] Fichier local absent', [
                'type' => $this->modelType,
                'id' => $this->modelId,
                'path' => $model->video_path,
            ]);

            return;
        }

        Log::info('[ConvertLocalVideoToMp4] Conversion démarrée', [
            'type' => $this->modelType,
            'id' => $this->modelId,
            'from' => $model->video_path,
        ]);

        $newAbsolute = $transcoder->toMp4($absolute);
        if ($newAbsolute === null) {
            Log::error('[ConvertLocalVideoToMp4] Échec conversion (voir logs ffmpeg)', [
                'type' => $this->modelType,
                'id' => $this->modelId,
            ]);

            return;
        }

        $oldRelative = $model->video_path;
        $newRelative = 'videos/' . basename($newAbsolute);
        $model->update(['video_path' => $newRelative]);

        // Resynchronise l'upload d'origine (le picker/la liste retrouvent le MP4).
        \App\Models\BunnyUpload::where('local_path', $oldRelative)
            ->update(['local_path' => $newRelative, 'temp_path' => $newAbsolute]);

        // Supprime l'ancien fichier non-MP4 (remplacé par le .mp4).
        if ($newAbsolute !== $absolute && is_file($absolute)) {
            @unlink($absolute);
        }

        Log::info('[ConvertLocalVideoToMp4] Conversion terminée', [
            'type' => $this->modelType,
            'id' => $this->modelId,
            'to' => $newRelative,
        ]);
    }
}
