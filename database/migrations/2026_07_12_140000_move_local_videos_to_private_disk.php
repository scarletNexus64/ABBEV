<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Durcissement sécurité : déplace les vidéos locales du disque PUBLIC
 * (storage/app/public/uploads — accessible directement via le web) vers le
 * disque PRIVÉ (storage/app/private/videos). Elles ne seront plus servies que
 * par une URL signée (LocalVideoStreamController) après contrôle d'abonnement.
 *
 * Met à jour les références : bunny_uploads.local_path/temp_path, ainsi que
 * media.video_path et episodes.video_path (provider = local).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->moveFiles('public', 'uploads', 'local', 'videos');
        $this->rewritePaths('uploads/', 'videos/', 'local');
    }

    public function down(): void
    {
        $this->moveFiles('local', 'videos', 'public', 'uploads');
        $this->rewritePaths('videos/', 'uploads/', 'public');
    }

    /** Déplace tous les fichiers d'un dossier de disque vers un autre. */
    private function moveFiles(string $fromDisk, string $fromDir, string $toDisk, string $toDir): void
    {
        Storage::disk($toDisk)->makeDirectory($toDir);

        $src = Storage::disk($fromDisk)->path($fromDir);
        if (! is_dir($src)) {
            return;
        }

        foreach (glob($src.'/*') ?: [] as $file) {
            if (! is_file($file)) {
                continue;
            }
            $dest = Storage::disk($toDisk)->path($toDir.'/'.basename($file));
            if (! is_file($dest)) {
                @rename($file, $dest);
            }
        }
    }

    /** Réécrit les chemins d'un préfixe vers un autre + resynchronise temp_path. */
    private function rewritePaths(string $from, string $to, string $tempDisk): void
    {
        DB::table('bunny_uploads')->where('local_path', 'like', $from.'%')->get()
            ->each(function ($row) use ($from, $to, $tempDisk) {
                $new = $to.basename($row->local_path);
                $abs = Storage::disk($tempDisk)->path($new);
                DB::table('bunny_uploads')->where('id', $row->id)->update([
                    'local_path' => $new,
                    'temp_path'  => is_file($abs) ? $abs : null,
                ]);
            });

        foreach (['media', 'episodes'] as $table) {
            DB::table($table)
                ->where('video_provider', 'local')
                ->where('video_path', 'like', $from.'%')
                ->get()
                ->each(function ($row) use ($table, $to) {
                    DB::table($table)->where('id', $row->id)
                        ->update(['video_path' => $to.basename($row->video_path)]);
                });
        }
    }
};
