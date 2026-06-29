<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bunny_uploads', function (Blueprint $table) {
            // Chemin (relatif au disque public) d'une copie locale lisible directement,
            // utilisée comme fallback de test tant que Bunny n'est pas disponible.
            $table->string('local_path')->nullable()->after('temp_path');
        });
    }

    public function down(): void
    {
        Schema::table('bunny_uploads', function (Blueprint $table) {
            $table->dropColumn('local_path');
        });
    }
};
