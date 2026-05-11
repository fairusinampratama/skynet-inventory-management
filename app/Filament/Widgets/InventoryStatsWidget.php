<?php

namespace App\Filament\Widgets;

use App\Models\Item;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class InventoryStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $items = Item::with('movementLines.movement')->get();

        return [
            Stat::make('Total Barang', (string) $items->count())
                ->icon(Heroicon::OutlinedArchiveBox),
            Stat::make('Stok Menipis', (string) $items->filter(fn (Item $item): bool => $item->stock_status === 'Low Stock')->count())
                ->icon(Heroicon::OutlinedBellAlert)
                ->color('warning'),
            Stat::make('Stok Kosong', (string) $items->filter(fn (Item $item): bool => $item->stock_status === 'Empty')->count())
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('danger'),
            Stat::make('Stok Minus', (string) $items->filter(fn (Item $item): bool => $item->stock_status === 'Negative')->count())
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger'),
        ];
    }
}
