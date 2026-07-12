<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier d'un forfait (classique/standard/premium). Quand un utilisateur paie un
 * forfait, tout le contenu approuvé du MÊME tier reçoit +1 vue producteur.
 * L'accès au contenu reste libre (le tier ne sert qu'à la rémunération).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->enum('tier', ['classique', 'standard', 'premium'])
                ->default('classique')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('tier');
        });
    }
};
