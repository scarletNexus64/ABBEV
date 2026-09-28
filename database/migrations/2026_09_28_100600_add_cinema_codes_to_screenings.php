<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Billetterie (cat.md : « Réservation ticket — salle cinéma, achat code »).
 *
 * Une offre de billetterie est désormais de l'un de ces deux types :
 *  - `seance` : une projection datée dans une salle (l'existant) ;
 *  - `code`   : un CODE CINÉMA prépayé, sans séance fixe, valable jusqu'à
 *               `valid_until` dans la salle partenaire (e-billet ouvert).
 *
 * Les deux réutilisent intégralement la chaîne existante (catégories de
 * places → réservation → paiement) : le code remis est la référence de la
 * réservation. `redeemed_quantity` trace les entrées déjà validées au
 * contrôle, pour qu'un même code ne serve pas deux fois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('screenings', function (Blueprint $table) {
            $table->string('kind', 8)->default('seance')->after('media_id')->index();
            $table->dateTime('valid_until')->nullable()->after('starts_at');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->unsignedInteger('redeemed_quantity')->default(0)->after('quantity');
            $table->timestamp('redeemed_at')->nullable()->after('confirmed_at');
            $table->foreignId('redeemed_by')->nullable()->after('redeemed_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('redeemed_by');
            $table->dropColumn(['redeemed_quantity', 'redeemed_at']);
        });

        Schema::table('screenings', function (Blueprint $table) {
            $table->dropIndex(['kind']);
            $table->dropColumn(['kind', 'valid_until']);
        });
    }
};
