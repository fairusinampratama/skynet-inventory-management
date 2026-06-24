<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockMovementLine;
use App\Support\StockFormatter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryExportController extends Controller
{
    public function currentStock(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            $this->putCsv($handle, ['Kode', 'Barang', 'Kategori', 'Satuan', 'Stok Saat Ini', 'Stok Minimum', 'Status']);

            Item::with(['category', 'unit', 'movementLines.movement'])->orderBy('name')->each(function (Item $item) use ($handle): void {
                $this->putCsv($handle, [
                    $item->code,
                    $item->name,
                    $item->category?->name,
                    $item->unit?->symbol,
                    StockFormatter::format($item->current_stock),
                    StockFormatter::format($item->minimum_stock),
                    $item->stock_status_label,
                ]);
            });

            fclose($handle);
        }, 'current-stock.csv', ['Content-Type' => 'text/csv']);
    }

    public function movements(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');
            $this->putCsv($handle, ['Tanggal', 'Nomor Pergerakan', 'Jenis', 'Barang', 'Jumlah', 'Asal', 'Tujuan', 'Keperluan', 'PIC', 'Catatan']);

            StockMovementLine::with(['item', 'movement.sourceLocation', 'movement.destinationLocation', 'movement.purpose'])
                ->whereHas('movement')
                ->orderBy('stock_movement_id')
                ->each(function (StockMovementLine $line) use ($handle): void {
                    $movement = $line->movement;

                    $this->putCsv($handle, [
                        $movement->movement_date?->toDateString(),
                        $movement->movement_number,
                        $movement->type->label(),
                        $line->item?->name,
                        StockFormatter::format($line->quantity),
                        $movement->sourceLocation?->name,
                        $movement->destinationLocation?->name,
                        $movement->purpose?->name,
                        $movement->pic,
                        $movement->notes,
                    ]);
                });

            fclose($handle);
        }, 'movement-history.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  resource  $handle
     * @param  array<int, mixed>  $row
     */
    private function putCsv($handle, array $row): void
    {
        fputcsv($handle, $row, ',', '"', '');
    }
}
