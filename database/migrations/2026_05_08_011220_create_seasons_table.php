<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seasons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_id')->constrained('media')->onDelete('cascade');
            $table->integer('season_number'); // Numéro de la saison (1, 2, 3...)
            $table->string('title')->nullable(); // Titre de la saison (optionnel)
            $table->text('description')->nullable();
            $table->string('thumbnail_path')->nullable(); // Image de la saison
            $table->year('release_year')->nullable();
            $table->integer('episodes_count')->default(0); // Nombre d'épisodes
            $table->timestamps();

            // Index pour optimiser les requêtes
            $table->unique(['media_id', 'season_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seasons');
    }
};
