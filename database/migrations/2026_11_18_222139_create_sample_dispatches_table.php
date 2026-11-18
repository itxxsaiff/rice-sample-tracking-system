<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per (sample → buyer) dispatch. A sample sent to three buyers
        // has three rows, each with its own response and loading history.
        Schema::create('sample_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('dispatch_number', 30)->unique();
            $table->foreignId('sample_id')->constrained()->restrictOnDelete();
            $table->foreignId('buyer_id')->constrained()->restrictOnDelete();
            $table->date('dispatch_date');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending');

            // Confirmation details (status = confirmed)
            $table->decimal('confirmed_quantity', 12, 2)->nullable();
            $table->string('confirmed_quantity_unit', 20)->nullable();
            $table->decimal('rate', 12, 2)->nullable();
            $table->string('rate_unit', 20)->nullable();
            $table->string('packing_type', 50)->nullable();
            $table->text('packing_details')->nullable();
            $table->date('confirmation_date')->nullable();

            // Rejection details (status = rejected)
            $table->text('rejection_reason')->nullable();
            $table->date('rejection_date')->nullable();

            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();

            // Loading workflow (only set once confirmed)
            $table->string('loading_status', 20)->nullable();
            $table->timestamp('loading_completed_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'dispatch_date']);
            $table->index(['buyer_id', 'status']);
            $table->index(['status', 'loading_status']);
            $table->index('dispatch_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sample_dispatches');
    }
};
