<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Espaces producteurs.
 *
 *  - Le rôle « assistant » disparaît : la modération passe au producteur, qui
 *    valide ses propres contenus (l'admin garde la main sur le tier).
 *  - Un producteur peut inviter une équipe : les membres ont le rôle
 *    `producer`, pointent vers leur producteur (`users.producer_id`) et ne
 *    voient que les modules cochés dans `users.permissions`.
 *  - Chaque module reçoit un propriétaire (`producer_id`). NULL = donnée de
 *    la plateforme, gérée par l'admin (c'est le cas de tout l'existant).
 */
return new class extends Migration
{
    /** Tables des modules cloisonnés par producteur. */
    private const OWNED_TABLES = [
        'agents', 'talents', 'casting_calls', 'courses',
        'project_calls', 'award_editions', 'screenings', 'oeuvres',
    ];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Supprimer un producteur ne supprime pas son équipe : les membres
            // sont rétrogradés en abonnés (voir User::booted).
            $table->foreignId('producer_id')->nullable()->after('role')
                ->constrained('users')->nullOnDelete();
            $table->json('permissions')->nullable()->after('producer_id');
        });

        foreach (self::OWNED_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                // Producteur supprimé : ses données restent, rattachées à la plateforme.
                $table->foreignId('producer_id')->nullable()
                    ->constrained('users')->nullOnDelete();
            });
        }

        // Les éventuels assistants deviennent producteurs (espace vide, que
        // l'admin peut supprimer s'il n'a plus lieu d'être).
        DB::table('users')->where('role', 'assistant')->update(['role' => 'producer']);
    }

    public function down(): void
    {
        foreach (self::OWNED_TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropConstrainedForeignId('producer_id');
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producer_id');
            $table->dropColumn('permissions');
        });
    }
};
