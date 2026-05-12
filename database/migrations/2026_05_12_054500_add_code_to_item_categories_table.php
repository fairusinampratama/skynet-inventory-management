<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('item_categories', 'code')) {
            Schema::table('item_categories', function (Blueprint $table): void {
                $table->string('code', 3)->nullable()->unique()->after('name');
            });
        }

        foreach ($this->defaultCodes() as $name => $code) {
            DB::table('item_categories')
                ->where('name', $name)
                ->update(['code' => $code]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('item_categories', 'code')) {
            Schema::table('item_categories', function (Blueprint $table): void {
                $table->dropUnique(['code']);
                $table->dropColumn('code');
            });
        }
    }

    /**
     * @return array<string, string>
     */
    private function defaultCodes(): array
    {
        return [
            'Kabel FO' => 'FE',
            'Distribusi' => 'DST',
            'Feeder' => 'FDR',
            'IKR/PSB' => 'IKR',
            'ONT/Router' => 'ONT',
            'Aksesoris' => 'AKS',
            'Alat' => 'ALT',
            'Bahan Habis Pakai' => 'BHP',
            'Lainnya' => 'LNY',
        ];
    }
};
