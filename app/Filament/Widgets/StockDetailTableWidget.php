<?php

namespace App\Filament\Widgets;

use App\Models\Item;
use App\Support\StockFormatter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class StockDetailTableWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Detail Stok')
            ->query(self::stockDetailQuery())
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable(),
                TextColumn::make('name')->label('Barang')->searchable(),
                TextColumn::make('category.name')->label('Jenis/Kategori'),
                TextColumn::make('unit.symbol')->label('Satuan'),
                TextColumn::make('computed_current_stock')->label('Stok Saat Ini')->formatStateUsing(fn (mixed $state): string => StockFormatter::format($state))->badge()
                    ->color(fn (Item $record): string => match ($record->stock_status) {
                        'Negative', 'Empty' => 'danger',
                        'Low Stock' => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('minimum_stock')->label('Stok Minimum')->formatStateUsing(fn (mixed $state): string => StockFormatter::format($state)),
                TextColumn::make('stock_status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state, Item $record): string => $record->stock_status_label)
                    ->color(fn (string $state): string => match ($state) {
                        'Negative', 'Empty' => 'danger',
                        'Low Stock' => 'warning',
                        default => 'success',
                    }),
            ])
            ->paginationPageOptions([10, 25])
            ->defaultPaginationPageOption(10);
    }

    private static function stockDetailQuery(): Builder
    {
        $movementEffect = self::movementEffectSql();
        $currentStock = "(items.opening_balance + {$movementEffect})";

        return Item::query()
            ->with(['category', 'unit', 'movementLines.movement'])
            ->select('items.*')
            ->selectRaw("{$currentStock} as computed_current_stock")
            ->orderByRaw("
                case
                    when {$currentStock} < 0 then 0
                    when {$currentStock} = 0 then 1
                    when {$currentStock} <= items.minimum_stock then 2
                    else 3
                end
            ")
            ->orderBy('items.name');
    }

    private static function movementEffectSql(): string
    {
        return <<<'SQL'
coalesce((
    select sum(
        case stock_movements.type
            when 'stock_in' then stock_movement_lines.quantity
            when 'stock_out' then -stock_movement_lines.quantity
            when 'adjustment' then
                case
                    when stock_movements.destination_location_id is not null then stock_movement_lines.quantity
                    else -stock_movement_lines.quantity
                end
            else 0
        end
    )
    from stock_movement_lines
    inner join stock_movements on stock_movements.id = stock_movement_lines.stock_movement_id
    where stock_movement_lines.item_id = items.id
), 0)
SQL;
    }
}
