<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute `producer_subscription` aux types de transaction (paiement du pack
 * producteur depuis le dashboard). Sans cela, la transaction échoue sur la
 * contrainte de la colonne `type`.
 *
 * Base en production : on LIT les valeurs actuellement autorisées et on se
 * contente d'y ajouter la nouvelle, sans en retirer aucune — aucune ligne
 * existante n'est touchée, quel que soit l'historique des migrations.
 */
return new class extends Migration
{
    private const VALUE = 'producer_subscription';

    public function up(): void
    {
        $this->rewrite(fn (array $values) => array_values(array_unique([...$values, self::VALUE])));
    }

    public function down(): void
    {
        // On ne supprime jamais un historique de paiement : retour arrière
        // impossible tant que des transactions producteur existent.
        if (DB::table('transactions')->where('type', self::VALUE)->exists()) {
            throw new RuntimeException('Des transactions « producer_subscription » existent : retour arrière refusé pour ne pas perdre d\'historique de paiement.');
        }

        $this->rewrite(fn (array $values) => array_values(array_diff($values, [self::VALUE])));
    }

    /** @param callable(array<int,string>):array<int,string> $change */
    private function rewrite(callable $change): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $column = DB::selectOne(
                "SELECT COLUMN_TYPE AS definition FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'transactions' AND COLUMN_NAME = 'type'"
            );
            preg_match_all("/'([^']*)'/", (string) $column->definition, $m);
            $values = $change($m[1]);

            $list = implode(',', array_map(fn ($v) => DB::getPdo()->quote($v), $values));
            DB::statement("ALTER TABLE transactions MODIFY type ENUM($list) NOT NULL DEFAULT 'subscription'");

            return;
        }

        if ($driver === 'pgsql') {
            $constraint = DB::selectOne(
                "SELECT pg_get_constraintdef(oid) AS definition FROM pg_constraint
                 WHERE conrelid = 'transactions'::regclass AND conname = 'transactions_type_check'"
            );

            // Pas de contrainte : la colonne accepte déjà n'importe quelle valeur.
            if (! $constraint) {
                return;
            }

            preg_match_all("/'([^']*)'::/", (string) $constraint->definition, $m);
            $values = $change($m[1]);

            $list = implode(',', array_map(fn ($v) => DB::getPdo()->quote($v), $values));
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT transactions_type_check');
            DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_type_check CHECK (type::text = ANY (ARRAY[$list]::text[]))");

            return;
        }

        // SQLite (tests) : Laravel reconstruit la table en conservant les données.
        $values = $change(['subscription', 'purchase', 'refund', 'withdrawal', self::VALUE]);
        Schema::table('transactions', function (Blueprint $table) use ($values) {
            $table->enum('type', array_values(array_unique($values)))->default('subscription')->change();
        });
    }
};
