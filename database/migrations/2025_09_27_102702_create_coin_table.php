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
            $table->string('coin_code')->primary();
            $table->string('coin_symbol')->nullable();
            $table->string('coin_name')->nullable();
            $table->boolean('coin_watch')->default(false);
            $table->double('coin_price_usd')->nullable();
            $table->double('coin_price_idr')->nullable();
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
