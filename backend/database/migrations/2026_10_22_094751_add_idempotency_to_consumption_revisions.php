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
        Schema::table('consumption_revisions', function (Blueprint $table) {
            $table->string('idempotency_key', 120)->nullable();
            $table->string('request_fingerprint', 64)->nullable();
            $table->unique(['consumption_id', 'idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('consumption_revisions', function (Blueprint $table) {
            $table->dropUnique(['consumption_id', 'idempotency_key']);
            $table->dropColumn(['idempotency_key', 'request_fingerprint']);
        });
    }
};
