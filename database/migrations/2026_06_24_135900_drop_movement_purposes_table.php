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
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['movement_purpose_id']);
            $table->dropColumn('movement_purpose_id');
        });

        Schema::dropIfExists('movement_purposes');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('movement_purposes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->timestamps();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreignId('movement_purpose_id')->nullable()->constrained('movement_purposes')->nullOnDelete();
        });
    }
};
