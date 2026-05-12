<?php

namespace Database\Seeders;

use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Models\MovementPurpose;
use App\Models\StockAdjustmentReason;
use App\Models\StockLocation;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\Concerns\SeedsItemCategories;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use SeedsItemCategories;
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

        $this->seedDefaultItemCategories();

        foreach ([['Pcs', 'Pcs'], ['Meter', 'Meter'], ['Roll', 'Roll'], ['Pack', 'Pack']] as [$name, $symbol]) {
            Unit::firstOrCreate(['symbol' => $symbol], ['name' => $name, 'is_active' => true]);
        }

        StockLocation::firstOrCreate(['code' => 'MAIN'], ['name' => 'Gudang Utama', 'type' => 'warehouse', 'is_active' => true]);
        StockLocation::firstOrCreate(['code' => 'KRIAN'], ['name' => 'Krian', 'type' => 'branch', 'is_active' => true]);

        foreach ([
            'maintenance' => ['Pemeliharaan', MovementType::StockOut],
            'PSB' => ['PSB', MovementType::StockOut],
            'pemasangan odp' => ['Pemasangan ODP', MovementType::StockOut],
            'barang masuk' => ['Barang Masuk', MovementType::StockIn],
            'stok krian' => ['Stok Krian', MovementType::Transfer],
            'Cab Krian' => ['Cabang Krian', MovementType::Transfer],
            'Migrasi' => ['Migrasi', MovementType::StockIn],
        ] as $oldName => [$name, $type]) {
            $this->mergeMovementPurpose($oldName, $name, $type);
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

    private function mergeMovementPurpose(string $oldName, string $name, MovementType $type): void
    {
        $canonical = MovementPurpose::firstOrCreate(
            ['name' => $name],
            ['type' => $type->value, 'is_active' => true],
        );

        $canonical->update(['type' => $type->value, 'is_active' => true]);

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
