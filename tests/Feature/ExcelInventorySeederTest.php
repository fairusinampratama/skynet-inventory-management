<?php

namespace Tests\Feature;

use App\Models\Item;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ExcelInventorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExcelInventorySeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['inventory.seed_excel_inventory' => true]);
    }

    public function test_imports_excel_master_items_using_stok_akhir_as_opening_balance(): void
    {
        $this->seed(DatabaseSeeder::class);

        $pigtail = Item::where('name', 'Pigtail')->firstOrFail();
        $adapter = Item::where('name', 'Adaptor 5V')->firstOrFail();

        $this->assertSame('FD-PGT', $pigtail->code);
        $this->assertSame('Feeder', $pigtail->category->name);
        $this->assertSame('Pcs', $pigtail->unit->symbol);
        $this->assertSame(145.0, $pigtail->current_stock);
        $this->assertSame(-15.0, $adapter->current_stock);
        $this->assertStringContainsString('Status Excel: Instock', $pigtail->notes);
    }

    public function test_import_is_idempotent_and_handles_duplicate_excel_codes(): void
    {
        $this->seed(DatabaseSeeder::class);
        $firstCount = Item::count();

        $this->seed(ExcelInventorySeeder::class);

        $this->assertSame($firstCount, Item::count());
        $this->assertSame(1, Item::where('code', 'IKR-FTP')->count());

        $generatedDuplicate = Item::where('name', 'Kabel FTP Speedcore Cat5e')->firstOrFail();

        $this->assertNotSame('IKR-FTP', $generatedDuplicate->code);
        $this->assertStringContainsString('Kode Excel: IKR-FTP', $generatedDuplicate->notes);
    }

    public function test_importer_reports_movement_materials_without_exact_master_match(): void
    {
        $seeder = new ExcelInventorySeeder;

        $this->seed(DatabaseSeeder::class);
        $seeder->run();

        $summary = $seeder->summary();

        $this->assertSame(44, $summary['master_rows']);
        $this->assertContains('box odp kosongan', $summary['unmatched_movement_materials']);
        $this->assertContains('cleaver', $summary['unmatched_movement_materials']);
    }
}
