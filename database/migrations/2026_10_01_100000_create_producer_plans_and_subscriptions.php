<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Abonnement des producteurs à leur espace du panel.
 *
 *  - `producer_plans` : LE pack producteur (une seule ligne, configurée par
 *    l'admin) — prix et période de facturation (mois ou année).
 *  - `producer_subscriptions` : une ligne par période payée (ou offerte par
 *    l'admin). Les périodes s'enchaînent : un renouvellement démarre à la fin
 *    de la précédente.
 *
 * Migration purement additive : aucune donnée existante n'est modifiée. Tant
 * qu'aucun pack actif n'existe (ProducerPlanSeeder), rien n'est verrouillé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('producer_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 15, 2);
            // 'month' | 'year' (voir ProducerPlan::PERIODS)
            $table->string('billing_period', 10)->default('month');
            $table->unsignedSmallInteger('period_count')->default(1);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('producer_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('producer_plan_id')->nullable()->constrained('producer_plans')->nullOnDelete();
            // Unique : une transaction ne provisionne qu'une seule période,
            // même si webhook, polling et confirmation arrivent en même temps.
            $table->foreignId('transaction_id')->nullable()->unique()->constrained('transactions')->nullOnDelete();
            // 'payment' (payé par le producteur) | 'admin' (offert par l'admin)
            $table->string('source', 20)->default('payment');
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            // 'active' | 'cancelled'
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['producer_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('producer_subscriptions');
        Schema::dropIfExists('producer_plans');
    }
};
