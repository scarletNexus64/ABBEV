<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('country_code', 2)->nullable()->after('email');
            $table->string('currency_code', 3)->nullable()->after('country_code');

            $table->index('country_code');
            $table->index('currency_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['country_code']);
            $table->dropIndex(['currency_code']);
            $table->dropColumn(['country_code', 'currency_code']);
        });
    }
};
