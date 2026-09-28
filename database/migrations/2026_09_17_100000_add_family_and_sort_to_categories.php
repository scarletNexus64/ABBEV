<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le catalogue mélange désormais huit familles (genres, cours, appels à
     * financement / écriture / musique, casting, awards, billetterie…). Sans
     * marqueur explicite, l'app ne peut les regrouper qu'en devinant le
     * préfixe du slug — fragile dès qu'une entrée est ajoutée depuis l'admin.
     *
     * `sort_order` complète le tri alphabétique là où l'ordre métier prime
     * (durées de film : court < moyen < long, et non l'inverse de l'alphabet).
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('family', 32)->default('genre')->index()->after('slug');
            $table->unsignedInteger('sort_order')->default(0)->after('family');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['family', 'sort_order']);
        });
    }
};
