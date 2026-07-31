<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table de traduction générique (polymorphe).
 *
 * Choix : une table unique plutôt que des colonnes `name_en` / `title_en` sur
 * chaque table métier.
 *   * ajouter une 3ᵉ langue ne demande AUCUNE migration ;
 *   * les tables métier gardent leur schéma (aucun risque sur l'existant) ;
 *   * un contenu non traduit n'occupe simplement aucune ligne — le modèle
 *     retombe alors sur la valeur d'origine.
 *
 * La valeur d'origine reste dans la table métier et fait foi pour la locale
 * par défaut (`fr`) : on ne duplique QUE les traductions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('translations', function (Blueprint $table) {
            $table->id();

            // Cible polymorphe : Category, SubscriptionPlan, Media, Rubrique…
            $table->morphs('translatable');

            $table->string('locale', 5);          // 'en', 'fr', …
            $table->string('field', 64);          // 'name', 'description', 'features'
            $table->text('value')->nullable();

            $table->timestamps();

            // Une seule traduction par (objet, langue, champ).
            $table->unique(
                ['translatable_type', 'translatable_id', 'locale', 'field'],
                'translations_unique'
            );

            // Chargement groupé : « toutes les traductions EN de ces modèles ».
            $table->index(['translatable_type', 'locale'], 'translations_type_locale_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('translations');
    }
};
