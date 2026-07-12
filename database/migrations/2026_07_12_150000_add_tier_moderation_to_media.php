<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tier de rémunération (classique/standard/premium), workflow de modération
 * (assistant/direction artistique) et compteur de vues « producteur » sur les
 * contenus.
 *
 * - `tier` : classe le contenu pour la rémunération (assigné par l'assistant).
 * - `moderation_status` : pending → approved/rejected. Défaut `approved` pour
 *   que le contenu HISTORIQUE reste visible ; les nouveaux uploads producteur
 *   passeront en `pending` (posé côté contrôleur).
 * - `producer_views` : compteur DÉDIÉ, incrémenté quand un abonné paie un
 *   forfait du même tier (≠ views_count, qui compte les ouvertures de fiche).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->enum('tier', ['classique', 'standard', 'premium'])
                ->default('classique')->after('category_id');
            $table->enum('moderation_status', ['pending', 'approved', 'rejected'])
                ->default('approved')->after('tier');
            $table->foreignId('reviewed_by')->nullable()->after('moderation_status')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
            $table->text('rejection_reason')->nullable()->after('reviewed_at');
            $table->unsignedBigInteger('producer_views')->default(0)->after('views_count');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'tier', 'moderation_status', 'reviewed_at',
                'rejection_reason', 'producer_views',
            ]);
        });
    }
};
