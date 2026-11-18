<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per truck. A confirmed order can be loaded across several trucks.
        Schema::create('loadings', function (Blueprint $table) {
            $table->id();
            $table->string('loading_number', 30)->unique();
            $table->foreignId('sample_dispatch_id')->constrained()->restrictOnDelete();
            $table->string('truck_number', 30);
            $table->date('loading_date');
            $table->decimal('quantity', 12, 2);
            $table->string('quantity_unit', 20);
            $table->unsignedInteger('bag_count')->nullable();
            $table->string('packing_type', 50)->nullable();
            $table->text('packing_details')->nullable();
            $table->string('receiver_name', 100)->nullable();
            $table->string('driver_name', 100)->nullable();
            $table->string('driver_phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('loading_date');
            $table->index('truck_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loadings');
    }
};
