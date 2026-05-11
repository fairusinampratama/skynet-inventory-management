<?php

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\MovementPurpose;
use App\Models\StockAdjustmentReason;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoInventorySeeder extends Seeder
{
    /**
     * Seed realistic ISP warehouse training data without touching production baseline records.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->cleanupDemoData();

            $admin = User::where('email', 'admin@skynet.local')->first()
                ?? User::query()->first();

            $categories = $this->categories();
            $units = $this->units();
            $locations = $this->locations();
            $purposes = $this->purposes();
            $reasons = $this->adjustmentReasons();
            $items = $this->items($categories, $units);

            $this->movements($items, $locations, $purposes, $reasons, $admin?->id);
        });
    }

    private function cleanupDemoData(): void
    {
        StockMovement::query()
            ->where('movement_number', 'like', 'DEMO-%')
            ->delete();

        Item::query()
            ->where('code', 'like', 'DEMO-%')
            ->delete();

        StockLocation::query()
            ->whereIn('code', ['SDA', 'SBYB', 'GRS', 'FIELD'])
            ->delete();
    }

    /**
     * @return array<string, ItemCategory>
     */
    private function categories(): array
    {
        $names = [
            'Distribusi',
            'Feeder',
            'IKR/PSB',
            'ONT/Router',
            'Aksesoris',
            'Alat',
            'Bahan Habis Pakai',
            'Lainnya',
        ];

        return collect($names)
            ->mapWithKeys(fn (string $name): array => [
                $name => ItemCategory::firstOrCreate(
                    ['name' => $name],
                    ['description' => 'Kategori inventori demo', 'is_active' => true],
                ),
            ])
            ->all();
    }

    /**
     * @return array<string, Unit>
     */
    private function units(): array
    {
        $units = [
            ['Pcs', 'Pcs'],
            ['Meter', 'Meter'],
            ['Roll', 'Roll'],
            ['Pack', 'Pack'],
            ['Set', 'Set'],
            ['Box', 'Box'],
            ['Tube', 'Tube'],
            ['Drum', 'Drum'],
        ];

        return collect($units)
            ->mapWithKeys(fn (array $unit): array => [
                $unit[1] => Unit::firstOrCreate(
                    ['symbol' => $unit[1]],
                    ['name' => $unit[0], 'is_active' => true],
                ),
            ])
            ->all();
    }

    /**
     * @return array<string, StockLocation>
     */
    private function locations(): array
    {
        $locations = [
            'MAIN' => ['Gudang Utama', 'warehouse', 'Gudang pusat operasional dan penerimaan barang'],
            'KRIAN' => ['Krian', 'branch', 'Cabang aktif untuk area Krian dan sekitarnya'],
            'SDA' => ['Sidoarjo', 'branch', 'Cabang demo untuk pekerjaan PSB dan maintenance'],
            'SBYB' => ['Surabaya Barat', 'branch', 'Cabang demo untuk area Surabaya Barat'],
            'GRS' => ['Gresik', 'branch', 'Cabang demo untuk alokasi feeder dan ODP'],
            'FIELD' => ['Stok Teknisi', 'field', 'Stok pegangan teknisi untuk pekerjaan harian'],
        ];

        return collect($locations)
            ->mapWithKeys(fn (array $location, string $code): array => [
                $code => StockLocation::firstOrCreate(
                    ['code' => $code],
                    [
                        'name' => $location[0],
                        'type' => $location[1],
                        'notes' => $location[2],
                        'is_active' => true,
                    ],
                ),
            ])
            ->all();
    }

    /**
     * @return array<string, MovementPurpose>
     */
    private function purposes(): array
    {
        $names = [
            'Pemeliharaan',
            'PSB',
            'Pemasangan ODP',
            'Barang Masuk',
            'Stok Krian',
            'Cabang Krian',
            'Migrasi',
            'Perluasan Jaringan',
            'Stok Teknisi',
            'Retur Lapangan',
        ];

        return collect($names)
            ->mapWithKeys(fn (string $name): array => [
                $name => MovementPurpose::firstOrCreate(
                    ['name' => $name],
                    ['type' => (string) str($name)->slug(), 'is_active' => true],
                ),
            ])
            ->all();
    }

    /**
     * @return array<string, StockAdjustmentReason>
     */
    private function adjustmentReasons(): array
    {
        $names = ['Opname Stok', 'Koreksi', 'Rusak', 'Hilang', 'Pembersihan Data'];

        return collect($names)
            ->mapWithKeys(fn (string $name): array => [
                $name => StockAdjustmentReason::firstOrCreate(
                    ['name' => $name],
                    ['is_active' => true],
                ),
            ])
            ->all();
    }

    /**
     * @param  array<string, ItemCategory>  $categories
     * @param  array<string, Unit>  $units
     * @return array<string, Item>
     */
    private function items(array $categories, array $units): array
    {
        $items = [
            ['DEMO-FO-001', 'Kabel Dropcore 1 Core G657A2', 'IKR/PSB', 'Meter', 1200, 500, false],
            ['DEMO-FO-002', 'Kabel FO 2 Core Outdoor', 'Distribusi', 'Meter', 2400, 250, false],
            ['DEMO-FO-003', 'Kabel FO 4 Core Outdoor', 'Distribusi', 'Meter', 3800, 200, false],
            ['DEMO-FO-004', 'Kabel FO 8 Core Outdoor', 'Feeder', 'Meter', 6200, 300, false],
            ['DEMO-FO-005', 'Kabel FO 12 Core Outdoor', 'Feeder', 'Meter', 8500, 200, false],
            ['DEMO-FO-006', 'Kabel FO 24 Core Outdoor', 'Feeder', 'Meter', 15200, 120, false],
            ['DEMO-FO-007', 'Kabel FO 48 Core Outdoor', 'Feeder', 'Meter', 24500, 80, false],
            ['DEMO-FO-008', 'Patchcord SC/APC 3m', 'Aksesoris', 'Pcs', 16500, 30, false],
            ['DEMO-FO-009', 'Pigtail SC/APC 1.5m', 'Aksesoris', 'Pcs', 9500, 40, false],
            ['DEMO-FO-010', 'Fast Connector SC/APC', 'Aksesoris', 'Pcs', 13500, 75, false],
            ['DEMO-FO-011', 'Roset FO 2 Port', 'Aksesoris', 'Pcs', 12000, 50, false],
            ['DEMO-FO-012', 'Sleeve Protector 60mm', 'Bahan Habis Pakai', 'Pack', 35000, 4, false],
            ['DEMO-FO-013', 'Kabel Figure-8 1 Core', 'IKR/PSB', 'Meter', 1600, 300, false],
            ['DEMO-FO-014', 'Adapter SC/APC Simplex', 'Aksesoris', 'Pcs', 6500, 60, false],

            ['DEMO-ODP-001', 'ODP 8 Port Outdoor', 'Distribusi', 'Pcs', 285000, 5, false],
            ['DEMO-ODP-002', 'ODP 16 Port Outdoor', 'Distribusi', 'Pcs', 420000, 4, false],
            ['DEMO-ODP-003', 'Box ODP Kosongan', 'Aksesoris', 'Pcs', 95000, 8, false],
            ['DEMO-ODP-004', 'Splitter 1:2 SC/APC', 'Aksesoris', 'Pcs', 45000, 10, false],
            ['DEMO-ODP-005', 'Splitter 1:4 SC/APC', 'Aksesoris', 'Pcs', 65000, 10, false],
            ['DEMO-ODP-006', 'Splitter 1:8 SC/APC', 'Aksesoris', 'Pcs', 95000, 12, false],
            ['DEMO-ODP-007', 'Splitter 1:16 SC/APC', 'Aksesoris', 'Pcs', 145000, 8, false],
            ['DEMO-ODP-008', 'Closure 24 Core', 'Distribusi', 'Pcs', 385000, 3, false],
            ['DEMO-ODP-009', 'Closure 48 Core', 'Feeder', 'Pcs', 580000, 2, false],
            ['DEMO-ODP-010', 'Slack Storage FO', 'Aksesoris', 'Pcs', 35000, 15, false],
            ['DEMO-ODP-011', 'Bracket ODP Besi', 'Aksesoris', 'Pcs', 28000, 20, false],
            ['DEMO-ODP-012', 'Patch Panel FO 24 Port', 'Feeder', 'Pcs', 520000, 2, false],

            ['DEMO-CPE-001', 'ONT ZTE F670L', 'ONT/Router', 'Pcs', 330000, 15, true],
            ['DEMO-CPE-002', 'ONT Huawei EG8145V5', 'ONT/Router', 'Pcs', 360000, 15, true],
            ['DEMO-CPE-003', 'Router MikroTik hAP lite', 'ONT/Router', 'Pcs', 365000, 5, true],
            ['DEMO-CPE-004', 'Router TP-Link Archer C24', 'ONT/Router', 'Pcs', 245000, 6, true],
            ['DEMO-CPE-005', 'Adaptor ONT 12V 1A', 'Aksesoris', 'Pcs', 26000, 20, false],
            ['DEMO-CPE-006', 'Remote STB Android', 'Aksesoris', 'Pcs', 45000, 10, false],
            ['DEMO-CPE-007', 'STB Android TV', 'ONT/Router', 'Pcs', 410000, 4, true],

            ['DEMO-LAN-001', 'Kabel LAN Cat6 Outdoor', 'IKR/PSB', 'Meter', 3200, 300, false],
            ['DEMO-LAN-002', 'Patch Cable LAN 1m', 'Aksesoris', 'Pcs', 12000, 30, false],
            ['DEMO-LAN-003', 'Patch Cable LAN 5m', 'Aksesoris', 'Pcs', 25000, 20, false],
            ['DEMO-LAN-004', 'RJ45 Connector Cat6', 'Bahan Habis Pakai', 'Pack', 42000, 5, false],
            ['DEMO-LAN-005', 'Faceplate LAN Single', 'Aksesoris', 'Pcs', 18000, 20, false],

            ['DEMO-INF-001', 'Clamp Hook Kabel Dropcore', 'Aksesoris', 'Pcs', 1800, 100, false],
            ['DEMO-INF-002', 'Tiang Besi 7m', 'Distribusi', 'Pcs', 720000, 2, false],
            ['DEMO-INF-003', 'SFP 1G Single Mode', 'Feeder', 'Pcs', 185000, 4, true],
            ['DEMO-INF-004', 'SFP 10G Single Mode', 'Feeder', 'Pcs', 685000, 2, true],
            ['DEMO-INF-005', 'Media Converter Gigabit', 'Feeder', 'Pcs', 275000, 3, true],
            ['DEMO-INF-006', 'Klem Tiang Stainless', 'Aksesoris', 'Pcs', 7500, 80, false],
            ['DEMO-INF-007', 'Kabel Messenger Wire', 'Distribusi', 'Meter', 2200, 200, false],
            ['DEMO-INF-008', 'Label Kabel Outdoor', 'Bahan Habis Pakai', 'Roll', 65000, 3, false],

            ['DEMO-TOOL-001', 'Fusion Splicer Electrode', 'Alat', 'Set', 350000, 1, false],
            ['DEMO-TOOL-002', 'Cutter Fiber', 'Alat', 'Pcs', 180000, 2, false],
            ['DEMO-TOOL-003', 'Stripper Fiber', 'Alat', 'Pcs', 95000, 2, false],
            ['DEMO-TOOL-004', 'Isolasi Listrik Hitam', 'Bahan Habis Pakai', 'Pcs', 8500, 25, false],
            ['DEMO-TOOL-005', 'Cable Tie 20cm', 'Bahan Habis Pakai', 'Pack', 18500, 20, false],
            ['DEMO-TOOL-006', 'Ducting 20x10mm', 'IKR/PSB', 'Meter', 6500, 100, false],
            ['DEMO-TOOL-007', 'Lakban Kabel Outdoor', 'Bahan Habis Pakai', 'Pcs', 22000, 8, false],
        ];

        return collect($items)
            ->mapWithKeys(function (array $item) use ($categories, $units): array {
                $record = Item::create([
                    'code' => $item[0],
                    'name' => $item[1],
                    'item_category_id' => $categories[$item[2]]->id,
                    'unit_id' => $units[$item[3]]->id,
                    'price' => $item[4],
                    'opening_balance' => 0,
                    'minimum_stock' => $item[5],
                    'requires_serial_tracking' => $item[6],
                    'is_active' => true,
                    'notes' => 'Barang demo untuk latihan operasional gudang ISP.',
                ]);

                return [$record->code => $record];
            })
            ->all();
    }

    /**
     * @param  array<string, Item>  $items
     * @param  array<string, StockLocation>  $locations
     * @param  array<string, MovementPurpose>  $purposes
     * @param  array<string, StockAdjustmentReason>  $reasons
     */
    private function movements(array $items, array $locations, array $purposes, array $reasons, ?int $createdBy): void
    {
        $baseDate = CarbonImmutable::now()->subDays(90);

        $this->seedOpeningStock($items, $locations, $purposes, $reasons, $createdBy, $baseDate);
        $this->seedSupplierRestocks($items, $locations, $purposes, $reasons, $createdBy, $baseDate);
        $this->seedBranchTransfers($items, $locations, $purposes, $reasons, $createdBy, $baseDate);
        $this->seedDailyUsage($items, $locations, $purposes, $reasons, $createdBy, $baseDate);
        $this->seedAdjustments($items, $locations, $purposes, $reasons, $createdBy, $baseDate);
    }

    /**
     * @param  array<string, Item>  $items
     * @param  array<string, StockLocation>  $locations
     * @param  array<string, MovementPurpose>  $purposes
     * @param  array<string, StockAdjustmentReason>  $reasons
     */
    private function seedOpeningStock(array $items, array $locations, array $purposes, array $reasons, ?int $createdBy, CarbonImmutable $baseDate): void
    {
        $this->movement($items, $locations, $purposes, $reasons, $createdBy, '001', $baseDate->addDays(1), MovementType::StockIn, null, 'MAIN', 'Migrasi', null, 'Skynet Admin', 'Saldo awal demo untuk kabel distribusi dan feeder.', [
            'DEMO-FO-001' => 7200,
            'DEMO-FO-002' => 4000,
            'DEMO-FO-003' => 3500,
            'DEMO-FO-004' => 4200,
            'DEMO-FO-005' => 2500,
            'DEMO-FO-006' => 900,
            'DEMO-FO-007' => 600,
            'DEMO-FO-013' => 2800,
            'DEMO-INF-007' => 1800,
        ]);

        $this->movement($items, $locations, $purposes, $reasons, $createdBy, '002', $baseDate->addDays(2), MovementType::StockIn, null, 'MAIN', 'Migrasi', null, 'Operator Gudang', 'Saldo awal demo untuk ODP, splitter, closure, dan aksesoris FO.', [
            'DEMO-FO-008' => 160,
            'DEMO-FO-009' => 180,
            'DEMO-FO-010' => 320,
            'DEMO-FO-011' => 120,
            'DEMO-FO-012' => 18,
            'DEMO-FO-014' => 160,
            'DEMO-ODP-001' => 34,
            'DEMO-ODP-002' => 18,
            'DEMO-ODP-003' => 26,
            'DEMO-ODP-004' => 50,
            'DEMO-ODP-005' => 40,
            'DEMO-ODP-006' => 52,
            'DEMO-ODP-007' => 28,
            'DEMO-ODP-008' => 12,
            'DEMO-ODP-009' => 5,
            'DEMO-ODP-010' => 70,
            'DEMO-ODP-011' => 90,
            'DEMO-ODP-012' => 5,
        ]);

        $this->movement($items, $locations, $purposes, $reasons, $createdBy, '003', $baseDate->addDays(3), MovementType::StockIn, null, 'MAIN', 'Migrasi', null, 'Operator Gudang', 'Saldo awal demo untuk ONT, router, LAN, alat, dan bahan habis pakai.', [
            'DEMO-CPE-001' => 80,
            'DEMO-CPE-002' => 58,
            'DEMO-CPE-003' => 18,
            'DEMO-CPE-004' => 20,
            'DEMO-CPE-005' => 90,
            'DEMO-CPE-006' => 12,
            'DEMO-CPE-007' => 7,
            'DEMO-LAN-001' => 5000,
            'DEMO-LAN-002' => 120,
            'DEMO-LAN-003' => 80,
            'DEMO-LAN-004' => 24,
            'DEMO-LAN-005' => 55,
            'DEMO-INF-001' => 650,
            'DEMO-INF-002' => 12,
            'DEMO-INF-003' => 16,
            'DEMO-INF-004' => 8,
            'DEMO-INF-005' => 12,
            'DEMO-INF-006' => 260,
            'DEMO-INF-008' => 8,
            'DEMO-TOOL-001' => 4,
            'DEMO-TOOL-002' => 7,
            'DEMO-TOOL-003' => 5,
            'DEMO-TOOL-004' => 80,
            'DEMO-TOOL-005' => 65,
            'DEMO-TOOL-006' => 700,
            'DEMO-TOOL-007' => 24,
        ]);
    }

    /**
     * @param  array<string, Item>  $items
     * @param  array<string, StockLocation>  $locations
     * @param  array<string, MovementPurpose>  $purposes
     * @param  array<string, StockAdjustmentReason>  $reasons
     */
    private function seedSupplierRestocks(array $items, array $locations, array $purposes, array $reasons, ?int $createdBy, CarbonImmutable $baseDate): void
    {
        $this->movement($items, $locations, $purposes, $reasons, $createdBy, '010', $baseDate->addDays(9), MovementType::StockIn, null, 'MAIN', 'Barang Masuk', null, 'Rina Gudang', 'Restock kabel dan aksesoris dari supplier Surabaya.', [
            'DEMO-FO-004' => 2000,
            'DEMO-FO-005' => 1200,
            'DEMO-FO-013' => 2400,
            'DEMO-FO-010' => 180,
            'DEMO-INF-001' => 300,
            'DEMO-INF-006' => 140,
        ]);

        $this->movement($items, $locations, $purposes, $reasons, $createdBy, '011', $baseDate->addDays(18), MovementType::StockIn, null, 'MAIN', 'Barang Masuk', null, 'Maya Purchasing', 'Kiriman ONT, router, dan aksesoris untuk target PSB bulanan.', [
            'DEMO-CPE-001' => 45,
            'DEMO-CPE-002' => 35,
            'DEMO-CPE-004' => 12,
            'DEMO-CPE-005' => 50,
            'DEMO-CPE-006' => 20,
            'DEMO-LAN-002' => 60,
            'DEMO-LAN-003' => 50,
        ]);

        $this->movement($items, $locations, $purposes, $reasons, $createdBy, '012', $baseDate->addDays(33), MovementType::StockIn, null, 'MAIN', 'Barang Masuk', null, 'Rina Gudang', 'Restock ODP dan closure untuk perluasan jaringan area barat.', [
            'DEMO-ODP-001' => 16,
            'DEMO-ODP-002' => 8,
            'DEMO-ODP-008' => 5,
            'DEMO-ODP-009' => 4,
            'DEMO-ODP-011' => 40,
            'DEMO-ODP-012' => 3,
        ]);

        $this->movement($items, $locations, $purposes, $reasons, $createdBy, '013', $baseDate->addDays(47), MovementType::StockIn, null, 'MAIN', 'Barang Masuk', null, 'Dimas Gudang', 'Pembelian bahan habis pakai untuk stok teknisi.', [
            'DEMO-FO-012' => 12,
            'DEMO-LAN-004' => 15,
            'DEMO-INF-008' => 6,
            'DEMO-TOOL-004' => 50,
            'DEMO-TOOL-005' => 40,
            'DEMO-TOOL-007' => 12,
        ]);
    }

    /**
     * @param  array<string, Item>  $items
     * @param  array<string, StockLocation>  $locations
     * @param  array<string, MovementPurpose>  $purposes
     * @param  array<string, StockAdjustmentReason>  $reasons
     */
    private function seedBranchTransfers(array $items, array $locations, array $purposes, array $reasons, ?int $createdBy, CarbonImmutable $baseDate): void
    {
        $this->movement($items, $locations, $purposes, $reasons, $createdBy, '020', $baseDate->addDays(20), MovementType::Transfer, 'MAIN', 'KRIAN', 'Stok Krian', null, 'Agus Krian', 'Alokasi rutin untuk cabang Krian.', [
            'DEMO-FO-001' => 700,
            'DEMO-FO-004' => 900,
            'DEMO-FO-013' => 650,
            'DEMO-ODP-001' => 8,
            'DEMO-ODP-006' => 10,
            'DEMO-CPE-001' => 22,
            'DEMO-CPE-002' => 15,
            'DEMO-CPE-005' => 20,
            'DEMO-INF-001' => 100,
        ]);

        $this->movement($items, $locations, $purposes, $reasons, $createdBy, '021', $baseDate->addDays(31), MovementType::Transfer, 'MAIN', 'SDA', 'PSB', null, 'Budi Sidoarjo', 'Alokasi demo untuk cabang Sidoarjo.', [
            'DEMO-FO-003' => 500,
            'DEMO-FO-001' => 700,
            'DEMO-ODP-001' => 4,
            'DEMO-ODP-005' => 6,
            'DEMO-CPE-001' => 12,
            'DEMO-CPE-004' => 4,
            'DEMO-LAN-001' => 500,
        ]);

        $this->movement($items, $locations, $purposes, $reasons, $createdBy, '022', $baseDate->addDays(44), MovementType::Transfer, 'MAIN', 'SBYB', 'Perluasan Jaringan', null, 'Dewi Barat', 'Alokasi demo untuk cabang Surabaya Barat.', [
            'DEMO-FO-004' => 650,
            'DEMO-FO-001' => 650,
            'DEMO-ODP-002' => 3,
            'DEMO-ODP-006' => 5,
            'DEMO-CPE-002' => 10,
            'DEMO-CPE-005' => 10,
            'DEMO-LAN-001' => 450,
        ]);

        $this->movement($items, $locations, $purposes, $reasons, $createdBy, '023', $baseDate->addDays(58), MovementType::Transfer, 'MAIN', 'GRS', 'Perluasan Jaringan', null, 'Fajar Gresik', 'Alokasi feeder dan ODP untuk area Gresik.', [
            'DEMO-FO-005' => 500,
            'DEMO-FO-007' => 300,
            'DEMO-ODP-008' => 3,
            'DEMO-ODP-009' => 2,
            'DEMO-INF-003' => 3,
            'DEMO-INF-005' => 4,
        ]);

        $this->movement($items, $locations, $purposes, $reasons, $createdBy, '024', $baseDate->addDays(64), MovementType::Transfer, 'MAIN', 'FIELD', 'Stok Teknisi', null, 'Koordinator Teknisi', 'Stok pegangan teknisi untuk pekerjaan PSB minggu berjalan.', [
            'DEMO-FO-001' => 900,
            'DEMO-FO-010' => 80,
            'DEMO-FO-011' => 50,
            'DEMO-CPE-001' => 18,
            'DEMO-CPE-005' => 18,
            'DEMO-LAN-001' => 500,
            'DEMO-INF-001' => 160,
        ]);
    }

    /**
     * @param  array<string, Item>  $items
     * @param  array<string, StockLocation>  $locations
     * @param  array<string, MovementPurpose>  $purposes
     * @param  array<string, StockAdjustmentReason>  $reasons
     */
    private function seedDailyUsage(array $items, array $locations, array $purposes, array $reasons, ?int $createdBy, CarbonImmutable $baseDate): void
    {
        $stockOuts = [
            ['030', 24, 'MAIN', 'PSB', 'Andi Pratama', 'PSB cluster Menganti.', ['DEMO-FO-001' => 380, 'DEMO-FO-010' => 18, 'DEMO-FO-011' => 18, 'DEMO-CPE-001' => 12, 'DEMO-CPE-005' => 12, 'DEMO-LAN-001' => 180, 'DEMO-LAN-002' => 12]],
            ['031', 29, 'KRIAN', 'Pemeliharaan', 'Slamet Krian', 'Perbaikan redaman tinggi area Krian.', ['DEMO-FO-001' => 220, 'DEMO-FO-008' => 12, 'DEMO-FO-009' => 18, 'DEMO-FO-010' => 22, 'DEMO-INF-001' => 35]],
            ['032', 35, 'MAIN', 'Pemasangan ODP', 'Tono Fiber', 'Pemasangan ODP baru area Driyorejo.', ['DEMO-FO-004' => 700, 'DEMO-ODP-001' => 5, 'DEMO-ODP-006' => 8, 'DEMO-ODP-010' => 14, 'DEMO-ODP-011' => 18]],
            ['033', 42, 'SDA', 'PSB', 'Budi Sidoarjo', 'PSB apartemen dan ruko Sidoarjo.', ['DEMO-FO-001' => 520, 'DEMO-FO-010' => 36, 'DEMO-CPE-001' => 13, 'DEMO-CPE-004' => 5, 'DEMO-LAN-001' => 360]],
            ['034', 48, 'MAIN', 'Pemeliharaan', 'Eko Maintenance', 'Penggantian perangkat aktif di POP.', ['DEMO-INF-003' => 5, 'DEMO-INF-004' => 3, 'DEMO-INF-005' => 4, 'DEMO-TOOL-004' => 16, 'DEMO-TOOL-005' => 12]],
            ['035', 55, 'SBYB', 'PSB', 'Dewi Barat', 'PSB rumah pelanggan area Surabaya Barat.', ['DEMO-FO-001' => 460, 'DEMO-FO-010' => 24, 'DEMO-CPE-002' => 9, 'DEMO-CPE-005' => 8, 'DEMO-LAN-001' => 290]],
            ['036', 61, 'MAIN', 'Pemasangan ODP', 'Tono Fiber', 'Pekerjaan ODP feeder padat pelanggan.', ['DEMO-FO-006' => 940, 'DEMO-ODP-002' => 17, 'DEMO-ODP-007' => 24, 'DEMO-ODP-008' => 10]],
            ['037', 68, 'KRIAN', 'PSB', 'Agus Krian', 'Instalasi pelanggan baru area Krian.', ['DEMO-FO-001' => 760, 'DEMO-FO-010' => 64, 'DEMO-CPE-001' => 15, 'DEMO-CPE-002' => 8, 'DEMO-CPE-005' => 16, 'DEMO-LAN-001' => 300]],
            ['038', 75, 'MAIN', 'Pemeliharaan', 'Yusuf NOC', 'Penggantian perangkat uplink dan media converter.', ['DEMO-INF-004' => 5, 'DEMO-INF-005' => 7, 'DEMO-ODP-012' => 4, 'DEMO-TOOL-001' => 2, 'DEMO-TOOL-002' => 3]],
            ['039', 82, 'MAIN', 'PSB', 'Andi Pratama', 'Migrasi router pelanggan lama ke paket baru.', ['DEMO-CPE-003' => 18, 'DEMO-LAN-003' => 32, 'DEMO-FO-011' => 30, 'DEMO-TOOL-006' => 620]],
            ['040', 83, 'FIELD', 'PSB', 'Tim Teknisi A', 'Pemakaian stok teknisi untuk PSB harian.', ['DEMO-FO-001' => 680, 'DEMO-FO-010' => 58, 'DEMO-CPE-001' => 16, 'DEMO-INF-001' => 90]],
            ['041', 84, 'GRS', 'Perluasan Jaringan', 'Fajar Gresik', 'Aktivasi jalur feeder Gresik.', ['DEMO-FO-007' => 260, 'DEMO-ODP-009' => 2, 'DEMO-INF-003' => 2, 'DEMO-INF-005' => 3]],
            ['042', 86, 'MAIN', 'Pemeliharaan', 'Yusuf NOC', 'Pemakaian spare router untuk gangguan massal.', ['DEMO-CPE-007' => 7, 'DEMO-CPE-006' => 18, 'DEMO-LAN-005' => 38]],
        ];

        foreach ($stockOuts as [$number, $day, $source, $purpose, $pic, $notes, $lines]) {
            $this->movement($items, $locations, $purposes, $reasons, $createdBy, $number, $baseDate->addDays($day), MovementType::StockOut, $source, null, $purpose, null, $pic, $notes, $lines);
        }
    }

    /**
     * @param  array<string, Item>  $items
     * @param  array<string, StockLocation>  $locations
     * @param  array<string, MovementPurpose>  $purposes
     * @param  array<string, StockAdjustmentReason>  $reasons
     */
    private function seedAdjustments(array $items, array $locations, array $purposes, array $reasons, ?int $createdBy, CarbonImmutable $baseDate): void
    {
        $this->movement($items, $locations, $purposes, $reasons, $createdBy, '050', $baseDate->addDays(72), MovementType::Adjustment, 'MAIN', null, 'Pemeliharaan', 'Rusak', 'Rina Gudang', 'Barang rusak ditemukan saat pengecekan gudang.', [
            'DEMO-FO-008' => 6,
            'DEMO-FO-012' => 2,
            'DEMO-CPE-005' => 4,
            'DEMO-TOOL-007' => 2,
        ]);

        $this->movement($items, $locations, $purposes, $reasons, $createdBy, '051', $baseDate->addDays(78), MovementType::Adjustment, null, 'MAIN', 'Pemeliharaan', 'Opname Stok', 'Rina Gudang', 'Opname stok menemukan tambahan aksesoris dan bahan habis pakai.', [
            'DEMO-FO-009' => 9,
            'DEMO-LAN-004' => 2,
            'DEMO-TOOL-005' => 4,
            'DEMO-INF-008' => 1,
        ]);

        $this->movement($items, $locations, $purposes, $reasons, $createdBy, '052', $baseDate->addDays(85), MovementType::Adjustment, 'KRIAN', null, 'Pemeliharaan', 'Hilang', 'Agus Krian', 'Selisih opname stok cabang Krian.', [
            'DEMO-INF-001' => 14,
            'DEMO-CPE-005' => 3,
        ]);

        $this->movement($items, $locations, $purposes, $reasons, $createdBy, '053', $baseDate->addDays(88), MovementType::Adjustment, null, 'MAIN', 'Retur Lapangan', 'Koreksi', 'Dimas Gudang', 'Retur perangkat dari teknisi setelah pekerjaan dibatalkan.', [
            'DEMO-CPE-004' => 3,
            'DEMO-FO-010' => 12,
            'DEMO-LAN-002' => 8,
        ]);
    }

    /**
     * @param  array<string, Item>  $items
     * @param  array<string, StockLocation>  $locations
     * @param  array<string, MovementPurpose>  $purposes
     * @param  array<string, StockAdjustmentReason>  $reasons
     * @param  array<string, int|float>  $lines
     */
    private function movement(
        array $items,
        array $locations,
        array $purposes,
        array $reasons,
        ?int $createdBy,
        string $number,
        CarbonImmutable $date,
        MovementType $type,
        ?string $source,
        ?string $destination,
        ?string $purpose,
        ?string $reason,
        string $pic,
        string $notes,
        array $lines,
    ): void {
        $movement = StockMovement::create([
            'movement_number' => "DEMO-{$number}",
            'movement_date' => $date->toDateString(),
            'type' => $type,
            'source_location_id' => $source ? $locations[$source]->id : null,
            'destination_location_id' => $destination ? $locations[$destination]->id : null,
            'movement_purpose_id' => $purpose ? $purposes[$purpose]->id : null,
            'stock_adjustment_reason_id' => $reason ? $reasons[$reason]->id : null,
            'pic' => $pic,
            'notes' => $notes,
            'created_by' => $createdBy,
        ]);

        foreach ($lines as $code => $quantity) {
            $movement->lines()->create([
                'item_id' => $items[$code]->id,
                'quantity' => $quantity,
                'unit_cost' => $items[$code]->price,
                'notes' => 'Baris pergerakan demo',
            ]);
        }
    }
}
