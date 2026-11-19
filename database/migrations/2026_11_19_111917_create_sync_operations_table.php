<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log of offline operations already applied. Written in the same
     * transaction as the change itself, so a retried request (e.g. the
     * response was lost on a weak connection) returns the stored result
     * instead of applying the change twice.
     */
    public function up(): void
    {
        Schema::create('sync_operations', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->json('result')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_operations');
    }
};
