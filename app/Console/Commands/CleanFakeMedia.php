<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\Media;
use App\Models\Season;
use Illuminate\Console\Command;

/**
 * Vide les tables media / seasons / episodes du contenu de DÉMONSTRATION
 * (catalogue vitrine seedé : posters TMDB + une seule vidéo Bunny) pour
 * laisser place aux vrais contenus inédits uploadés via le dashboard.
 *
 * Pourquoi : ce catalogue de démo (affiches/titres de vrais films connus
 * récupérés du CDN TMDB) a fait croire à Apple à du contenu piraté
 * (Guideline 5.2.2). Il DOIT être retiré de la production.
 *
 * Les suppressions cascadent : supprimer un Media efface aussi ses saisons,
 * épisodes, entrées d'historique et de listes (FK cascadeOnDelete).
 *
 * Usage :
 *   php artisan media:clean-fake --dry-run   (montre sans supprimer)
 *   php artisan media:clean-fake             (demande confirmation)
 *   php artisan media:clean-fake --force     (sans confirmation)
 */
class CleanFakeMedia extends Command
{
    protected $signature = 'media:clean-fake
        {--force : Ne pas demander confirmation}
        {--dry-run : Affiche ce qui serait supprimé sans rien supprimer}';

    protected $description = 'Vide media / seasons / episodes du contenu de démonstration';

    public function handle(): int
    {
        $mediaCount = Media::count();
        $seasonCount = Season::count();
        $episodeCount = Episode::count();

        $this->info("Contenu actuel : {$mediaCount} médias, {$seasonCount} saisons, {$episodeCount} épisodes.");

        if ($mediaCount === 0) {
            $this->info('Aucun média à supprimer. Rien à faire.');
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('--dry-run : aucune suppression effectuée.');
            $this->line('Relance sans --dry-run pour supprimer réellement.');
            return self::SUCCESS;
        }

        if (! $this->option('force')
            && ! $this->confirm("Supprimer DÉFINITIVEMENT ces {$mediaCount} médias ?", false)) {
            $this->warn('Annulé.');
            return self::SUCCESS;
        }

        // L'ordre explicite reste un garde-fou même si les FK cascadent.
        Episode::query()->delete();
        Season::query()->delete();
        Media::query()->delete();

        $this->info('✅ Contenu de démonstration supprimé.');
        $this->newLine();
        $this->line('Prochaine étape : ajoute tes vrais contenus depuis le dashboard');
        $this->line('(Médias → Ajouter), en choisissant ta vidéo Bunny et tes propres');
        $this->line('affiches. Ne resoumets PAS l\'app à Apple tant que le catalogue est vide.');

        return self::SUCCESS;
    }
}
