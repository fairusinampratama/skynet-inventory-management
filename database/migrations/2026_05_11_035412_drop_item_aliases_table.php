<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('item_aliases');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Alias Barang has been removed from the app.
    }
};
