<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Counters for human-readable reference numbers (SMP-0001, DSP-0001, …).
        Schema::create('reference_sequences', function (Blueprint $table) {
            $table->string('key', 30)->primary();
            $table->unsignedBigInteger('value')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_sequences');
    }
};
