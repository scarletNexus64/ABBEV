<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Une promesse de soutien est aussi une pièce comptable : une fois les fonds
 * reçus, elle compte dans la jauge publique de l'appel. Supprimer le compte
 * du soutien ne doit donc plus l'effacer (cascade) mais seulement la
 * détacher du compte : montant, statut et contrepartie restent, le lien
 * vers la personne disparaît.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_pledges', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('project_pledges', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Revenir à la cascade impose un compte à chaque promesse.
        DB::table('project_pledges')->whereNull('user_id')->delete();

        Schema::table('project_pledges', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('project_pledges', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
