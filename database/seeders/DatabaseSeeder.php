<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\ItemCategory;
use App\Models\MovementPurpose;
use App\Models\StockAdjustmentReason;
use App\Models\StockLocation;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@skynet.local'],
            ['name' => 'Skynet Admin', 'password' => 'password', 'role' => UserRole::Admin],
        );

        User::updateOrCreate(
            ['email' => 'warehouse@skynet.local'],
            ['name' => 'Operator Gudang', 'password' => 'password', 'role' => UserRole::Warehouse],
        );

        foreach (['Distribusi', 'Feeder', 'IKR/PSB', 'Lainnya'] as $name) {
            ItemCategory::firstOrCreate(['name' => $name], ['is_active' => true]);
        }

        foreach ([['Pcs', 'Pcs'], ['Meter', 'Meter'], ['Roll', 'Roll'], ['Pack', 'Pack']] as [$name, $symbol]) {
            Unit::firstOrCreate(['symbol' => $symbol], ['name' => $name, 'is_active' => true]);
        }

        StockLocation::firstOrCreate(['code' => 'MAIN'], ['name' => 'Gudang Utama', 'type' => 'warehouse', 'is_active' => true]);
        StockLocation::firstOrCreate(['code' => 'KRIAN'], ['name' => 'Krian', 'type' => 'branch', 'is_active' => true]);

        foreach ([
            'maintenance' => 'Pemeliharaan',
            'PSB' => 'PSB',
            'pemasangan odp' => 'Pemasangan ODP',
            'barang masuk' => 'Barang Masuk',
            'stok krian' => 'Stok Krian',
            'Cab Krian' => 'Cabang Krian',
            'Migrasi' => 'Migrasi',
        ] as $oldName => $name) {
            $this->mergeMovementPurpose($oldName, $name);
        }

        foreach ([
            'Stock opname' => 'Opname Stok',
            'Opname Stok' => 'Opname Stok',
            'Correction' => 'Koreksi',
            'Damaged' => 'Rusak',
            'Lost' => 'Hilang',
            'Excel cleanup' => 'Pembersihan Data',
            'Pembersihan Excel' => 'Pembersihan Data',
        ] as $oldName => $name) {
            $this->mergeAdjustmentReason($oldName, $name);
        }
    }

    private function mergeMovementPurpose(string $oldName, string $name): void
    {
        $canonical = MovementPurpose::firstOrCreate(
            ['name' => $name],
            ['type' => (string) str($name)->slug(), 'is_active' => true],
        );

        $canonical->update(['type' => (string) str($name)->slug(), 'is_active' => true]);

        if ($oldName === $name) {
            return;
        }

        $old = MovementPurpose::where('name', $oldName)->first();

        if (! $old || $old->is($canonical)) {
            return;
        }

        DB::table('stock_movements')
            ->where('movement_purpose_id', $old->id)
            ->update(['movement_purpose_id' => $canonical->id]);

        $old->delete();
    }

    private function mergeAdjustmentReason(string $oldName, string $name): void
    {
        $canonical = StockAdjustmentReason::firstOrCreate(['name' => $name], ['is_active' => true]);
        $canonical->update(['is_active' => true]);

        if ($oldName === $name) {
            return;
        }

        $old = StockAdjustmentReason::where('name', $oldName)->first();

        if (! $old || $old->is($canonical)) {
            return;
        }

        DB::table('stock_movements')
            ->where('stock_adjustment_reason_id', $old->id)
            ->update(['stock_adjustment_reason_id' => $canonical->id]);

        $old->delete();
    }
}
