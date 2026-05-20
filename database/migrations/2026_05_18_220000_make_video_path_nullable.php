<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les vidéos ne sont plus stockées en local : elles vivent chez Bunny Stream
 * et sont référencées via video_provider / video_id. La colonne historique
 * video_path doit donc devenir nullable (sinon NOT NULL constraint failed).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->string('video_path')->nullable()->change();
        });

        Schema::table('media', function (Blueprint $table) {
            $table->string('video_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        // On ne re-force pas NOT NULL : d'anciennes lignes peuvent être nulles
        // (vidéos hébergées chez Bunny). Rien à défaire ici.
    }
};
