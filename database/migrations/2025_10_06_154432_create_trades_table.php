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
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->string('trade_id')->unique(); // Unique trade identifier
            $table->string('symbol'); // Trading pair symbol (e.g., BTC/USDT)
            $table->enum('side', ['buy', 'sell']); // Trade side
            $table->enum('type', ['market', 'limit', 'stop', 'stop_limit'])->default('market'); // Order type
            $table->decimal('amount', 20, 8); // Trade amount/quantity
            $table->decimal('price', 20, 8)->nullable(); // Trade price (null for market orders)
            $table->decimal('cost', 20, 8)->default(0); // Total cost of the trade
            $table->decimal('fee', 20, 8)->default(0); // Trading fee
            $table->string('fee_currency')->nullable(); // Fee currency
            $table->string('exchange')->default('tokocrypto'); // Exchange name
            $table->string('exchange_order_id')->nullable(); // CCXT order ID from exchange
            $table->string('status')->default('pending'); // Trade status: pending, open, filled, cancelled, rejected
            $table->json('exchange_response')->nullable(); // Raw response from exchange
            $table->string('trading_plan_id')->nullable(); // Reference to trading plan if applicable
            $table->string('analysis_method')->nullable(); // Analysis method used for this trade
            $table->json('analysis_result')->nullable(); // Analysis result that led to this trade
            $table->decimal('entry_price', 20, 8)->nullable(); // Entry price from analysis
            $table->decimal('stop_loss', 20, 8)->nullable(); // Stop loss price
            $table->decimal('take_profit', 20, 8)->nullable(); // Take profit price
            $table->decimal('risk_reward_ratio', 5, 2)->nullable(); // Risk reward ratio
            $table->decimal('confidence', 5, 2)->nullable(); // Confidence level from analysis
            $table->text('notes')->nullable(); // Additional notes about the trade
            $table->timestamp('executed_at')->nullable(); // When the trade was actually executed
            $table->timestamp('closed_at')->nullable(); // When the position was closed
            $table->timestamps();

            // Indexes for better performance
            $table->index(['symbol', 'status']);
            $table->index(['exchange', 'exchange_order_id']);
            $table->index(['trading_plan_id']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
