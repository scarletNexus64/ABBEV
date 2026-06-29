<?php

namespace App\Console\Commands;

use App\Models\BunnyUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Housekeeping des uploads Bunny :
 *   1. Marque "failed" les uploads restés en réception (uploading) sans activité
 *      depuis plus de 6h (onglet abandonné en cours de réception).
 *   2. Supprime les fichiers temporaires orphelins de storage/app/bunny_uploads
 *      qui ne sont plus référencés par aucune ligne active (transfert terminé
 *      ou ligne supprimée).
 *
 * Les chunks partiels de pion/laravel-chunk-upload sont purgés séparément par
 * la planification intégrée du package (config/chunk-upload.php → clear).
 */
class CleanupBunnyUploads extends Command
{
    protected $signature = 'bunny:uploads:cleanup {--hours=6 : Inactivité (heures) avant d\'abandonner un upload bloqué}';

    protected $description = 'Nettoie les uploads Bunny bloqués et leurs fichiers temporaires orphelins.';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');

        // 1. Uploads "uploading" inactifs → failed.
        $stale = BunnyUpload::where('status', 'uploading')
            ->where('updated_at', '<', now()->subHours($hours))
            ->get();

        foreach ($stale as $upload) {
            $upload->markFailed("Réception abandonnée (inactif depuis plus de {$hours}h).");
            if ($upload->temp_path && is_file($upload->temp_path)) {
                @unlink($upload->temp_path);
            }
        }
        $this->info("{$stale->count()} upload(s) bloqué(s) marqué(s) en échec.");

        // 2. Fichiers temporaires orphelins.
        $dir = storage_path('app/bunny_uploads');
        $removed = 0;

        if (File::isDirectory($dir)) {
            $referenced = BunnyUpload::whereNotNull('temp_path')->pluck('temp_path')->all();

            foreach (File::files($dir) as $file) {
                if (! in_array($file->getPathname(), $referenced, true)) {
                    @unlink($file->getPathname());
                    $removed++;
                }
            }
        }
        $this->info("{$removed} fichier(s) temporaire(s) orphelin(s) supprimé(s).");

        return self::SUCCESS;
    }
}
