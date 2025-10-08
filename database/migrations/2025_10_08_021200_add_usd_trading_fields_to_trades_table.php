<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            // Add USDT amount field for dollar-based trading
            $table->decimal('usd_amount', 15, 8)->nullable()->after('amount');

            // Add amount mode field to track base currency vs USDT trading
            $table->enum('amount_mode', ['base', 'usd'])->default('base')->after('usd_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->dropColumn(['usd_amount', 'amount_mode']);
        });
    }
};