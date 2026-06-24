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
        // 1. Create a "Stok Awal" purpose if it doesn't exist

        // 2. Get the first stock location to dump the stock into
        $location = \App\Models\StockLocation::first();

        if ($location) {
            // 3. Find all items with opening_balance > 0
            $items = \App\Models\Item::where('opening_balance', '>', 0)->get();

            foreach ($items as $item) {
                // Create a StockMovement
                $movement = \App\Models\StockMovement::create([
                    'movement_number' => \App\Models\StockMovement::nextMovementNumber(),
                    'movement_date' => now(),
                    'type' => \App\Enums\MovementType::StockIn->value,
                    'destination_location_id' => $location->id,
                    'notes' => 'Migrasi otomatis stok awal.',
                ]);

                // Create the StockMovementLine
                \App\Models\StockMovementLine::create([
                    'stock_movement_id' => $movement->id,
                    'item_id' => $item->id,
                    'quantity' => $item->opening_balance,
                ]);
            }
        }

        // 4. Drop the opening_balance column
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('opening_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('opening_balance', 15, 3)->default(0);
        });
    }
};
