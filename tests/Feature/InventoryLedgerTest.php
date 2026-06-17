<?php

namespace Tests\Feature;

use App\Enums\MovementType;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockAdjustmentReason;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Services\StockAdjustmentService;
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
        $this->assertSame(-1.0, $item->stockForLocation($main->id));
        $this->assertSame(4.0, $item->stockForLocation($krian->id));
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

    public function test_adjusting_to_actual_stock_creates_positive_adjustment(): void
    {
        $main = StockLocation::create(['name' => 'Gudang Utama', 'code' => 'MAIN']);
        $item = Item::create($this->itemAttributes(['name' => 'Patchcord', 'opening_balance' => 0, 'minimum_stock' => 2]));
        $reason = StockAdjustmentReason::create(['name' => 'Opname Stok', 'is_active' => true]);

        $this->movement(MovementType::StockIn, $item, 10, destination: $main);

        $movement = app(StockAdjustmentService::class)->adjustToActualStock(
            item: $item,
            locationId: $main->id,
            actualStock: 14,
            reasonId: $reason->id,
            pic: 'Rina Gudang',
            notes: 'Hasil opname',
        );

        $item->refresh();

        $this->assertNotNull($movement);
        $this->assertSame(MovementType::Adjustment, $movement->type);
        $this->assertNull($movement->source_location_id);
        $this->assertSame($main->id, $movement->destination_location_id);
        $this->assertSame($reason->id, $movement->stock_adjustment_reason_id);
        $this->assertSame('Rina Gudang', $movement->pic);
        $this->assertSame('Hasil opname', $movement->notes);
        $this->assertSame(4.0, (float) $movement->lines->first()->quantity);
        $this->assertSame(14.0, $item->stockForLocation($main->id));
        $this->assertSame(14.0, $item->current_stock);
    }

    public function test_adjusting_to_actual_stock_creates_negative_adjustment(): void
    {
        $main = StockLocation::create(['name' => 'Gudang Utama', 'code' => 'MAIN']);
        $item = Item::create($this->itemAttributes(['name' => 'Adaptor', 'opening_balance' => 0, 'minimum_stock' => 2]));
        $reason = StockAdjustmentReason::create(['name' => 'Koreksi', 'is_active' => true]);

        $this->movement(MovementType::StockIn, $item, 10, destination: $main);

        $movement = app(StockAdjustmentService::class)->adjustToActualStock(
            item: $item,
            locationId: $main->id,
            actualStock: 7,
            reasonId: $reason->id,
        );

        $item->refresh();

        $this->assertNotNull($movement);
        $this->assertSame(MovementType::Adjustment, $movement->type);
        $this->assertSame($main->id, $movement->source_location_id);
        $this->assertNull($movement->destination_location_id);
        $this->assertSame(3.0, (float) $movement->lines->first()->quantity);
        $this->assertSame(7.0, $item->stockForLocation($main->id));
        $this->assertSame(7.0, $item->current_stock);
    }

    public function test_adjusting_to_same_actual_stock_does_not_create_movement(): void
    {
        $main = StockLocation::create(['name' => 'Gudang Utama', 'code' => 'MAIN']);
        $item = Item::create($this->itemAttributes(['name' => 'ONT', 'opening_balance' => 0, 'minimum_stock' => 2]));
        $reason = StockAdjustmentReason::create(['name' => 'Opname Stok', 'is_active' => true]);

        $this->movement(MovementType::StockIn, $item, 10, destination: $main);

        $movement = app(StockAdjustmentService::class)->adjustToActualStock(
            item: $item,
            locationId: $main->id,
            actualStock: 10,
            reasonId: $reason->id,
        );

        $this->assertNull($movement);
        $this->assertSame(1, StockMovement::count());
        $this->assertSame(10.0, $item->stockForLocation($main->id));
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
