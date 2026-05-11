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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->string('movement_number')->unique();
            $table->date('movement_date')->index();
            $table->string('type')->index();
            $table->foreignId('source_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->foreignId('destination_location_id')->nullable()->constrained('stock_locations')->nullOnDelete();
            $table->foreignId('movement_purpose_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stock_adjustment_reason_id')->nullable()->constrained()->nullOnDelete();
            $table->string('pic')->nullable()->index();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
