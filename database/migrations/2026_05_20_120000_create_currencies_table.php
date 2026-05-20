<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 3)->unique(); // ISO 4217 (XOF, USD, EUR…)
            $table->string('name');               // West African CFA franc
            $table->string('symbol', 8)->nullable();
            // Combien d'unités de cette devise vaut 1 XOF.
            // Exemple : USD ≈ 0.00177 (1 XOF ≈ 0.00177 USD).
            // Les prix sont stockés en XOF côté backend et convertis à la volée.
            $table->decimal('rate_from_xof', 18, 8)->default(1);
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
