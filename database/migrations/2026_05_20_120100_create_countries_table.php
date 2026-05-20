<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('code', 2)->unique();      // ISO 3166-1 alpha-2 (CI, SN, FR, US…)
            $table->string('name');                   // Nom français du pays
            $table->string('flag_emoji', 8)->nullable();
            $table->string('phone_code', 8)->nullable();
            $table->string('currency_code', 3);       // FK logique vers currencies.code
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('currency_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
