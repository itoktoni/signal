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
        Schema::create('trading_plans', function (Blueprint $table) {
            $table->id();
            $table->string('symbol');
            $table->enum('position_type', ['long', 'short']);
            $table->decimal('entry_price', 15, 8);
            $table->decimal('stop_loss', 15, 8);
            $table->decimal('take_profit', 15, 8);
            $table->decimal('risk_reward_ratio', 5, 2);
            $table->decimal('success_rate', 5, 2);
            $table->decimal('position_size', 15, 8);
            $table->decimal('costs', 10, 4)->default(0);
            $table->decimal('net_rr', 5, 2)->default(0);
            $table->string('timeframe', 10);
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->json('analysis_data')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['symbol', 'position_type']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trading_plans');
    }
};
