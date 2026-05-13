<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_in_stock_out_transfer_and_adjustment_affect_ledger(): void
    {
        $main = StockLocation::create(['name' => 'Gudang Utama', 'code' => 'MAIN']);
        $krian = StockLocation::create(['name' => 'Krian', 'code' => 'KRIAN', 'type' => 'branch']);
        $item = Item::create($this->itemAttributes(['name' => 'Pigtail', 'opening_balance' => 10, 'minimum_stock' => 2]));

        $this->movement(MovementType::StockIn, $item, 5, destination: $main);
        $this->movement(MovementType::StockOut, $item, 3, source: $main);
        $this->movement(MovementType::Transfer, $item, 4, source: $main, destination: $krian);
        $this->movement(MovementType::Adjustment, $item, 2, destination: $main);
        $this->movement(MovementType::Adjustment, $item, 1, source: $main);

        $item->refresh();

        $this->assertSame(13.0, $item->current_stock);
        $this->assertSame(9.0, $item->stockForLocation($main->id));
        $this->assertSame(14.0, $item->stockForLocation($krian->id));
    }

    public function test_negative_stock_is_allowed_and_flagged(): void
    {
        $main = StockLocation::create(['name' => 'Gudang Utama', 'code' => 'MAIN']);
        $item = Item::create($this->itemAttributes(['name' => 'Spliter ODP 1:8', 'opening_balance' => 1, 'minimum_stock' => 2]));

        $this->movement(MovementType::StockOut, $item, 5, source: $main);

        $item->refresh();

        $this->assertSame(-4.0, $item->current_stock);
        $this->assertSame('Negative', $item->stock_status);
    }

    private function movement(MovementType $type, Item $item, float $quantity, ?StockLocation $source = null, ?StockLocation $destination = null): StockMovement
    {
        $movement = StockMovement::create([
            'movement_date' => now()->toDateString(),
            'type' => $type,
            'source_location_id' => $source?->id,
            'destination_location_id' => $destination?->id,
        ]);

        $movement->lines()->create(['item_id' => $item->id, 'quantity' => $quantity]);

        return $movement;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function itemAttributes(array $attributes): array
    {
        $category = ItemCategory::firstOrCreate(['name' => 'Lainnya'], ['code' => 'LNY']);
        $unit = Unit::firstOrCreate(['symbol' => 'Pcs'], ['name' => 'Pcs']);

        return $attributes + [
            'item_category_id' => $category->id,
            'unit_id' => $unit->id,
        ];
    }
}
