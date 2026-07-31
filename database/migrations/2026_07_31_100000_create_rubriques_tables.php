<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rubriques : sections thématiques mises en avant dans l'app mobile
 * (« Avant Première », « Œuvre adaptable »…), affichées en chips au-dessus
 * du catalogue.
 *
 * Deux natures de rubrique, distinguées par `content_type` :
 *  - `oeuvre` : documents à lire (PDF) → table `oeuvres` ;
 *  - `media`  : films / séries déjà au catalogue → pivot `media_rubrique`.
 *
 * `required_tier` gouverne l'ACCÈS (≠ `media.tier`, qui ne sert qu'à la
 * rémunération des producteurs et laisse le contenu libre). `null` = rubrique
 * ouverte à tous ; sinon il faut un abonnement actif d'un tier au moins égal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rubriques', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('content_type', ['oeuvre', 'media'])->default('oeuvre');
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();

            // null = accessible sans abonnement.
            $table->enum('required_tier', ['classique', 'standard', 'premium'])
                ->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        // Œuvres : documents PDF rattachés à une rubrique `content_type=oeuvre`.
        Schema::create('oeuvres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubrique_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('author')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('pages')->nullable();
            $table->string('cover_path')->nullable();
            // Chemin sur le disque privé : l'URL est signée à la volée par
            // l'API, jamais stockée en dur.
            $table->string('file_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['rubrique_id', 'is_active']);
        });

        // Pivot : contenus du catalogue mis en avant dans une rubrique
        // `content_type=media` (ex. les films en avant-première).
        Schema::create('media_rubrique', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rubrique_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['rubrique_id', 'media_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_rubrique');
        Schema::dropIfExists('oeuvres');
        Schema::dropIfExists('rubriques');
    }
};
