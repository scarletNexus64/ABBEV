<?php

use App\Support\MediaFormat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Format de durée d'un contenu, tel que cat.md le demande :
 *  - film  : court, moyen ou long métrage ;
 *  - série : très court, court ou moyen (durée d'un épisode).
 *
 * Porté par une COLONNE et non par une catégorie : un film est à la fois un
 * « Drame » (son genre, `category_id`) et un « Long métrage » (son format).
 * Tant que les deux vivaient dans la même clé étrangère, il fallait choisir.
 *
 * `format_locked` distingue un format choisi à la main dans l'admin (on n'y
 * touche plus) d'un format déduit de la durée (recalculé quand elle change).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('format', 16)->nullable()->after('duration')->index();
            $table->boolean('format_locked')->default(false)->after('format');
        });

        // Rattrapage : chaque contenu existant reçoit le format que sa durée
        // implique. Les contenus sans durée connue restent sans format.
        MediaFormat::backfill();
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropIndex(['format']);
            $table->dropColumn(['format', 'format_locked']);
        });
    }
};
