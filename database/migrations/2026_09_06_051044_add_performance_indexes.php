<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rdu_indicator_results', function (Blueprint $table) {
            // Speeds up "load all results for a given period" (getOrCalculateMany batch query)
            $table->index(['year', 'month'], 'rir_year_month_idx');
        });

        Schema::table('rdu_indicators', function (Blueprint $table) {
            // Speeds up active()->ordered() scope used on every page load
            $table->index(['is_active', 'sort_order'], 'ri_active_order_idx');
        });
    }

    public function down(): void
    {
        Schema::table('rdu_indicator_results', function (Blueprint $table) {
            $table->dropIndex('rir_year_month_idx');
        });

        Schema::table('rdu_indicators', function (Blueprint $table) {
            $table->dropIndex('ri_active_order_idx');
        });
    }
};
