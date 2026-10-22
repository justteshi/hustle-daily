<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meal_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('name', 100);
            $table->unsignedSmallInteger('sort_order');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'key']);
        });
        Schema::create('foods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('brand');
            $table->string('barcode')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->string('idempotency_key', 120)->nullable();
            $table->string('request_fingerprint', 64)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'name']);
            $table->unique(['user_id', 'idempotency_key']);
        });
        Schema::create('food_nutrition', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('food_id')->unique()->constrained('foods')->cascadeOnDelete();
            $table->decimal('basis_amount', 10, 3);
            $table->string('basis_unit', 12);
            foreach (['calories', 'protein', 'carbohydrates', 'fat'] as $nutrient) {
                $table->decimal($nutrient, 12, 3);
            }
            foreach (['fibre', 'sugar', 'salt'] as $nutrient) {
                $table->decimal($nutrient, 12, 3)->nullable();
            }
            $table->timestamps();
        });
        Schema::create('food_portions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('food_id')->constrained('foods')->cascadeOnDelete();
            $table->string('label', 100);
            $table->decimal('base_amount', 10, 3);
            $table->string('base_unit', 12);
            $table->timestamps();
        });
        Schema::create('consumptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('meal_category_id')->constrained()->restrictOnDelete();
            $table->date('local_date');
            $table->timestamp('eaten_at');
            $table->text('notes')->nullable();
            $table->string('idempotency_key', 120);
            $table->string('request_fingerprint', 64);
            foreach (['calories', 'protein', 'carbohydrates', 'fat'] as $nutrient) {
                $table->decimal($nutrient, 12, 3);
            }
            foreach (['fibre', 'sugar', 'salt'] as $nutrient) {
                $table->decimal($nutrient, 12, 3)->nullable();
            }
            $table->timestamps();
            $table->unique(['user_id', 'idempotency_key']);
            $table->index(['user_id', 'local_date', 'eaten_at']);
        });
        Schema::create('consumption_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('consumption_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('food_id')->nullable()->constrained('foods')->nullOnDelete();
            $table->string('food_name');
            $table->string('brand');
            $table->decimal('quantity', 10, 3);
            $table->string('unit', 100);
            $table->jsonb('nutrition_snapshot');
            foreach (['calories', 'protein', 'carbohydrates', 'fat'] as $nutrient) {
                $table->decimal($nutrient, 12, 3);
            }
            foreach (['fibre', 'sugar', 'salt'] as $nutrient) {
                $table->decimal($nutrient, 12, 3)->nullable();
            }
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumption_items');
        Schema::dropIfExists('consumptions');
        Schema::dropIfExists('food_portions');
        Schema::dropIfExists('food_nutrition');
        Schema::dropIfExists('foods');
        Schema::dropIfExists('meal_categories');
    }
};
