<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Rattrape les comptes créés avant l'ajout des champs pays/devise :
     * on leur attribue le Cameroun (CM) et le FCFA BEAC (XAF) par défaut.
     * L'utilisateur pourra changer ensuite depuis son profil.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('country_code')
            ->update([
                'country_code' => 'CM',
                'currency_code' => 'XAF',
                'updated_at' => now(),
            ]);

        // Sécurité : si certains comptes ont un country_code mais pas de
        // currency_code (cas improbable mais cohérent avec le schéma).
        DB::table('users')
            ->whereNotNull('country_code')
            ->whereNull('currency_code')
            ->update([
                'currency_code' => 'XAF',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Pas de rollback : on ne sait pas distinguer les valeurs backfillées
        // des valeurs définies manuellement par l'utilisateur après coup.
    }
};
