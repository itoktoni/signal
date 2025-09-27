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
        Schema::create('menu', function (Blueprint $table) {
            $table->string('menu_code')->primary();
            $table->string('menu_group')->nullable()->index('menu_ibfk_1');
            $table->string('menu_name')->nullable();
            $table->string('menu_controller')->nullable();
            $table->string('menu_action')->nullable();
            $table->integer('menu_sort')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu');
    }
};
