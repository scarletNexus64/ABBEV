<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cours de cinéma (cat.md : « document » et « vidéos »).
 *
 * Un cours est soit un parcours VIDÉO, soit un cours en DOCUMENTS (PDF) :
 * c'est la sous-catégorie que l'app affiche en onglet. Il se découpe en
 * leçons ordonnées ; certaines peuvent être ouvertes en « aperçu » à tout
 * compte connecté, le reste suit `required_tier` (même règle d'accès que les
 * rubriques : null = ouvert à tout compte connecté).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            // video | document
            $table->string('type', 12)->default('video');
            // realisation | scenario | jeu | image | son | montage | production | decors-costumes | autre
            $table->string('discipline', 20)->default('realisation');
            // debutant | intermediaire | avance
            $table->string('level', 16)->default('debutant');
            $table->string('instructor_name')->nullable();
            $table->string('instructor_title')->nullable();
            $table->string('summary', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('required_tier', 12)->nullable();
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'type']);
        });

        Schema::create('course_lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title');
            $table->string('summary', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            // Leçon consultable sans abonnement (compte connecté requis).
            $table->boolean('is_preview')->default(false);
            // Vidéo : Bunny Stream (GUID) OU lien direct MP4/HLS.
            $table->string('video_provider', 16)->nullable();
            $table->string('video_id', 128)->nullable();
            $table->string('video_library_id', 64)->nullable();
            $table->string('video_url')->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            // Document : PDF sur le disque privé, servi par URL signée.
            $table->string('file_path')->nullable();
            $table->unsignedInteger('pages')->nullable();
            $table->timestamps();

            $table->index(['course_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_lessons');
        Schema::dropIfExists('courses');
    }
};
