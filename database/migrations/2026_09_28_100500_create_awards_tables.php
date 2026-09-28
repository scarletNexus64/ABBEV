<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lions Head Awards — vote ouvert au public.
 *
 *  - une ÉDITION (ex. « Lions Head Awards 2026 ») porte la période de vote et
 *    la date de publication des résultats ;
 *  - ses CATÉGORIES (prix) sont regroupées en Cinéma, Télévision et Métiers ;
 *  - chaque catégorie a ses NOMMÉS : une œuvre du catalogue, un talent de
 *    l'annuaire, ou une entrée libre (nom + photo) ;
 *  - un VOTE par compte et par catégorie (contrainte unique) : c'est la
 *    garantie anti-bourrage de base, doublée d'un throttle sur l'API.
 *
 * `votes_count` est un compteur dénormalisé, tenu dans la même transaction
 * que l'insertion du vote : les tableaux de résultats ne recomptent rien.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('award_editions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('year');
            $table->string('tagline')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->dateTime('voting_starts_at')->nullable();
            $table->dateTime('voting_ends_at')->nullable();
            $table->dateTime('ceremony_at')->nullable();
            $table->string('ceremony_venue')->nullable();
            $table->timestamp('results_published_at')->nullable();
            // Édition mise en avant dans l'app (une seule à la fois).
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('award_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('award_edition_id')->constrained('award_editions')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            // cinema | television | metiers
            $table->string('scope', 12)->default('cinema');
            // media (une œuvre est nommée) | person (un talent est nommé)
            $table->string('nominee_type', 8)->default('person');
            $table->string('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['award_edition_id', 'slug']);
        });

        Schema::create('award_nominees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('award_category_id')->constrained('award_categories')->cascadeOnDelete();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->foreignId('talent_id')->nullable()->constrained('talents')->nullOnDelete();
            $table->string('name');
            // Œuvre associée, rôle tenu, poste… (« pour Les Bois Sacrés »).
            $table->string('subtitle')->nullable();
            $table->string('photo_path')->nullable();
            $table->unsignedInteger('votes_count')->default(0);
            $table->boolean('is_winner')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('award_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('award_category_id')->constrained('award_categories')->cascadeOnDelete();
            $table->foreignId('award_nominee_id')->constrained('award_nominees')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->unique(['award_category_id', 'user_id']);
            $table->index('award_nominee_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('award_votes');
        Schema::dropIfExists('award_nominees');
        Schema::dropIfExists('award_categories');
        Schema::dropIfExists('award_editions');
    }
};
