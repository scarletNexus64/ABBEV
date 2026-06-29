<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bunny_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // admin auteur

            $table->string('original_filename');
            $table->string('title');                              // titre envoyé à Bunny
            $table->unsignedBigInteger('size_bytes')->default(0); // taille totale annoncée
            $table->unsignedBigInteger('bytes_received')->default(0); // réception nav→serveur
            $table->unsignedBigInteger('bytes_sent')->default(0);     // transfert serveur→Bunny

            $table->string('resumable_identifier')->nullable()->index(); // clé reprise client
            $table->string('temp_path')->nullable();              // fichier local assemblé

            $table->enum('status', [
                'uploading',    // réception des chunks (nav→serveur)
                'queued',       // fichier reçu, job en file
                'transferring', // PUT du binaire vers Bunny
                'processing',   // binaire envoyé, Bunny transcode (status Bunny 1..3)
                'ready',        // Bunny status 4
                'failed',
            ])->default('uploading')->index();

            $table->unsignedTinyInteger('progress')->default(0);   // 0..100 (phase active)
            $table->unsignedTinyInteger('bunny_status')->nullable(); // 0..4 reflété de Bunny
            $table->string('bunny_guid')->nullable()->index();     // guid Bunny
            $table->text('error')->nullable();

            $table->timestamp('uploaded_at')->nullable();     // fin réception serveur
            $table->timestamp('transferred_at')->nullable();  // fin PUT Bunny
            $table->timestamp('ready_at')->nullable();        // Bunny encodé
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bunny_uploads');
    }
};
