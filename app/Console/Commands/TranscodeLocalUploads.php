<?php

namespace App\Console\Commands;

use App\Models\BunnyUpload;
use App\Models\Episode;
use App\Models\Media;
use App\Services\VideoTranscoder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Convertit en MP4 (H.264/AAC, lisible iPhone/Safari) les vidéos LOCALES déjà
 * uploadées qui sont restées dans un format non universel (.webm, .mkv…), par
 * ex. parce que ffmpeg n'était pas installé au moment de l'upload.
 *
 * Les NOUVEAUX uploads sont déjà convertis automatiquement à l'assemblage
 * (worker bunny) ; cette commande rattrape l'existant. Idempotente.
 *
 *   php artisan videos:transcode-uploads
 */
class TranscodeLocalUploads extends Command
{
    protected $signature = 'videos:transcode-uploads';

    protected $description = 'Convertit en MP4 les vidéos locales déjà uploadées restées en .webm/.mkv…';

    public function handle(VideoTranscoder $transcoder): int
    {
        if (! $transcoder->isAvailable()) {
            $this->error('ffmpeg indisponible — installez-le (sudo apt install -y ffmpeg) puis relancez.');

            return self::FAILURE;
        }

        $uploads = BunnyUpload::whereNotNull('local_path')->get()
            ->filter(fn (BunnyUpload $u) => $u->hasLocalCopy()
                && VideoTranscoder::needsTranscode((string) $u->local_path));

        if ($uploads->isEmpty()) {
            $this->info('Aucune vidéo locale à convertir : tout est déjà en MP4.');

            return self::SUCCESS;
        }

        $this->info($uploads->count().' vidéo(s) à convertir…');
        $ok = 0;

        foreach ($uploads as $upload) {
            $source = Storage::disk('local')->path($upload->local_path);
            $this->line("→ #{$upload->id} {$upload->title} ({$upload->local_path})");

            $newAbsolute = $transcoder->toMp4($source);
            if ($newAbsolute === null) {
                $this->error("  échec conversion #{$upload->id} (voir logs).");
                continue;
            }

            $oldRelative = $upload->local_path;
            $newRelative = 'videos/'.basename($newAbsolute);

            // Réattribue tout Media/Episode qui pointait sur l'ancien fichier.
            foreach ([Media::class, Episode::class] as $modelClass) {
                $modelClass::where('video_provider', 'local')
                    ->where('video_path', $oldRelative)
                    ->update(['video_path' => $newRelative]);
            }

            $upload->update(['local_path' => $newRelative, 'temp_path' => $newAbsolute]);

            if ($newAbsolute !== $source && is_file($source)) {
                @unlink($source);
            }

            $ok++;
            $this->info("  ✓ #{$upload->id} → {$newRelative}");
        }

        $this->newLine();
        $this->info("Terminé : {$ok}/{$uploads->count()} converties en MP4.");

        return self::SUCCESS;
    }
}
