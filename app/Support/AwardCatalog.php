<?php

namespace App\Support;

use App\Models\AwardCategory;
use App\Models\AwardEdition;

/**
 * Grille des prix des Lions Head Awards, telle que cat.md la décrit.
 *
 * cat.md liste les prix en abrégé (« meilleur acteur/actrice », « meilleure
 * révélation (masculine et féminine) »…). Conformément à l'usage des
 * cérémonies africaines de référence (AMVCA, Écrans Noirs…), chaque ligne
 * d'interprétation donne DEUX prix, masculin et féminin. Le volet Cinéma et
 * le volet Télévision comptent donc chacun 13 prix, auxquels s'ajoutent les
 * 3 prix Métiers communs (costumes, effets spéciaux, maquillage) — 29 au
 * total.
 *
 * Cette grille n'est qu'un POINT DE DÉPART : chaque édition copie la grille
 * à sa création, puis l'admin renomme, fusionne ou retire librement.
 */
class AwardCatalog
{
    /**
     * [slug, nom FR, nom EN, volet, type de nommé, description FR, description EN]
     *
     * @return list<array{0:string,1:string,2:string,3:string,4:string,5:string,6:string}>
     */
    public static function template(): array
    {
        $rows = [];

        foreach (['cinema' => ['film', 'Meilleur film', 'Best Film'],
                  'television' => ['serie', 'Meilleure série', 'Best Series']] as $scope => [$work, $fr, $en]) {
            $where = $scope === 'cinema' ? 'au cinéma' : 'à la télévision';
            $whereEn = $scope === 'cinema' ? 'in film' : 'on television';

            $rows[] = ["meilleur-{$work}", $fr, $en, $scope, 'media',
                $scope === 'cinema' ? "Le film de l'année." : "La série de l'année.",
                $scope === 'cinema' ? 'The film of the year.' : 'The series of the year.'];

            foreach ([
                ['meilleur-acteur', 'Meilleur acteur', 'Best Actor', "Meilleure interprétation masculine dans un premier rôle {$where}.", "Best leading performance by an actor {$whereEn}."],
                ['meilleure-actrice', 'Meilleure actrice', 'Best Actress', "Meilleure interprétation féminine dans un premier rôle {$where}.", "Best leading performance by an actress {$whereEn}."],
                ['second-role-masculin', 'Meilleur second rôle masculin', 'Best Supporting Actor', "Meilleur acteur dans un second rôle {$where}.", "Best supporting actor {$whereEn}."],
                ['second-role-feminin', 'Meilleur second rôle féminin', 'Best Supporting Actress', "Meilleure actrice dans un second rôle {$where}.", "Best supporting actress {$whereEn}."],
                ['revelation-masculine', 'Révélation masculine', 'Male Breakthrough', "Le jeune talent masculin qui s'est révélé {$where}.", "The male newcomer who broke through {$whereEn}."],
                ['revelation-feminine', 'Révélation féminine', 'Female Breakthrough', "Le jeune talent féminin qui s'est révélé {$where}.", "The female newcomer who broke through {$whereEn}."],
                ['meilleur-realisateur', 'Meilleur réalisateur', 'Best Director', "Meilleure mise en scène {$where}.", "Best direction {$whereEn}."],
                ['meilleure-photo', 'Meilleure photographie', 'Best Cinematography', "Meilleure direction de la photographie {$where}.", "Best cinematography {$whereEn}."],
                ['meilleur-son', 'Meilleur son', 'Best Sound', "Meilleur travail sonore (prise de son, mixage) {$where}.", "Best sound work (recording, mixing) {$whereEn}."],
                ['meilleur-montage', 'Meilleur montage', 'Best Editing', "Meilleur montage {$where}.", "Best editing {$whereEn}."],
                ['meilleure-musique', 'Meilleure musique', 'Best Score', "Meilleure musique originale {$where}.", "Best original score {$whereEn}."],
                ['meilleurs-decors', 'Meilleurs décors', 'Best Production Design', "Meilleurs décors {$where}.", "Best production design {$whereEn}."],
            ] as [$slug, $nameFr, $nameEn, $descFr, $descEn]) {
                $rows[] = ["{$slug}-{$scope}", $nameFr, $nameEn, $scope, 'person', $descFr, $descEn];
            }
        }

        foreach ([
            ['meilleurs-costumes', 'Meilleure création de costumes', 'Best Costume Design', 'Création de costumes, cinéma et télévision confondus.', 'Costume design, film and television combined.'],
            ['meilleurs-effets-speciaux', 'Meilleurs effets spéciaux', 'Best Visual Effects', 'Effets spéciaux et visuels, cinéma et télévision confondus.', 'Special and visual effects, film and television combined.'],
            ['meilleur-maquillage', 'Meilleur maquillage', 'Best Make-Up', 'Maquillage et coiffure, cinéma et télévision confondus.', 'Make-up and hairstyling, film and television combined.'],
        ] as [$slug, $nameFr, $nameEn, $descFr, $descEn]) {
            $rows[] = [$slug, $nameFr, $nameEn, 'metiers', 'person', $descFr, $descEn];
        }

        return $rows;
    }

    /**
     * Crée les prix de la grille manquants dans une édition (idempotent :
     * un prix déjà présent, même renommé, n'est ni dupliqué ni écrasé).
     */
    public static function applyTo(AwardEdition $edition): int
    {
        $created = 0;

        foreach (self::template() as $i => [$slug, $nameFr, $nameEn, $scope, $type, $descFr, $descEn]) {
            $category = AwardCategory::firstOrCreate(
                ['award_edition_id' => $edition->id, 'slug' => $slug],
                [
                    'name' => $nameFr,
                    'scope' => $scope,
                    'nominee_type' => $type,
                    'description' => $descFr,
                    'sort_order' => ($i + 1) * 10,
                ],
            );

            if ($category->wasRecentlyCreated) {
                $category->setTranslation('name', 'en', $nameEn);
                $category->setTranslation('description', 'en', $descEn);
                $created++;
            }
        }

        return $created;
    }
}
