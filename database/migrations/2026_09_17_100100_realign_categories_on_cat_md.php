<?php

use App\Models\Category;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Aligne les GENRES existants sur les 15 genres de `cat.md`.
 *
 * Huit genres historiques n'y figurent plus (Action, Thriller, Anime, Crime,
 * Guerre, Musical, Biographie, Dessin animé). Ils portent pourtant des
 * médias, et la clé étrangère `media.category_id` est en ON DELETE CASCADE :
 * supprimer la catégorie supprimerait les films avec elle. On REBASCULE donc
 * les médias vers le genre `cat.md` le plus proche AVANT de retirer l'entrée.
 *
 * Les autres familles de cat.md (casting, cours, appels, awards, billetterie,
 * formats, sélections) ne sont PAS des catégories de médias : elles ont
 * chacune leur module (cf. migrations du 28/09/2026). Cette migration ne
 * touche donc qu'aux genres.
 *
 * Sans aucun ancien genre en base (installation neuve, tests), elle ne fait
 * rien : les genres viennent alors du `CategorySeeder`.
 */
return new class extends Migration
{
    /** Ancien genre => genre de destination (cat.md). */
    private const REMAP = [
        'action' => 'aventure',
        'thriller' => 'policier',
        'crime' => 'policier',
        'anime' => 'animation',
        'dessin-anime' => 'animation',
        'guerre' => 'drame',
        'musical' => 'drame',
        'biographie' => 'drame',
    ];

    public function up(): void
    {
        if (! Category::whereIn('slug', array_keys(self::REMAP))->exists()) {
            return;
        }

        // Les genres de destination doivent exister avant toute bascule.
        (new \Database\Seeders\CategorySeeder())->run();

        foreach (self::REMAP as $from => $to) {
            $source = Category::where('slug', $from)->first();
            $target = Category::where('slug', $to)->first();

            if (! $source || ! $target) {
                continue;
            }

            DB::table('media')
                ->where('category_id', $source->id)
                ->update(['category_id' => $target->id]);

            // Plus aucun média rattaché : la suppression ne cascade sur rien.
            $source->delete();
        }
    }

    public function down(): void
    {
        // Le rattachement média/genre d'avant la bascule n'est pas
        // reconstituable (plusieurs sources fusionnent vers une même cible).
    }
};
