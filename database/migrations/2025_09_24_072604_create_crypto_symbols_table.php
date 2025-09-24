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
        Schema::create('crypto_symbols', function (Blueprint $table) {
            $table->id();
            $table->string('symbol')->unique(); // e.g., 'BTCUSDT'
            $table->string('base_asset'); // e.g., 'BTC'
            $table->string('quote_asset'); // e.g., 'USDT'
            $table->string('status')->default('active'); // TRADING, BREAK, etc.
            $table->boolean('is_spot_trading_allowed')->default(true);
            $table->boolean('is_margin_trading_allowed')->default(false);
            $table->decimal('min_price', 20, 10)->nullable();
            $table->decimal('max_price', 20, 10)->nullable();
            $table->decimal('tick_size', 20, 10)->nullable();
            $table->decimal('min_qty', 20, 10)->nullable();
            $table->decimal('max_qty', 20, 10)->nullable();
            $table->decimal('step_size', 20, 10)->nullable();
            $table->json('filters')->nullable(); // Store additional filters as JSON
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crypto_symbols');
    }
};
