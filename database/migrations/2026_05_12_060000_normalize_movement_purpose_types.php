<?php

use App\Enums\MovementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->controlledTypes() as $name => $type) {
            DB::table('movement_purposes')
                ->where('name', $name)
                ->update(['type' => $type->value]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->legacyTypes() as $name => $type) {
            DB::table('movement_purposes')
                ->where('name', $name)
                ->update(['type' => $type]);
        }
    }

    /**
     * @return array<string, MovementType>
     */
    private function controlledTypes(): array
    {
        return [
            'Pemeliharaan' => MovementType::StockOut,
            'PSB' => MovementType::StockOut,
            'Pemasangan ODP' => MovementType::StockOut,
            'Barang Masuk' => MovementType::StockIn,
            'Stok Krian' => MovementType::Transfer,
            'Cabang Krian' => MovementType::Transfer,
            'Migrasi' => MovementType::StockIn,
            'Perluasan Jaringan' => MovementType::StockOut,
            'Stok Teknisi' => MovementType::Transfer,
            'Retur Lapangan' => MovementType::Adjustment,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function legacyTypes(): array
    {
        return [
            'Pemeliharaan' => 'pemeliharaan',
            'PSB' => 'psb',
            'Pemasangan ODP' => 'pemasangan-odp',
            'Barang Masuk' => 'barang-masuk',
            'Stok Krian' => 'stok-krian',
            'Cabang Krian' => 'cabang-krian',
            'Migrasi' => 'migrasi',
            'Perluasan Jaringan' => 'perluasan-jaringan',
            'Stok Teknisi' => 'stok-teknisi',
            'Retur Lapangan' => 'retur-lapangan',
        ];
    }
};
