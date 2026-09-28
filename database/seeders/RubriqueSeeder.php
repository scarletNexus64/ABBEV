<?php

namespace Database\Seeders;

use App\Models\Media;
use App\Models\Oeuvre;
use App\Models\Rubrique;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

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
                'name' => 'Avant-première',
                'content_type' => 'media',
                'description' => 'Les sorties à découvrir avant tout le monde.',
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        // « Œuvre adaptable » : documents/scénarios à lire dans l'app.
        $oeuvreAdaptable = Rubrique::updateOrCreate(
            ['slug' => 'oeuvre-adaptable'],
            [
                'name' => 'Œuvre adaptable',
                'content_type' => 'oeuvre',
                'description' => 'Les œuvres littéraires en attente d\'adaptation.',
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        // « Sport » et « Jeux » (cat.md) : sélections de programmes, dont
        // l'admin choisit le contenu depuis « Sélections éditoriales ».
        foreach ([
            'sport' => ['Sport', 'Compétitions, magazines et documentaires sportifs.', 30],
            'jeux' => ['Jeux', 'Jeux télévisés, quiz et divertissements.', 40],
        ] as $slug => [$name, $description, $order]) {
            Rubrique::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $name,
                    'content_type' => 'media',
                    'description' => $description,
                    'is_active' => true,
                    'sort_order' => $order,
                ]
            );
        }

        // Oeuvres de démonstration (PDFs générés).
        $oeuvres = [
            [
                'title'       => 'Le Dernier Voyage',
                'author'      => 'Marie Fontaine',
                'description' => 'Un homme retourne à Marseille après dix ans d\'absence, confronté aux souvenirs et à une ville qui a changé.',
                'pages'       => 4,
                'file_path'   => 'oeuvres/le_dernier_voyage.pdf',
            ],
            [
                'title'       => 'Échos de Minuit',
                'author'      => 'Jean-Pierre Morel',
                'description' => 'Dans un café parisien, un ancien musicien de jazz replonge dans les souvenirs d\'une époque révolue.',
                'pages'       => 3,
                'file_path'   => 'oeuvres/echos_de_minuit.pdf',
            ],
            [
                'title'       => 'La Maison sur la Colline',
                'author'      => 'Sophie Lambert',
                'description' => 'Claire hérite d\'une bastide provençale et découvre le jardin secret de sa grand-tante, artiste solitaire.',
                'pages'       => 4,
                'file_path'   => 'oeuvres/la_maison_sur_la_colline.pdf',
            ],
            [
                'title'       => 'Les Ombres du Fleuve',
                'author'      => 'Antoine Duval',
                'description' => 'Un pêcheur de Loire découvre une barque ancienne échouée sur un banc de sable, avec une carte mystérieuse.',
                'pages'       => 3,
                'file_path'   => 'oeuvres/les_ombres_du_fleuve.pdf',
            ],
            [
                'title'       => 'Fragments Urbains',
                'author'      => 'Camille Rousseau',
                'description' => 'Un photographe et une dessinatrice explorent Paris à l\'aube, capturant la poésie cachée du quotidien.',
                'pages'       => 4,
                'file_path'   => 'oeuvres/fragments_urbains.pdf',
            ],
        ];

        foreach ($oeuvres as $i => $data) {
            Oeuvre::updateOrCreate(
                ['rubrique_id' => $oeuvreAdaptable->id, 'title' => $data['title']],
                [
                    'slug'        => Str::slug($data['title']),
                    'author'      => $data['author'],
                    'description' => $data['description'],
                    'pages'       => $data['pages'],
                    'file_path'   => $data['file_path'],
                    'is_active'   => true,
                    'sort_order'  => $i + 1,
                    'published_at' => now(),
                ]
            );
        }

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
