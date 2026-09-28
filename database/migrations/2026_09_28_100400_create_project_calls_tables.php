<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Appels à projets (cat.md) — trois familles d'appel sur un même modèle :
 *
 *  - `financement` (film, série & feuilleton, documentaire) : un porteur de
 *    projet cherche des soutiens. On y collecte des PROMESSES de soutien
 *    (`project_pledges`) que l'équipe ABBEV confirme à réception des fonds —
 *    aucun paiement dans l'app (le financement participatif est réglementé
 *    et les stores encadrent strictement les collectes de fonds) ;
 *  - `ecriture` (film, série & feuilleton, documentaire) : appel à scénarios ;
 *  - `musique`  (cinéma, télévision) : appel à compositions.
 *
 * Écriture et musique reçoivent des CANDIDATURES (`project_submissions`) :
 * pitch, synopsis, note d'intention, lien d'écoute ou de lecture, PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_calls', function (Blueprint $table) {
            $table->id();
            // financement | ecriture | musique
            $table->string('type', 16);
            // film | serie | documentaire (financement, écriture) ; cinema | television (musique)
            $table->string('target', 16);
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('summary', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('cover_path')->nullable();
            // Porteur du projet (financement) ou organisateur (écriture, musique).
            $table->string('organizer')->nullable();
            $table->dateTime('opens_at')->nullable();
            $table->dateTime('closes_at')->nullable();
            // draft | open | closed | completed
            $table->string('status', 12)->default('draft');

            // --- Financement --------------------------------------------------
            $table->decimal('goal_amount', 15, 2)->nullable();
            $table->string('currency', 3)->default('XAF');
            $table->decimal('min_pledge', 15, 2)->nullable();
            // Montant déjà réuni hors de l'app (déclaré par l'admin).
            $table->decimal('raised_offline', 15, 2)->default(0);
            // Contreparties : [{amount, title, description}]
            $table->json('rewards')->nullable();

            // --- Écriture & musique ------------------------------------------
            // Pièces demandées / cahier des charges.
            $table->text('requirements')->nullable();
            // Dotation, rémunération ou prix promis au lauréat.
            $table->string('prize')->nullable();
            $table->string('genre')->nullable();
            $table->unsignedSmallInteger('max_pages')->nullable();
            $table->string('music_style')->nullable();
            $table->unsignedSmallInteger('max_duration_minutes')->nullable();

            $table->string('rules_url')->nullable();
            $table->string('contact_email')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status', 'closes_at']);
        });

        Schema::create('project_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_call_id')->constrained('project_calls')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            // Pitch en une ou deux phrases (logline).
            $table->string('logline', 500)->nullable();
            $table->text('synopsis')->nullable();
            // Note d'intention / présentation de l'auteur ou du compositeur.
            $table->text('message')->nullable();
            $table->string('link_url')->nullable();
            $table->string('file_path')->nullable();
            $table->string('phone', 32)->nullable();
            // received | shortlisted | selected | rejected
            $table->string('status', 12)->default('received');
            $table->text('admin_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['project_call_id', 'user_id']);
            $table->index(['project_call_id', 'status']);
        });

        Schema::create('project_pledges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_call_id')->constrained('project_calls')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('XAF');
            // Intitulé de la contrepartie choisie, figé au moment de la promesse.
            $table->string('reward_title')->nullable();
            $table->text('message')->nullable();
            $table->string('phone', 32)->nullable();
            $table->boolean('is_anonymous')->default(false);
            // pending | confirmed | cancelled
            $table->string('status', 12)->default('pending');
            $table->timestamp('confirmed_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();

            $table->index(['project_call_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_pledges');
        Schema::dropIfExists('project_submissions');
        Schema::dropIfExists('project_calls');
    }
};
