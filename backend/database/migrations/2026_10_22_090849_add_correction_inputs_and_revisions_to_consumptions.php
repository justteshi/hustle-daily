<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumption_items', function (Blueprint $table) {
            $table->string('input_unit', 12)->nullable();
            $table->uuid('food_portion_id')->nullable();
        });
        Schema::create('consumption_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('consumption_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->text('reason');
            $table->jsonb('before_state');
            $table->jsonb('after_state');
            $table->timestamp('created_at');
            $table->unique(['consumption_id', 'revision_number']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumption_revisions');
        Schema::table('consumption_items', function (Blueprint $table) {
            $table->dropColumn(['input_unit', 'food_portion_id']);
        });
    }
};
