<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('samples', function (Blueprint $table) {
            $table->id();
            $table->string('sample_number', 30)->unique();
            // Optional grouping; each sample still belongs to its own mill.
            $table->foreignId('sample_bundle_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('mill_id')->constrained()->restrictOnDelete();
            $table->foreignId('rice_variety_id')->constrained()->restrictOnDelete();
            $table->date('collection_date');
            $table->decimal('quantity', 10, 2)->nullable();
            $table->string('quantity_unit', 20)->nullable();
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['mill_id', 'collection_date']);
            $table->index('collection_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('samples');
    }
};
