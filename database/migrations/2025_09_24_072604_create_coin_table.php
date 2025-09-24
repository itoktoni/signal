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
        Schema::create('coin', function (Blueprint $table) {
            $table->integer('coin_id')->primary()->autoIncrement();
            $table->string('coin_code')->unique();
            $table->enum('coin_watch', ['watch'])->nullable();
            $table->enum('coin_plan', ['long', 'short'])->nullable();
            $table->double('coin_price_usd')->nullable();
            $table->double('coin_price_idr')->nullable();
            $table->double('coin_entry_usd')->nullable();
            $table->double('coin_entry_idr')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coin');
    }
};
