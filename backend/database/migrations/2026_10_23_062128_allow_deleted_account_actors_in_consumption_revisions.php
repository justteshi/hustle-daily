<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consumption_revisions', function (Blueprint $table) {
            $table->dropForeign(['actor_user_id']);
            $table->foreignUuid('actor_user_id')->nullable()->change();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('consumption_revisions') && DB::table('consumption_revisions')->whereNull('actor_user_id')->exists()) {
            throw new RuntimeException('Cannot restore the actor constraint after an actor account has been deleted.');
        }

        Schema::table('consumption_revisions', function (Blueprint $table) {
            $table->dropForeign(['actor_user_id']);
            $table->foreignUuid('actor_user_id')->nullable(false)->change();
            $table->foreign('actor_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }
};
