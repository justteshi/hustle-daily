<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_mutation_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key', 120);
            $table->string('request_fingerprint', 64);
            $table->string('resource_type', 20);
            $table->uuid('resource_id');
            $table->jsonb('response_data');
            $table->timestamp('created_at');
            $table->unique(['user_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_mutation_keys');
    }
};
