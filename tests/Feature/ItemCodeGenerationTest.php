<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemCodeGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_code_is_generated_from_category_prefix(): void
    {
        $category = ItemCategory::create(['name' => 'Kabel FO', 'code' => 'FE']);
        $unit = $this->unit();

        $first = Item::create(['name' => 'Kabel FO 1C', 'item_category_id' => $category->id, 'unit_id' => $unit->id]);
        $second = Item::create(['name' => 'Kabel FO 2C', 'item_category_id' => $category->id, 'unit_id' => $unit->id]);

        $this->assertSame('FE-0001', $first->code);
        $this->assertSame('FE-0002', $second->code);
    }

    public function test_item_code_uses_different_prefixes_and_generic_fallback(): void
    {
        $distribution = ItemCategory::create(['name' => 'Distribusi', 'code' => 'DST']);
        $feeder = ItemCategory::create(['name' => 'Feeder', 'code' => 'FDR']);
        $generic = ItemCategory::create(['name' => 'Tanpa Prefix']);
        $unit = $this->unit();

        $distributionItem = Item::create(['name' => 'Kabel Distribusi 12C', 'item_category_id' => $distribution->id, 'unit_id' => $unit->id]);
        $feederItem = Item::create(['name' => 'Kabel FO 24C', 'item_category_id' => $feeder->id, 'unit_id' => $unit->id]);
        $uncategorizedItem = Item::create(['name' => 'Barang Tanpa Jenis', 'item_category_id' => $generic->id, 'unit_id' => $unit->id]);

        $this->assertSame('DST-0001', $distributionItem->code);
        $this->assertSame('FDR-0001', $feederItem->code);
        $this->assertSame('BRG-0001', $uncategorizedItem->code);
    }

    public function test_manual_code_is_preserved_and_unrelated_codes_do_not_affect_sequence(): void
    {
        $category = ItemCategory::create(['name' => 'Distribusi', 'code' => 'DST']);
        $unit = $this->unit();

        $manual = Item::create(['name' => 'Demo Kabel', 'code' => 'DEMO-FO-001', 'item_category_id' => $category->id, 'unit_id' => $unit->id]);
        $generated = Item::create(['name' => 'Kabel Baru', 'item_category_id' => $category->id, 'unit_id' => $unit->id]);

        $this->assertSame('DEMO-FO-001', $manual->code);
        $this->assertSame('DST-0001', $generated->code);
    }

    public function test_backfill_command_only_generates_missing_codes(): void
    {
        $category = ItemCategory::create(['name' => 'Aksesoris', 'code' => 'AKS']);
        $unit = $this->unit();
        Item::withoutEvents(fn () => Item::create(['name' => 'Patchcord', 'item_category_id' => $category->id, 'unit_id' => $unit->id, 'code' => null]));
        $manual = Item::create(['name' => 'Fast Connector', 'item_category_id' => $category->id, 'unit_id' => $unit->id, 'code' => 'MANUAL-001']);

        $this->artisan('items:generate-missing-codes')
            ->expectsOutput('Generated codes for 1 item(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('items', ['name' => 'Patchcord', 'code' => 'AKS-0001']);
        $this->assertSame('MANUAL-001', $manual->refresh()->code);
    }

    private function unit(): Unit
    {
        return Unit::firstOrCreate(['symbol' => 'Pcs'], ['name' => 'Pcs']);
    }
}
