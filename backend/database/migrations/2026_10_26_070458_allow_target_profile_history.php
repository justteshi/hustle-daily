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
        Schema::table('target_profiles', function (Blueprint $table) {
            $table->dropUnique(['day_type_id']);
            $table->index(['day_type_id', 'effective_from']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('target_profiles', function (Blueprint $table) {
            $table->dropIndex(['day_type_id', 'effective_from']);
            $table->unique('day_type_id');
        });
    }
};
