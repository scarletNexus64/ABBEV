<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * « Sport » et « Jeux » (cat.md) sont des SÉLECTIONS de programmes, comme
 * l'Avant-première : des rubriques de type `media` dont l'admin choisit le
 * contenu. Créées ici plutôt que par seeder pour exister dès le déploiement,
 * sans intervention manuelle en production. Idempotent.
 */
return new class extends Migration
{
    private const RUBRIQUES = [
        'sport' => [
            'name' => 'Sport',
            'description' => 'Compétitions, magazines et documentaires sportifs.',
            'en' => ['Sports', 'Competitions, sports shows and documentaries.'],
            'sort_order' => 30,
        ],
        'jeux' => [
            'name' => 'Jeux',
            'description' => 'Jeux télévisés, quiz et divertissements.',
            'en' => ['Games', 'Game shows, quizzes and entertainment.'],
            'sort_order' => 40,
        ],
    ];

    public function up(): void
    {
        foreach (self::RUBRIQUES as $slug => $data) {
            $id = DB::table('rubriques')->where('slug', $slug)->value('id');

            if (! $id) {
                $id = DB::table('rubriques')->insertGetId([
                    'name' => $data['name'],
                    'slug' => $slug,
                    'content_type' => 'media',
                    'description' => $data['description'],
                    'is_active' => true,
                    'sort_order' => $data['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach (['name' => $data['en'][0], 'description' => $data['en'][1]] as $field => $value) {
                DB::table('translations')->updateOrInsert(
                    [
                        'translatable_type' => \App\Models\Rubrique::class,
                        'translatable_id' => $id,
                        'locale' => 'en',
                        'field' => $field,
                    ],
                    ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        }
    }

    public function down(): void
    {
        // Des contenus ont pu y être rangés depuis : on ne supprime rien.
    }
};
