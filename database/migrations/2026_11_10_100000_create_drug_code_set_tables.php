<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per drug code set (key from DrugCodeSetRegistry). `version` goes up on every save made on the
        // settings page; `changed_at` is set only by such a save, never by the one-time import, so results
        // calculated before the import stay valid (docs/plans/drug-code-sets-and-facility-profile.md).
        Schema::create('drug_code_sets', function (Blueprint $table) {
            $table->string('key', 50)->primary();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('changed_at')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('drug_code_set_items', function (Blueprint $table) {
            $table->id();
            $table->string('set_key', 50);
            $table->string('code', 20);                    // HOSxP icode, or lab_items_code for the lab set
            $table->string('attribute', 150)->nullable();  // ingredient (RAS) or dosage form (KPI11/13)
            $table->timestamps();

            $table->unique(['set_key', 'code']);
            $table->foreign('set_key')->references('key')->on('drug_code_sets')->cascadeOnDelete();
        });

        // Users an admin allows to edit drug code sets (admins always can, without a row here).
        Schema::create('drug_code_set_editors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drug_code_set_editors');
        Schema::dropIfExists('drug_code_set_items');
        Schema::dropIfExists('drug_code_sets');
    }
};
