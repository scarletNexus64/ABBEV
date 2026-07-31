<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\Rubrique;
use Illuminate\Database\Seeder;

/**
 * Rubriques affichées en chips au-dessus du catalogue mobile.
 *
 * Idempotent (`updateOrCreate` sur le slug) : relancer le seeder ne crée pas
 * de doublons et ne réécrit pas les contenus déjà rattachés.
 */
class RubriqueSeeder extends Seeder
{
    public function run(): void
    {
        // « Avant Première » : des films du catalogue mis en avant.
        // Ouverte à tous pour l'instant (required_tier = null) ; passer à
        // 'standard' ou 'premium' pour la réserver aux abonnés.
        $avantPremiere = Rubrique::updateOrCreate(
            ['slug' => 'avant-premiere'],
            [
                'name' => 'Avant Première',
                'content_type' => 'media',
                'description' => 'Les sorties à découvrir avant tout le monde.',
                'required_tier' => null,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        // « Œuvre adaptable » : documents/scénarios à lire dans l'app.
        Rubrique::updateOrCreate(
            ['slug' => 'oeuvre-adaptable'],
            [
                'name' => 'Œuvre adaptable',
                'content_type' => 'oeuvre',
                'description' => 'Les œuvres littéraires en attente d’adaptation.',
                'required_tier' => null,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        // Amorce d'« Avant Première » : les films publiés les plus récents.
        // Sans ce rattachement la rubrique s'ouvrirait sur un écran vide.
        // `syncWithoutDetaching` préserve une sélection éditoriale existante.
        if ($avantPremiere->media()->count() === 0) {
            $recent = Media::query()
                ->where('type', 'movie')
                ->published()
                ->orderByDesc('published_at')
                ->limit(10)
                ->pluck('id');

            $avantPremiere->media()->syncWithoutDetaching($recent->all());
        }
    }
}
