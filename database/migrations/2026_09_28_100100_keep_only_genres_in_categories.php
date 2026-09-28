<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Recentre la table `categories` sur ce qu'elle est : les GENRES des films et
 * séries (`media.category_id`).
 *
 * Le référentiel de cat.md y avait été rangé à plat — rangs d'acteurs, prix
 * des Lions Head Awards, appels à projets, cours… — alors qu'aucune de ces
 * entrées ne désigne le genre d'un film. Chaque famille a désormais son
 * propre module (talents, awards, appels, cours, billetterie, formats,
 * sélections éditoriales) ; leurs anciennes lignes n'ont plus d'usage.
 *
 * Règle de sûreté, à cause du ON DELETE CASCADE de `media.category_id` :
 * AUCUNE catégorie portant encore un média n'est supprimée. Une ancienne
 * entrée non vide reste en base comme genre (l'admin la signale « hors
 * référentiel » et propose de réaffecter ses contenus avant suppression).
 */
return new class extends Migration
{
    /** Slugs de l'ancien catalogue qui ne désignaient pas un genre. */
    private const LEGACY_NON_GENRE = [
        'films', 'series', 'reservation-cinema', 'cours-cinema',
        'financement-projets', 'lions-head-awards',
        'cours-realisation', 'cours-scenarisation', 'cours-acteur',
        'cours-production', 'cours-montage', 'cours-photographie',
        'cours-son-musique',
        'projet-court-metrage', 'projet-long-metrage', 'projet-web-serie',
        'projet-documentaire', 'projet-animation',
        'award-meilleur-acteur', 'award-meilleure-actrice',
        'award-meilleur-film', 'award-meilleure-serie',
        'award-meilleur-realisateur', 'award-meilleur-scenario',
        'award-revelation',
    ];

    public function up(): void
    {
        $this->moveSportToItsRubrique();

        $candidates = DB::table('categories')
            ->where(function ($q) {
                $q->where('family', '!=', 'genre')
                    ->orWhereIn('slug', self::LEGACY_NON_GENRE);
            })
            ->pluck('id');

        foreach ($candidates as $id) {
            $hasMedia = DB::table('media')->where('category_id', $id)->exists();

            if ($hasMedia) {
                // Conservée (jamais de cascade sur un média) mais rendue
                // visible comme genre, pour que l'admin puisse la vider.
                DB::table('categories')->where('id', $id)->update(['family' => 'genre']);
                continue;
            }

            DB::table('translations')
                ->where('translatable_type', \App\Models\Category::class)
                ->where('translatable_id', $id)
                ->delete();
            DB::table('categories')->where('id', $id)->delete();
        }
    }

    /**
     * « Sport » n'est pas un genre mais une sélection de programmes : ses
     * éventuels contenus rejoignent la rubrique Sport, et leur genre bascule
     * sur « Documentaire » (le plus proche parmi les 15 de cat.md).
     */
    private function moveSportToItsRubrique(): void
    {
        $sport = DB::table('categories')->where('slug', 'sport')->first();
        if (! $sport) {
            return;
        }

        $mediaIds = DB::table('media')->where('category_id', $sport->id)->pluck('id');
        $fallback = DB::table('categories')->where('slug', 'documentaire')->value('id');

        if ($mediaIds->isNotEmpty() && $fallback) {
            $rubriqueId = DB::table('rubriques')->where('slug', 'sport')->value('id')
                ?? DB::table('rubriques')->insertGetId([
                    'name' => 'Sport',
                    'slug' => 'sport',
                    'content_type' => 'media',
                    'description' => 'Compétitions, magazines et documentaires sportifs.',
                    'is_active' => true,
                    'sort_order' => 30,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            foreach ($mediaIds as $i => $mediaId) {
                DB::table('media_rubrique')->insertOrIgnore([
                    'rubrique_id' => $rubriqueId,
                    'media_id' => $mediaId,
                    'sort_order' => $i + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('media')->whereIn('id', $mediaIds)->update(['category_id' => $fallback]);
        }

        // Vide désormais : elle tombe dans le nettoyage général.
        DB::table('categories')->where('id', $sport->id)->update(['family' => 'rubrique']);
    }

    public function down(): void
    {
        // Les entrées supprimées étaient vides ; le `CategorySeeder` ne les
        // recrée plus. Rien à restaurer.
    }
};
