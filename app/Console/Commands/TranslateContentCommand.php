<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Models\Translation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Import/export des traductions de contenu au format CSV.
 *
 * Sert au cas que le [TranslationSeeder] ne peut pas couvrir : les titres et
 * synopsis des médias. Ce sont des textes rédactionnels propres à chaque
 * œuvre — aucun mapping figé ne peut les produire. Le flux prévu est :
 *
 *   1. `php artisan translate:content export --locale=en > todo.csv`
 *   2. remplir la colonne `translation` (traducteur, agence, DeepL…)
 *   3. `php artisan translate:content import todo.csv --locale=en`
 *
 * L'export ne liste que ce qui MANQUE : relancer la commande après un import
 * partiel ne re-propose pas ce qui est déjà traduit.
 */
class TranslateContentCommand extends Command
{
    protected $signature = 'translate:content
        {action : export ou import}
        {file? : fichier CSV (obligatoire pour import)}
        {--locale=en : langue cible}
        {--all : à l\'export, inclure aussi les entrées déjà traduites}';

    protected $description = 'Exporte/importe les traductions de contenu (titres, synopsis) en CSV';

    public function handle(): int
    {
        return match ($this->argument('action')) {
            'export' => $this->export(),
            'import' => $this->import(),
            default  => $this->fail0('Action inconnue : utilisez « export » ou « import ».'),
        };
    }

    private function export(): int
    {
        $locale = $this->option('locale');
        $rows = [];

        foreach (Media::query()->orderBy('id')->cursor() as $media) {
            foreach (['title', 'description'] as $field) {
                $original = (string) $media->getAttribute($field);
                if ($original === '') {
                    continue;
                }

                $existing = $media->translations
                    ->firstWhere(fn ($t) => $t->field === $field && $t->locale === $locale)?->value;

                if (! $this->option('all') && $existing) {
                    continue;
                }

                $rows[] = [$media->id, $field, $original, $existing ?? ''];
            }
        }

        $out = fopen('php://output', 'w');
        fputcsv($out, ['media_id', 'field', 'original', 'translation']);
        foreach ($rows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);

        $this->components->info(sprintf('%d ligne(s) à traduire en « %s ».', count($rows), $locale));

        return self::SUCCESS;
    }

    private function import(): int
    {
        $file = $this->argument('file');
        if (! $file || ! is_readable($file)) {
            return $this->fail0("Fichier illisible : {$file}");
        }

        $locale = $this->option('locale');
        $handle = fopen($file, 'r');
        $header = fgetcsv($handle);

        if ($header !== ['media_id', 'field', 'original', 'translation']) {
            fclose($handle);
            return $this->fail0('En-tête CSV inattendu. Repartez d\'un export.');
        }

        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($handle, $locale, &$imported, &$skipped) {
            while (($row = fgetcsv($handle)) !== false) {
                [$id, $field, , $translation] = array_pad($row, 4, '');

                // Ligne non remplie : on ne crée pas une traduction vide, qui
                // masquerait l'original à l'affichage.
                if (trim((string) $translation) === '') {
                    $skipped++;
                    continue;
                }

                if (! in_array($field, ['title', 'description'], true)) {
                    $skipped++;
                    continue;
                }

                Translation::updateOrCreate(
                    [
                        'translatable_type' => Media::class,
                        'translatable_id'   => (int) $id,
                        'locale'            => $locale,
                        'field'             => $field,
                    ],
                    ['value' => $translation],
                );
                $imported++;
            }
        });

        fclose($handle);

        $this->components->info("{$imported} traduction(s) importée(s), {$skipped} ignorée(s).");

        return self::SUCCESS;
    }

    private function fail0(string $message): int
    {
        $this->components->error($message);

        return self::FAILURE;
    }
}
