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
        Schema::create('rdu_doctor_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('doctor_code', 20)->unique(); // HOSxP doctor.code — the stable join key
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rdu_doctor_aliases');
    }
};
