<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compteur de vues « producteur » sur les épisodes : chaque épisode d'une série
 * du tier acheté reçoit +1 à chaque paiement d'abonnement de ce tier. Le tier
 * de l'épisode est hérité de sa série (media via season → media.tier).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->unsignedBigInteger('producer_views')->default(0)->after('views_count');
        });
    }

    public function down(): void
    {
        Schema::table('episodes', function (Blueprint $table) {
            $table->dropColumn('producer_views');
        });
    }
};
