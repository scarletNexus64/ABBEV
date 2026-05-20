<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watch_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_id')->constrained('media')->cascadeOnDelete();
            $table->foreignId('episode_id')->nullable()->constrained('episodes')->cascadeOnDelete();
            // Secondes réellement regardées lors de cette session de visionnage.
            $table->unsignedInteger('watched_seconds')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'media_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watch_history');
    }
};
