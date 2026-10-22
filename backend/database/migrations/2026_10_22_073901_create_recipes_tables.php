<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('yield_servings', 10, 3);
            $table->boolean('is_archived')->default(false);
            $table->string('idempotency_key', 120)->nullable();
            $table->string('request_fingerprint', 64)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'name']);
        });
        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('food_id')->constrained('foods')->restrictOnDelete();
            $table->foreignUuid('food_portion_id')->nullable()->constrained('food_portions')->restrictOnDelete();
            $table->decimal('quantity', 10, 3);
            $table->string('unit', 12);
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
        });
        Schema::table('consumptions', function (Blueprint $table) {
            $table->string('source_type', 20)->default('foods');
            $table->uuid('source_id')->nullable();
            $table->string('source_name')->nullable();
            $table->decimal('source_quantity', 10, 3)->nullable();
            $table->string('source_unit', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('consumptions', function (Blueprint $table) {
            $table->dropColumn(['source_type', 'source_id', 'source_name', 'source_quantity', 'source_unit']);
        });
        Schema::dropIfExists('recipe_ingredients');
        Schema::dropIfExists('recipes');
    }
};
