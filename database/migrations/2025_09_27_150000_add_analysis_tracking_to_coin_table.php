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
        Schema::table('coin', function (Blueprint $table) {
            $table->timestamp('last_analyzed_at')->nullable()->after('coin_price_idr');
            $table->integer('analysis_count')->default(0)->after('last_analyzed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coin', function (Blueprint $table) {
            $table->dropColumn(['last_analyzed_at', 'analysis_count']);
        });
    }
};