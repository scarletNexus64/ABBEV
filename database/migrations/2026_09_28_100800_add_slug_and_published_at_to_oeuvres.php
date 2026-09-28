<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Colonnes attendues par le modèle `Oeuvre`, l'admin (« Œuvres adaptables »)
 * et le `RubriqueSeeder`, mais jamais créées par la migration d'origine :
 * tout ajout d'œuvre échouait sur « column slug does not exist ».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('oeuvres', function (Blueprint $table) {
            if (! Schema::hasColumn('oeuvres', 'slug')) {
                $table->string('slug')->nullable()->after('title');
            }
            if (! Schema::hasColumn('oeuvres', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('sort_order');
            }
        });
    }

    public function down(): void
    {
        Schema::table('oeuvres', function (Blueprint $table) {
            $table->dropColumn(['slug', 'published_at']);
        });
    }
};
