<?php

namespace App\Console\Commands;

use App\Jobs\ConvertLocalVideoToMp4;
use App\Models\Episode;
use App\Models\Media;
use App\Services\VideoTranscoder;
use Illuminate\Console\Command;

/**
 * Met en file de conversion (file `bunny`) les vidéos locales EXISTANTES qui
 * ne sont pas déjà en MP4 (typiquement des .webm), pour qu'elles soient
 * lisibles sur iOS/Android et téléchargeables hors-ligne.
 *
 * L'encodage ffmpeg est réalisé par le worker déjà en place :
 *   php artisan queue:work --queue=bunny --timeout=0
 * (--timeout=0 est indispensable : un long-métrage peut être long à encoder.)
 *
 *   php artisan videos:transcode-local            # enfile tout
 *   php artisan videos:transcode-local --dry-run  # liste sans rien enfiler
 *   php artisan videos:transcode-local --id=218   # un seul média (film)
 */
class TranscodeLocalVideos extends Command
{
    protected $signature = 'videos:transcode-local
        {--dry-run : Liste les vidéos concernées sans rien mettre en file}
        {--id= : Ne traiter que ce média (id de film)}';

    protected $description = 'Enfile la conversion MP4 (file bunny) des vidéos locales non-MP4 (ex. .webm)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Avertissement non bloquant : la conversion tourne dans le worker,
        // potentiellement sur une autre machine, mais autant prévenir tôt.
        if (! $dryRun && ! app(VideoTranscoder::class)->isAvailable()) {
            $this->warn('⚠️  ffmpeg introuvable sur CETTE machine. Assurez-vous '
                .'qu\'il est installé là où tourne le worker bunny (FFMPEG_BIN).');
        }

        $targets = $this->collectTargets();

        if ($targets->isEmpty()) {
            $this->info('Aucune vidéo locale à convertir. Tout est déjà en MP4. ✅');

            return self::SUCCESS;
        }

        $this->info($targets->count().' vidéo(s) locale(s) non-MP4 :');
        $queued = 0;

        foreach ($targets as $t) {
            $model = $t['model'];
            $this->line("• [{$t['type']} #{$model->id}] {$t['label']} — {$model->video_path}");

            if ($dryRun) {
                continue;
            }

            ConvertLocalVideoToMp4::dispatch($t['job_type'], $model->id);
            $queued++;
        }

        if ($dryRun) {
            $this->comment('(dry-run : rien n’a été mis en file)');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info("$queued conversion(s) mise(s) en file « bunny ».");
        $this->comment('Le worker les traite : php artisan queue:work --queue=bunny --timeout=0');

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{type:string, job_type:string, model:Media|Episode, label:string}>
     */
    private function collectTargets()
    {
        $id = $this->option('id');

        $mediaQuery = Media::query()
            ->where('video_provider', 'local')
            ->whereNotNull('video_path');
        if ($id) {
            $mediaQuery->where('id', (int) $id);
        }

        $targets = collect();

        foreach ($mediaQuery->get() as $m) {
            if (VideoTranscoder::needsTranscode($m->video_path)) {
                $targets->push([
                    'type' => 'film',
                    'job_type' => 'movie',
                    'model' => $m,
                    'label' => $m->title,
                ]);
            }
        }

        // Les épisodes ne sont traités que si aucun --id de film n'est ciblé.
        if (! $id) {
            $episodes = Episode::query()
                ->where('video_provider', 'local')
                ->whereNotNull('video_path')
                ->get();
            foreach ($episodes as $e) {
                if (VideoTranscoder::needsTranscode($e->video_path)) {
                    $targets->push([
                        'type' => 'épisode',
                        'job_type' => 'episode',
                        'model' => $e,
                        'label' => $e->title ?? ('#'.$e->id),
                    ]);
                }
            }
        }

        return $targets;
    }
}
