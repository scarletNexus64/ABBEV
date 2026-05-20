<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute `kpay` aux moyens de paiement et `withdrawal` aux types de
 * transaction. Sans cela, toute transaction KPay (paiement ou retrait)
 * échoue sur la contrainte CHECK de la table `transactions`.
 *
 * SQLite ne supporte pas la modification d'une contrainte CHECK : on
 * recrée donc les colonnes enum (compatible SQLite & MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            // SQLite : recréation des colonnes via table temporaire.
            $this->rebuildSqlite([
                'payment_method' => ['cash', 'card', 'mobile', 'paypal', 'freemopay', 'fedapay', 'kpay'],
                'type'           => ['subscription', 'purchase', 'refund', 'withdrawal'],
            ]);

            return;
        }

        // MySQL / MariaDB : MODIFY COLUMN avec le nouvel enum.
        DB::statement("ALTER TABLE transactions MODIFY payment_method ENUM('cash','card','mobile','paypal','freemopay','fedapay','kpay') NOT NULL DEFAULT 'mobile'");
        DB::statement("ALTER TABLE transactions MODIFY type ENUM('subscription','purchase','refund','withdrawal') NOT NULL DEFAULT 'subscription'");
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildSqlite([
                'payment_method' => ['cash', 'card', 'mobile', 'paypal', 'freemopay', 'fedapay'],
                'type'           => ['subscription', 'purchase', 'refund'],
            ]);

            return;
        }

        DB::statement("ALTER TABLE transactions MODIFY payment_method ENUM('cash','card','mobile','paypal','freemopay','fedapay') NOT NULL DEFAULT 'mobile'");
        DB::statement("ALTER TABLE transactions MODIFY type ENUM('subscription','purchase','refund') NOT NULL DEFAULT 'subscription'");
    }

    /**
     * Recrée la table transactions sous SQLite avec de nouvelles
     * contraintes enum, en préservant les données.
     *
     * @param array<string,array<int,string>> $enums
     */
    private function rebuildSqlite(array $enums): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('transactions_tmp', function (Blueprint $table) use ($enums) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('transaction_id');
            $table->enum('payment_method', $enums['payment_method'])->default('mobile');
            $table->enum('type', $enums['type'])->default('subscription');
            $table->decimal('amount', 12, 2);
            $table->decimal('fees', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2);
            $table->string('currency')->default('XAF');
            $table->string('external_reference')->nullable();
            $table->text('description')->nullable();
            $table->text('metadata')->nullable();
            $table->string('payer_email')->nullable();
            $table->string('payer_name')->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
        });

        $columns = 'id, user_id, transaction_id, payment_method, type, amount, fees, net_amount, currency, external_reference, description, metadata, payer_email, payer_name, status, completed_at, created_at, updated_at';
        DB::statement("INSERT INTO transactions_tmp ($columns) SELECT $columns FROM transactions");

        Schema::drop('transactions');
        Schema::rename('transactions_tmp', 'transactions');

        Schema::enableForeignKeyConstraints();
    }
};
