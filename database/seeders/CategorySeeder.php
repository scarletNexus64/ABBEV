<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Les 15 GENRES de cat.md — et eux seuls : c'est ce que désigne
 * `media.category_id`.
 *
 * Les autres familles de cat.md ont leur propre module et ne sont plus des
 * catégories : formats (colonne `media.format`), sélections éditoriales
 * (rubriques Avant-première, Sport, Jeux), talents & casting, cours de
 * cinéma, appels à projets, Lions Head Awards, billetterie.
 *
 * Idempotent : `updateOrCreate` sur le slug — un relancement corrige les
 * libellés et l'ordre sans dupliquer ni détacher les médias rattachés.
 */
class CategorySeeder extends Seeder
{
    /** Descriptions FR, dans l'ordre de cat.md. */
    private const DESCRIPTIONS = [
        'drame' => 'Histoires dramatiques et émotionnelles',
        'romance' => "Histoires d'amour et relations romantiques",
        'aventure' => 'Explorations, quêtes et voyages épiques',
        'comedie' => 'Films et séries humoristiques pour vous faire rire',
        'famille' => 'Contenus adaptés à toute la famille',
        'fantastique' => 'Mondes magiques, créatures fantastiques et mythologie',
        'documentaire' => 'Films et séries documentaires sur des sujets réels',
        'docu-fiction' => 'Récits réels reconstitués avec les moyens de la fiction',
        'policier' => 'Enquêtes, suspense et histoires criminelles',
        'historique' => "Reconstitutions d'événements historiques",
        'mystere' => 'Énigmes et investigations mystérieuses',
        'science-fiction' => 'Univers futuristes, technologies avancées et mondes imaginaires',
        'western' => 'Grands espaces, cowboys et duels',
        'animation' => "Films et séries d'animation pour tous les âges",
        'horreur' => "Films et séries d'épouvante et de terreur",
    ];

    public function run(): void
    {
        $order = 0;

        foreach (Category::REFERENCE_GENRES as $slug => $name) {
            Category::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'family' => Category::GENRE,
                    'sort_order' => $order += 10,
                    'description' => self::DESCRIPTIONS[$slug] ?? null,
                ],
            );
        }
    }
}
