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
        Schema::create('rdu_indicators', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('category', 50);
            $table->text('description')->nullable();
            $table->text('numerator_desc')->nullable();
            $table->text('denominator_desc')->nullable();
            $table->decimal('target_value', 5, 2)->nullable();
            $table->string('target_direction', 10)->default('lower'); // lower | higher
            $table->string('unit', 20)->default('%');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rdu_indicators');
    }
};
