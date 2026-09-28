<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Talents & casting (cat.md, bloc « Acteur / actrice / technicien ») :
 *
 *  - `agents`   : agents d'acteurs et de techniciens ;
 *  - `talents`  : annuaire des acteurs, actrices et techniciens, classés par
 *                 rang (A² icône, A¹ star, B vedette, C confirmé, D espoir),
 *                 avec leur biographie ;
 *  - `talent_credits` : filmographie (liée au catalogue quand l'œuvre y est) ;
 *  - `casting_calls` / `casting_roles` : annonces de casting et rôles à
 *                 pourvoir — le « breakdown » des directeurs de casting ;
 *  - `casting_applications` : candidatures envoyées depuis l'app.
 *
 * Les listes de valeurs (rang, métier, type de projet…) sont des chaînes
 * validées par l'application plutôt que des ENUM SQL : les faire évoluer ne
 * demandera pas de migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('agency')->nullable();
            // acteurs | techniciens | mixte
            $table->string('represents', 16)->default('acteurs');
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('website_url')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('city')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('bio')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['is_published', 'represents']);
        });

        Schema::create('talents', function (Blueprint $table) {
            $table->id();
            // acteur | technicien
            $table->string('kind', 16)->default('acteur');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('stage_name')->nullable();
            $table->string('slug')->unique();
            // A2 | A1 | B | C | D — cf. Talent::TIERS
            $table->string('tier', 2)->default('C');
            // Métier : « acteur » pour les comédiens, poste technique sinon.
            $table->string('profession', 40)->default('acteur');
            // femme | homme (facultatif : l'annuaire ne l'exige pas)
            $table->string('gender', 8)->nullable();
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('city')->nullable();
            $table->string('headline')->nullable();
            $table->text('bio')->nullable();
            $table->string('photo_path')->nullable();
            // Comédiens : tranche d'âge jouable et taille, usuels au casting.
            $table->unsignedTinyInteger('playing_age_min')->nullable();
            $table->unsignedTinyInteger('playing_age_max')->nullable();
            $table->unsignedSmallInteger('height_cm')->nullable();
            $table->json('languages')->nullable();
            $table->json('skills')->nullable();
            $table->string('showreel_url')->nullable();
            $table->foreignId('agent_id')->nullable()->constrained('agents')->nullOnDelete();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index(['is_published', 'kind', 'tier']);
        });

        Schema::create('talent_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('talent_id')->constrained('talents')->cascadeOnDelete();
            // Œuvre du catalogue ABBEV quand elle y figure (lien « Regarder »).
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('year')->nullable();
            // Personnage joué, ou poste occupé pour un technicien.
            $table->string('role')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('casting_calls', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('project_title');
            // film | serie | documentaire | court-metrage | publicite | clip | theatre | autre
            $table->string('project_type', 20)->default('film');
            $table->string('production_company')->nullable();
            $table->string('director')->nullable();
            $table->text('description')->nullable();
            $table->string('city')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->date('shooting_starts_on')->nullable();
            $table->date('shooting_ends_on')->nullable();
            $table->dateTime('deadline_at')->nullable();
            // remunere | non-remunere | a-negocier
            $table->string('compensation', 16)->default('remunere');
            $table->string('compensation_details')->nullable();
            $table->string('cover_path')->nullable();
            $table->string('contact_email')->nullable();
            // draft | open | closed
            $table->string('status', 12)->default('draft');
            $table->boolean('is_featured')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'deadline_at']);
        });

        Schema::create('casting_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('casting_call_id')->constrained('casting_calls')->cascadeOnDelete();
            $table->string('name');
            // acteur | technicien
            $table->string('kind', 16)->default('acteur');
            $table->string('profession', 40)->nullable();
            // principal | secondaire | figuration (comédiens)
            $table->string('importance', 16)->nullable();
            // femme | homme | indifferent
            $table->string('gender', 12)->default('indifferent');
            $table->unsignedTinyInteger('age_min')->nullable();
            $table->unsignedTinyInteger('age_max')->nullable();
            // Rang minimum attendu (A2…D), facultatif.
            $table->string('min_tier', 2)->nullable();
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->unsignedSmallInteger('positions')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('casting_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('casting_call_id')->constrained('casting_calls')->cascadeOnDelete();
            $table->foreignId('casting_role_id')->constrained('casting_roles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 32)->nullable();
            $table->unsignedTinyInteger('age')->nullable();
            $table->string('city')->nullable();
            $table->text('message')->nullable();
            $table->string('portfolio_url')->nullable();
            $table->string('photo_path')->nullable();
            // pending | shortlisted | accepted | rejected
            $table->string('status', 12)->default('pending');
            $table->text('admin_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // Une candidature par rôle et par compte.
            $table->unique(['casting_role_id', 'user_id']);
            $table->index(['casting_call_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('casting_applications');
        Schema::dropIfExists('casting_roles');
        Schema::dropIfExists('casting_calls');
        Schema::dropIfExists('talent_credits');
        Schema::dropIfExists('talents');
        Schema::dropIfExists('agents');
    }
};
