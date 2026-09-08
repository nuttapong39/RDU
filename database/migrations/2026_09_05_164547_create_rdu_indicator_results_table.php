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
        Schema::create('rdu_indicator_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_id')->constrained('rdu_indicators')->cascadeOnDelete();
            $table->smallInteger('year');   // พ.ศ.
            $table->tinyInteger('month');   // 1-12
            $table->integer('numerator')->default(0);
            $table->integer('denominator')->default(0);
            $table->decimal('result', 5, 2)->nullable();  // % คำนวณแล้ว
            $table->string('status', 20)->nullable();     // pass | fail | warning
            $table->timestamp('calculated_at')->nullable();
            $table->foreignId('calculated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['indicator_id', 'year', 'month']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rdu_indicator_results');
    }
};
