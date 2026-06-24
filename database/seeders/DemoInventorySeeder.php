<?php

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StockAdjustmentReason;
use App\Models\StockLocation;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\SeedsItemCategories;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoInventorySeeder extends Seeder
{
    use SeedsItemCategories;

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
            $reasons = $this->adjustmentReasons();
            $items = $this->items($categories, $units);

            $this->movements($items, $locations, $reasons, $admin?->id);
        });
    }

    private function cleanupDemoData(): void
    {
        StockMovement::query()
            ->where('movement_number', 'like', 'DEMO-%')
            ->delete();

        Item::query()
            ->where('notes', 'Barang demo untuk latihan operasional gudang ISP.')
            ->orWhere('code', 'like', 'DEMO-%')
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
        return $this->seedDefaultItemCategories('Kategori inventori demo');
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
                    ['name' => $unit[0]],
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
                    ],
                ),
            ])
            ->all();
    }

    /**
     * @return array<string, StockAdjustmentReason>
     */
    private function adjustmentReasons(): array
    {
        $reasons = [
            'Opname Stok' => 'Audit',
            'Koreksi' => 'Lainnya',
            'Rusak' => 'Pengurangan',
            'Hilang' => 'Pengurangan',
            'Pembersihan Data' => 'Lainnya',
        ];

        return collect($reasons)
            ->mapWithKeys(fn (string $type, string $name): array => [
                $name => StockAdjustmentReason::firstOrCreate(
                    ['name' => $name],
                    ['type' => $type],
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
            ['IKR-0001', 'Kabel Dropcore 1 Core G657A2', 'IKR/PSB', 'Meter', 1200, 500, false],
            ['DST-0001', 'Kabel FO 2 Core Outdoor', 'Distribusi', 'Meter', 2400, 250, false],
            ['DST-0002', 'Kabel FO 4 Core Outdoor', 'Distribusi', 'Meter', 3800, 200, false],
            ['FDR-0001', 'Kabel FO 8 Core Outdoor', 'Feeder', 'Meter', 6200, 300, false],
            ['FDR-0002', 'Kabel FO 12 Core Outdoor', 'Feeder', 'Meter', 8500, 200, false],
            ['FDR-0003', 'Kabel FO 24 Core Outdoor', 'Feeder', 'Meter', 15200, 120, false],
            ['FDR-0004', 'Kabel FO 48 Core Outdoor', 'Feeder', 'Meter', 24500, 80, false],
            ['AKS-0001', 'Patchcord SC/APC 3m', 'Aksesoris', 'Pcs', 16500, 30, false],
            ['AKS-0002', 'Pigtail SC/APC 1.5m', 'Aksesoris', 'Pcs', 9500, 40, false],
            ['AKS-0003', 'Fast Connector SC/APC', 'Aksesoris', 'Pcs', 13500, 75, false],
            ['AKS-0004', 'Roset FO 2 Port', 'Aksesoris', 'Pcs', 12000, 50, false],
            ['BHP-0001', 'Sleeve Protector 60mm', 'Bahan Habis Pakai', 'Pack', 35000, 4, false],
            ['IKR-0002', 'Kabel Figure-8 1 Core', 'IKR/PSB', 'Meter', 1600, 300, false],
            ['AKS-0005', 'Adapter SC/APC Simplex', 'Aksesoris', 'Pcs', 6500, 60, false],

            ['DST-0003', 'ODP 8 Port Outdoor', 'Distribusi', 'Pcs', 285000, 5, false],
            ['DST-0004', 'ODP 16 Port Outdoor', 'Distribusi', 'Pcs', 420000, 4, false],
            ['AKS-0006', 'Box ODP Kosongan', 'Aksesoris', 'Pcs', 95000, 8, false],
            ['AKS-0007', 'Splitter 1:2 SC/APC', 'Aksesoris', 'Pcs', 45000, 10, false],
            ['AKS-0008', 'Splitter 1:4 SC/APC', 'Aksesoris', 'Pcs', 65000, 10, false],
            ['AKS-0009', 'Splitter 1:8 SC/APC', 'Aksesoris', 'Pcs', 95000, 12, false],
            ['AKS-0010', 'Splitter 1:16 SC/APC', 'Aksesoris', 'Pcs', 145000, 8, false],
            ['DST-0005', 'Closure 24 Core', 'Distribusi', 'Pcs', 385000, 3, false],
            ['FDR-0005', 'Closure 48 Core', 'Feeder', 'Pcs', 580000, 2, false],
            ['AKS-0011', 'Slack Storage FO', 'Aksesoris', 'Pcs', 35000, 15, false],
            ['AKS-0012', 'Bracket ODP Besi', 'Aksesoris', 'Pcs', 28000, 20, false],
            ['FDR-0006', 'Patch Panel FO 24 Port', 'Feeder', 'Pcs', 520000, 2, false],

            ['ONT-0001', 'ONT ZTE F670L', 'ONT/Router', 'Pcs', 330000, 15, true],
            ['ONT-0002', 'ONT Huawei EG8145V5', 'ONT/Router', 'Pcs', 360000, 15, true],
            ['ONT-0003', 'Router MikroTik hAP lite', 'ONT/Router', 'Pcs', 365000, 5, true],
            ['ONT-0004', 'Router TP-Link Archer C24', 'ONT/Router', 'Pcs', 245000, 6, true],
            ['AKS-0013', 'Adaptor ONT 12V 1A', 'Aksesoris', 'Pcs', 26000, 20, false],
            ['AKS-0014', 'Remote STB Android', 'Aksesoris', 'Pcs', 45000, 10, false],
            ['ONT-0005', 'STB Android TV', 'ONT/Router', 'Pcs', 410000, 4, true],

            ['IKR-0003', 'Kabel LAN Cat6 Outdoor', 'IKR/PSB', 'Meter', 3200, 300, false],
            ['AKS-0015', 'Patch Cable LAN 1m', 'Aksesoris', 'Pcs', 12000, 30, false],
            ['AKS-0016', 'Patch Cable LAN 5m', 'Aksesoris', 'Pcs', 25000, 20, false],
            ['BHP-0002', 'RJ45 Connector Cat6', 'Bahan Habis Pakai', 'Pack', 42000, 5, false],
            ['AKS-0017', 'Faceplate LAN Single', 'Aksesoris', 'Pcs', 18000, 20, false],

            ['AKS-0018', 'Clamp Hook Kabel Dropcore', 'Aksesoris', 'Pcs', 1800, 100, false],
            ['DST-0006', 'Tiang Besi 7m', 'Distribusi', 'Pcs', 720000, 2, false],
            ['FDR-0007', 'SFP 1G Single Mode', 'Feeder', 'Pcs', 185000, 4, true],
            ['FDR-0008', 'SFP 10G Single Mode', 'Feeder', 'Pcs', 685000, 2, true],
            ['FDR-0009', 'Media Converter Gigabit', 'Feeder', 'Pcs', 275000, 3, true],
            ['AKS-0019', 'Klem Tiang Stainless', 'Aksesoris', 'Pcs', 7500, 80, false],
            ['DST-0007', 'Kabel Messenger Wire', 'Distribusi', 'Meter', 2200, 200, false],
            ['BHP-0003', 'Label Kabel Outdoor', 'Bahan Habis Pakai', 'Roll', 65000, 3, false],

            ['ALT-0001', 'Fusion Splicer Electrode', 'Alat', 'Set', 350000, 1, false],
            ['ALT-0002', 'Cutter Fiber', 'Alat', 'Pcs', 180000, 2, false],
            ['ALT-0003', 'Stripper Fiber', 'Alat', 'Pcs', 95000, 2, false],
            ['BHP-0004', 'Isolasi Listrik Hitam', 'Bahan Habis Pakai', 'Pcs', 8500, 25, false],
            ['BHP-0005', 'Cable Tie 20cm', 'Bahan Habis Pakai', 'Pack', 18500, 20, false],
            ['IKR-0004', 'Ducting 20x10mm', 'IKR/PSB', 'Meter', 6500, 100, false],
            ['BHP-0006', 'Lakban Kabel Outdoor', 'Bahan Habis Pakai', 'Pcs', 22000, 8, false],
        ];

        return collect($items)
            ->mapWithKeys(function (array $item) use ($categories, $units): array {
                $record = Item::create([
                    'code' => $item[0],
                    'name' => $item[1],
                    'item_category_id' => $categories[$item[2]]->id,
                    'unit_id' => $units[$item[3]]->id,
                    'price' => $item[4],
                    'minimum_stock' => $item[5],
                    'notes' => 'Barang demo untuk latihan operasional gudang ISP.',
                ]);

                return [$record->code => $record];
            })
            ->all();
    }

    /**
     * @param  array<string, Item>  $items
     * @param  array<string, StockLocation>  $locations
     * @param  array<string, StockAdjustmentReason>  $reasons
     */
    private function movements(array $items, array $locations, array $reasons, ?int $createdBy): void
    {
        $baseDate = CarbonImmutable::now()->subDays(90);

        $this->seedOpeningStock($items, $locations, $reasons, $createdBy, $baseDate);
        $this->seedSupplierRestocks($items, $locations, $reasons, $createdBy, $baseDate);
        $this->seedBranchTransfers($items, $locations, $reasons, $createdBy, $baseDate);
        $this->seedDailyUsage($items, $locations, $reasons, $createdBy, $baseDate);
        $this->seedAdjustments($items, $locations, $reasons, $createdBy, $baseDate);
    }

    /**
     * @param  array<string, Item>  $items
     * @param  array<string, StockLocation>  $locations
     * @param  array<string, StockAdjustmentReason>  $reasons
     */
    private function seedOpeningStock(array $items, array $locations, array $reasons, ?int $createdBy, CarbonImmutable $baseDate): void
    {
        $this->movement($items, $locations, $reasons, $createdBy, '001', $baseDate->addDays(1), MovementType::StockIn, null, 'MAIN', null, 'Skynet Admin', 'Saldo awal demo untuk kabel distribusi dan feeder.', [
            'IKR-0001' => 7200,
            'DST-0001' => 4000,
            'DST-0002' => 3500,
            'FDR-0001' => 4200,
            'FDR-0002' => 2500,
            'FDR-0003' => 900,
            'FDR-0004' => 600,
            'IKR-0002' => 2800,
            'DST-0007' => 1800,
        ]);

        $this->movement($items, $locations, $reasons, $createdBy, '002', $baseDate->addDays(2), MovementType::StockIn, null, 'MAIN', null, 'Operator Gudang', 'Saldo awal demo untuk ODP, splitter, closure, dan aksesoris FO.', [
            'AKS-0001' => 160,
            'AKS-0002' => 180,
            'AKS-0003' => 320,
            'AKS-0004' => 120,
            'BHP-0001' => 18,
            'AKS-0005' => 160,
            'DST-0003' => 34,
            'DST-0004' => 18,
            'AKS-0006' => 26,
            'AKS-0007' => 50,
            'AKS-0008' => 40,
            'AKS-0009' => 52,
            'AKS-0010' => 28,
            'DST-0005' => 12,
            'FDR-0005' => 5,
            'AKS-0011' => 70,
            'AKS-0012' => 90,
            'FDR-0006' => 5,
        ]);

        $this->movement($items, $locations, $reasons, $createdBy, '003', $baseDate->addDays(3), MovementType::StockIn, null, 'MAIN', null, 'Operator Gudang', 'Saldo awal demo untuk ONT, router, LAN, alat, dan bahan habis pakai.', [
            'ONT-0001' => 80,
            'ONT-0002' => 58,
            'ONT-0003' => 18,
            'ONT-0004' => 20,
            'AKS-0013' => 90,
            'AKS-0014' => 12,
            'ONT-0005' => 7,
            'IKR-0003' => 5000,
            'AKS-0015' => 120,
            'AKS-0016' => 80,
            'BHP-0002' => 24,
            'AKS-0017' => 55,
            'AKS-0018' => 650,
            'DST-0006' => 12,
            'FDR-0007' => 16,
            'FDR-0008' => 8,
            'FDR-0009' => 12,
            'AKS-0019' => 260,
            'BHP-0003' => 8,
            'ALT-0001' => 4,
            'ALT-0002' => 7,
            'ALT-0003' => 5,
            'BHP-0004' => 80,
            'BHP-0005' => 65,
            'IKR-0004' => 700,
            'BHP-0006' => 24,
        ]);
    }

    /**
     * @param  array<string, Item>  $items
     * @param  array<string, StockLocation>  $locations
     * @param  array<string, StockAdjustmentReason>  $reasons
     */
    private function seedSupplierRestocks(array $items, array $locations, array $reasons, ?int $createdBy, CarbonImmutable $baseDate): void
    {
        $this->movement($items, $locations, $reasons, $createdBy, '010', $baseDate->addDays(9), MovementType::StockIn, null, 'MAIN', null, 'Rina Gudang', 'Restock kabel dan aksesoris dari supplier Surabaya.', [
            'FDR-0001' => 2000,
            'FDR-0002' => 1200,
            'IKR-0002' => 2400,
            'AKS-0003' => 180,
            'AKS-0018' => 300,
            'AKS-0019' => 140,
        ]);

        $this->movement($items, $locations, $reasons, $createdBy, '011', $baseDate->addDays(18), MovementType::StockIn, null, 'MAIN', null, 'Maya Purchasing', 'Kiriman ONT, router, dan aksesoris untuk target PSB bulanan.', [
            'ONT-0001' => 45,
            'ONT-0002' => 35,
            'ONT-0004' => 12,
            'AKS-0013' => 50,
            'AKS-0014' => 20,
            'AKS-0015' => 60,
            'AKS-0016' => 50,
        ]);

        $this->movement($items, $locations, $reasons, $createdBy, '012', $baseDate->addDays(33), MovementType::StockIn, null, 'MAIN', null, 'Rina Gudang', 'Restock ODP dan closure untuk perluasan jaringan area barat.', [
            'DST-0003' => 16,
            'DST-0004' => 8,
            'DST-0005' => 5,
            'FDR-0005' => 4,
            'AKS-0012' => 40,
            'FDR-0006' => 3,
        ]);

        $this->movement($items, $locations, $reasons, $createdBy, '013', $baseDate->addDays(47), MovementType::StockIn, null, 'MAIN', null, 'Dimas Gudang', 'Pembelian bahan habis pakai untuk stok teknisi.', [
            'BHP-0001' => 12,
            'BHP-0002' => 15,
            'BHP-0003' => 6,
            'BHP-0004' => 50,
            'BHP-0005' => 40,
            'BHP-0006' => 12,
        ]);
    }

    /**
     * @param  array<string, Item>  $items
     * @param  array<string, StockLocation>  $locations
     * @param  array<string, StockAdjustmentReason>  $reasons
     */
    private function seedBranchTransfers(array $items, array $locations, array $reasons, ?int $createdBy, CarbonImmutable $baseDate): void
    {
        $this->movement($items, $locations, $reasons, $createdBy, '020', $baseDate->addDays(20), MovementType::Transfer, 'MAIN', 'KRIAN', null, 'Agus Krian', 'Alokasi rutin untuk cabang Krian.', [
            'IKR-0001' => 700,
            'FDR-0001' => 900,
            'IKR-0002' => 650,
            'DST-0003' => 8,
            'AKS-0009' => 10,
            'ONT-0001' => 22,
            'ONT-0002' => 15,
            'AKS-0013' => 20,
            'AKS-0018' => 100,
        ]);

        $this->movement($items, $locations, $reasons, $createdBy, '021', $baseDate->addDays(31), MovementType::Transfer, 'MAIN', 'SDA', null, 'Budi Sidoarjo', 'Alokasi demo untuk cabang Sidoarjo.', [
            'DST-0002' => 500,
            'IKR-0001' => 700,
            'DST-0003' => 4,
            'AKS-0008' => 6,
            'ONT-0001' => 12,
            'ONT-0004' => 4,
            'IKR-0003' => 500,
        ]);

        $this->movement($items, $locations, $reasons, $createdBy, '022', $baseDate->addDays(44), MovementType::Transfer, 'MAIN', 'SBYB', null, 'Dewi Barat', 'Alokasi demo untuk cabang Surabaya Barat.', [
            'FDR-0001' => 650,
            'IKR-0001' => 650,
            'DST-0004' => 3,
            'AKS-0009' => 5,
            'ONT-0002' => 10,
            'AKS-0013' => 10,
            'IKR-0003' => 450,
        ]);

        $this->movement($items, $locations, $reasons, $createdBy, '023', $baseDate->addDays(58), MovementType::Transfer, 'MAIN', 'GRS', null, 'Fajar Gresik', 'Alokasi feeder dan ODP untuk area Gresik.', [
            'FDR-0002' => 500,
            'FDR-0004' => 300,
            'DST-0005' => 3,
            'FDR-0005' => 2,
            'FDR-0007' => 3,
            'FDR-0009' => 4,
        ]);

        $this->movement($items, $locations, $reasons, $createdBy, '024', $baseDate->addDays(64), MovementType::Transfer, 'MAIN', 'FIELD', null, 'Koordinator Teknisi', 'Stok pegangan teknisi untuk pekerjaan PSB minggu berjalan.', [
            'IKR-0001' => 900,
            'AKS-0003' => 80,
            'AKS-0004' => 50,
            'ONT-0001' => 18,
            'AKS-0013' => 18,
            'IKR-0003' => 500,
            'AKS-0018' => 160,
        ]);
    }

    /**
     * @param  array<string, Item>  $items
     * @param  array<string, StockLocation>  $locations
     * @param  array<string, StockAdjustmentReason>  $reasons
     */
    private function seedDailyUsage(array $items, array $locations, array $reasons, ?int $createdBy, CarbonImmutable $baseDate): void
    {
        $stockOuts = [
            ['030', 24, 'MAIN', 'PSB', 'Andi Pratama', 'PSB cluster Menganti.', ['IKR-0001' => 380, 'AKS-0003' => 18, 'AKS-0004' => 18, 'ONT-0001' => 12, 'AKS-0013' => 12, 'IKR-0003' => 180, 'AKS-0015' => 12]],
            ['031', 29, 'KRIAN', 'Pemeliharaan', 'Slamet Krian', 'Perbaikan redaman tinggi area Krian.', ['IKR-0001' => 220, 'AKS-0001' => 12, 'AKS-0002' => 18, 'AKS-0003' => 22, 'AKS-0018' => 35]],
            ['032', 35, 'MAIN', 'Pemasangan ODP', 'Tono Fiber', 'Pemasangan ODP baru area Driyorejo.', ['FDR-0001' => 700, 'DST-0003' => 5, 'AKS-0009' => 8, 'AKS-0011' => 14, 'AKS-0012' => 18]],
            ['033', 42, 'SDA', 'PSB', 'Budi Sidoarjo', 'PSB apartemen dan ruko Sidoarjo.', ['IKR-0001' => 520, 'AKS-0003' => 36, 'ONT-0001' => 13, 'ONT-0004' => 5, 'IKR-0003' => 360]],
            ['034', 48, 'MAIN', 'Pemeliharaan', 'Eko Maintenance', 'Penggantian perangkat aktif di POP.', ['FDR-0007' => 5, 'FDR-0008' => 3, 'FDR-0009' => 4, 'BHP-0004' => 16, 'BHP-0005' => 12]],
            ['035', 55, 'SBYB', 'PSB', 'Dewi Barat', 'PSB rumah pelanggan area Surabaya Barat.', ['IKR-0001' => 460, 'AKS-0003' => 24, 'ONT-0002' => 9, 'AKS-0013' => 8, 'IKR-0003' => 290]],
            ['036', 61, 'MAIN', 'Pemasangan ODP', 'Tono Fiber', 'Pekerjaan ODP feeder padat pelanggan.', ['FDR-0003' => 940, 'DST-0004' => 17, 'AKS-0010' => 24, 'DST-0005' => 10]],
            ['037', 68, 'KRIAN', 'PSB', 'Agus Krian', 'Instalasi pelanggan baru area Krian.', ['IKR-0001' => 760, 'AKS-0003' => 64, 'ONT-0001' => 15, 'ONT-0002' => 8, 'AKS-0013' => 16, 'IKR-0003' => 300]],
            ['038', 75, 'MAIN', 'Pemeliharaan', 'Yusuf NOC', 'Penggantian perangkat uplink dan media converter.', ['FDR-0008' => 5, 'FDR-0009' => 7, 'FDR-0006' => 4, 'ALT-0001' => 2, 'ALT-0002' => 3]],
            ['039', 82, 'MAIN', 'PSB', 'Andi Pratama', 'Migrasi router pelanggan lama ke paket baru.', ['ONT-0003' => 18, 'AKS-0016' => 32, 'AKS-0004' => 30, 'IKR-0004' => 620]],
            ['040', 83, 'FIELD', 'PSB', 'Tim Teknisi A', 'Pemakaian stok teknisi untuk PSB harian.', ['IKR-0001' => 680, 'AKS-0003' => 58, 'ONT-0001' => 16, 'AKS-0018' => 90]],
            ['041', 84, 'GRS', 'Perluasan Jaringan', 'Fajar Gresik', 'Aktivasi jalur feeder Gresik.', ['FDR-0004' => 260, 'FDR-0005' => 2, 'FDR-0007' => 2, 'FDR-0009' => 3]],
            ['042', 86, 'MAIN', 'Pemeliharaan', 'Yusuf NOC', 'Pemakaian spare router untuk gangguan massal.', ['ONT-0005' => 7, 'AKS-0014' => 18, 'AKS-0017' => 38]],
        ];

        foreach ($stockOuts as [$number, $day, $source, $purpose, $pic, $notes, $lines]) {
            $this->movement($items, $locations, $reasons, $createdBy, $number, $baseDate->addDays($day), MovementType::StockOut, $source, null, null, $pic, $notes, $lines);
        }
    }

    /**
     * @param  array<string, Item>  $items
     * @param  array<string, StockLocation>  $locations
     * @param  array<string, StockAdjustmentReason>  $reasons
     */
    private function seedAdjustments(array $items, array $locations, array $reasons, ?int $createdBy, CarbonImmutable $baseDate): void
    {
        $this->movement($items, $locations, $reasons, $createdBy, '050', $baseDate->addDays(72), MovementType::Adjustment, 'MAIN', null, 'Rusak', 'Rina Gudang', 'Barang rusak ditemukan saat pengecekan gudang.', [
            'AKS-0001' => 6,
            'BHP-0001' => 2,
            'AKS-0013' => 4,
            'BHP-0006' => 2,
        ]);

        $this->movement($items, $locations, $reasons, $createdBy, '051', $baseDate->addDays(78), MovementType::Adjustment, null, 'MAIN', 'Opname Stok', 'Rina Gudang', 'Opname stok menemukan tambahan aksesoris dan bahan habis pakai.', [
            'AKS-0002' => 9,
            'BHP-0002' => 2,
            'BHP-0005' => 4,
            'BHP-0003' => 1,
        ]);

        $this->movement($items, $locations, $reasons, $createdBy, '052', $baseDate->addDays(85), MovementType::Adjustment, 'KRIAN', null, 'Hilang', 'Agus Krian', 'Selisih opname stok cabang Krian.', [
            'AKS-0018' => 14,
            'AKS-0013' => 3,
        ]);

        $this->movement($items, $locations, $reasons, $createdBy, '053', $baseDate->addDays(88), MovementType::Adjustment, null, 'MAIN', 'Koreksi', 'Dimas Gudang', 'Retur perangkat dari teknisi setelah pekerjaan dibatalkan.', [
            'ONT-0004' => 3,
            'AKS-0003' => 12,
            'AKS-0015' => 8,
        ]);
    }

    /**
     * @param  array<string, Item>  $items
     * @param  array<string, StockLocation>  $locations
     * @param  array<string, StockAdjustmentReason>  $reasons
     * @param  array<string, int|float>  $lines
     */
    private function movement(
        array $items,
        array $locations,
        array $reasons,
        ?int $createdBy,
        string $number,
        CarbonImmutable $date,
        MovementType $type,
        ?string $source,
        ?string $destination,
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
