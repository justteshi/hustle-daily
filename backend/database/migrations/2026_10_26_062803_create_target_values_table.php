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
        Schema::create('target_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('target_profile_id')->constrained()->cascadeOnDelete();
            $table->string('metric_code', 40);
            $table->decimal('target_value', 12, 3);
            $table->string('unit', 20);
            $table->string('comparison_rule', 20);
            $table->timestamps();
            $table->unique(['target_profile_id', 'metric_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('target_values');
    }
};
