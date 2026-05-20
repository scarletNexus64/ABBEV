<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Ma liste" : une seule liste par utilisateur regroupant films ET séries
 * (les deux sont des `media`). Un toggle ajoute/retire une entrée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_list', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->timestamps();

            // Un même média ne peut être présent qu'une fois dans la liste.
            $table->unique(['user_id', 'media_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_list');
    }
};
