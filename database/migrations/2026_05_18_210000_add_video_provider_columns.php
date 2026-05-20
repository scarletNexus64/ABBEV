<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute les colonnes nécessaires pour découpler le stockage vidéo
 * (Bunny Stream, S3, etc.) du chemin local.
 *
 * - video_provider : 'bunny' | 'local' | 's3' | 'mux' ...
 * - video_id        : identifiant côté provider (ex: GUID Bunny)
 * - video_library_id: ID de la library Bunny (si plusieurs)
 * - video_metadata  : JSON pour ce que le provider renvoie d'autre
 *                    (durée détectée, résolutions disponibles, thumbnail, ...)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->string('video_provider', 32)->nullable()->after('video_path');
            $table->string('video_id', 128)->nullable()->after('video_provider');
            $table->string('video_library_id', 64)->nullable()->after('video_id');
            $table->json('video_metadata')->nullable()->after('video_library_id');
        });

        Schema::table('episodes', function (Blueprint $table) {
            $table->string('video_provider', 32)->nullable()->after('video_path');
            $table->string('video_id', 128)->nullable()->after('video_provider');
            $table->string('video_library_id', 64)->nullable()->after('video_id');
            $table->json('video_metadata')->nullable()->after('video_library_id');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['video_provider', 'video_id', 'video_library_id', 'video_metadata']);
        });
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropColumn(['video_provider', 'video_id', 'video_library_id', 'video_metadata']);
        });
    }
};
