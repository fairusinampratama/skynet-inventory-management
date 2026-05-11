<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemCodeGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_code_is_generated_from_category_prefix(): void
    {
        $category = ItemCategory::create(['name' => 'Distribusi']);

        $first = Item::create(['name' => 'Kabel FO 1C', 'item_category_id' => $category->id]);
        $second = Item::create(['name' => 'Kabel FO 2C', 'item_category_id' => $category->id]);

        $this->assertSame('DST-0001', $first->code);
        $this->assertSame('DST-0002', $second->code);
    }

    public function test_item_code_uses_different_prefixes_and_generic_fallback(): void
    {
        $feeder = ItemCategory::create(['name' => 'Feeder']);

        $feederItem = Item::create(['name' => 'Kabel FO 24C', 'item_category_id' => $feeder->id]);
        $uncategorizedItem = Item::create(['name' => 'Barang Tanpa Jenis']);

        $this->assertSame('FDR-0001', $feederItem->code);
        $this->assertSame('BRG-0001', $uncategorizedItem->code);
    }

    public function test_manual_code_is_preserved_and_unrelated_codes_do_not_affect_sequence(): void
    {
        $category = ItemCategory::create(['name' => 'Distribusi']);

        $manual = Item::create(['name' => 'Demo Kabel', 'code' => 'DEMO-FO-001', 'item_category_id' => $category->id]);
        $generated = Item::create(['name' => 'Kabel Baru', 'item_category_id' => $category->id]);

        $this->assertSame('DEMO-FO-001', $manual->code);
        $this->assertSame('DST-0001', $generated->code);
    }

    public function test_backfill_command_only_generates_missing_codes(): void
    {
        $category = ItemCategory::create(['name' => 'Aksesoris']);
        Item::withoutEvents(fn () => Item::create(['name' => 'Patchcord', 'item_category_id' => $category->id, 'code' => null]));
        $manual = Item::create(['name' => 'Fast Connector', 'item_category_id' => $category->id, 'code' => 'MANUAL-001']);

        $this->artisan('items:generate-missing-codes')
            ->expectsOutput('Generated codes for 1 item(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('items', ['name' => 'Patchcord', 'code' => 'AKS-0001']);
        $this->assertSame('MANUAL-001', $manual->refresh()->code);
    }
}
