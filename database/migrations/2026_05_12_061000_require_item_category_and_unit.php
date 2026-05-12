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
        $categoryId = DB::table('item_categories')->where('name', 'Lainnya')->value('id')
            ?? DB::table('item_categories')->insertGetId([
                'name' => 'Lainnya',
                'description' => 'Kategori fallback untuk data lama tanpa jenis.',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $unitId = DB::table('units')->where('symbol', 'Pcs')->value('id')
            ?? DB::table('units')->insertGetId([
                'name' => 'Pcs',
                'symbol' => 'Pcs',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        DB::table('items')->whereNull('item_category_id')->update(['item_category_id' => $categoryId]);
        DB::table('items')->whereNull('unit_id')->update(['unit_id' => $unitId]);

        Schema::table('items', function (Blueprint $table): void {
            $table->dropForeign(['item_category_id']);
            $table->dropForeign(['unit_id']);
        });

        DB::statement('ALTER TABLE items MODIFY item_category_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE items MODIFY unit_id BIGINT UNSIGNED NOT NULL');

        Schema::table('items', function (Blueprint $table): void {
            $table->foreign('item_category_id')->references('id')->on('item_categories')->restrictOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table): void {
            $table->dropForeign(['item_category_id']);
            $table->dropForeign(['unit_id']);
        });

        DB::statement('ALTER TABLE items MODIFY item_category_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE items MODIFY unit_id BIGINT UNSIGNED NULL');

        Schema::table('items', function (Blueprint $table): void {
            $table->foreign('item_category_id')->references('id')->on('item_categories')->nullOnDelete();
            $table->foreign('unit_id')->references('id')->on('units')->nullOnDelete();
        });
    }
};
