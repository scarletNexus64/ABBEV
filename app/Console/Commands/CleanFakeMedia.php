<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\Media;
use App\Models\Season;
use Illuminate\Console\Command;

/**
 * Vide les tables media / seasons / episodes du contenu seedé (Inception,
 * Le Parrain, Breaking Bad, ...) pour laisser place aux vidéos synchronisées
 * depuis Bunny Stream.
 *
 * Usage : php artisan media:clean-fake
 */
class CleanFakeMedia extends Command
{
    protected $signature = 'media:clean-fake {--force : Ne pas demander confirmation}';
    protected $description = 'Vide les tables media / seasons / episodes des fake data';

    public function handle(): int
    {
        $mediaCount = Media::count();
        $seasonCount = Season::count();
        $episodeCount = Episode::count();

        $this->info("À supprimer : {$mediaCount} médias, {$seasonCount} saisons, {$episodeCount} épisodes.");

        if (! $this->option('force') && ! $this->confirm('Confirmer ?', true)) {
            $this->warn('Annulé.');
            return self::SUCCESS;
        }

        Episode::query()->delete();
        Season::query()->delete();
        Media::query()->delete();

        $this->info('✅ Tables vidées.');

        return self::SUCCESS;
    }
}
