<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\StockMovementLine;
use App\Support\StockFormatter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryExportController extends Controller
{
    public function currentStock(Request $request): StreamedResponse
    {
        $locationId = $request->filled('location_id') ? (int) $request->input('location_id') : null;

        return response()->streamDownload(function () use ($request, $locationId): void {
            $handle = fopen('php://output', 'w');

            $headers = ['Kode', 'Barang', 'Kategori', 'Satuan', 'Stok Saat Ini', 'Stok Minimum', 'Status'];
            if ($locationId) {
                $headers[] = 'Lokasi';
            }
            $this->putCsv($handle, $headers);

            $query = Item::with(['category', 'unit', 'movementLines.movement'])->orderBy('name');

            // Filter: category
            if ($request->filled('category_id')) {
                $query->where('item_category_id', $request->input('category_id'));
            }

            // Filter: unit
            if ($request->filled('unit_id')) {
                $query->where('unit_id', $request->input('unit_id'));
            }

            // Filter: stock_status (mirrors ItemResource filter logic)
            if ($request->filled('stock_status')) {
                $stockSql = $this->currentStockSubquery();
                $status = $request->input('stock_status');
                match ($status) {
                    'negative' => $query->whereRaw("({$stockSql}) < 0"),
                    'empty'    => $query->whereRaw("({$stockSql}) = 0"),
                    'low'      => $query->whereRaw("({$stockSql}) > 0 AND ({$stockSql}) <= items.minimum_stock"),
                    'ok'       => $query->whereRaw("({$stockSql}) > items.minimum_stock"),
                    default    => null,
                };
            }

            // Filter: needs_reorder (ternary: '1' = true, '0' = false)
            if ($request->has('needs_reorder') && $request->input('needs_reorder') !== '') {
                $stockSql = $this->currentStockSubquery();
                if ($request->input('needs_reorder') === '1') {
                    $query->whereRaw("({$stockSql}) > 0")
                          ->whereRaw("({$stockSql}) <= items.minimum_stock");
                } else {
                    $query->whereRaw("({$stockSql}) > items.minimum_stock OR ({$stockSql}) <= 0");
                }
            }

            // Filter: location — only items with stock > 0 at that location
            if ($locationId) {
                $query->whereRaw('
                    coalesce((
                        select sum(
                            case
                                when sm.destination_location_id = ? then sml.quantity
                                when sm.source_location_id = ? then -sml.quantity
                                else 0
                            end
                        )
                        from stock_movement_lines sml
                        inner join stock_movements sm on sm.id = sml.stock_movement_id
                        where sml.item_id = items.id
                    ), 0) > 0
                ', [$locationId, $locationId]);
            }

            $locationName = $locationId
                ? \App\Models\StockLocation::find($locationId)?->name
                : null;

            $query->each(function (Item $item) use ($handle, $locationId, $locationName): void {
                $stock = $locationId
                    ? $item->stockForLocation($locationId)
                    : $item->current_stock;

                $row = [
                    $item->code,
                    $item->name,
                    $item->category?->name,
                    $item->unit?->symbol,
                    StockFormatter::format($stock),
                    StockFormatter::format($item->minimum_stock),
                    $item->stock_status_label,
                ];

                if ($locationId) {
                    $row[] = $locationName;
                }

                $this->putCsv($handle, $row);
            });

            fclose($handle);
        }, 'current-stock.csv', ['Content-Type' => 'text/csv']);
    }

    /**
     * SQL subquery to compute current global stock of an item.
     * Mirrors the logic in ItemResource::currentStockSubquery().
     */
    private function currentStockSubquery(): string
    {
        return <<<'SQL'
coalesce((
    select sum(
        case
            when sm.destination_location_id is not null and sm.source_location_id is null then sml.quantity
            when sm.source_location_id is not null and sm.destination_location_id is null then -sml.quantity
            else 0
        end
    )
    from stock_movement_lines sml
    inner join stock_movements sm on sm.id = sml.stock_movement_id
    where sml.item_id = items.id
), 0)
SQL;
    }

    public function movements(Request $request): StreamedResponse
    {
        return response()->streamDownload(function () use ($request): void {
            $handle = fopen('php://output', 'w');
            $this->putCsv($handle, ['Tanggal', 'Nomor Pergerakan', 'Jenis', 'Barang', 'Jumlah', 'Asal', 'Tujuan', 'Keperluan', 'PIC', 'Catatan']);

            $query = StockMovementLine::with(['item', 'movement.sourceLocation', 'movement.destinationLocation', 'movement.purpose'])
                ->whereHas('movement')
                ->orderBy('stock_movement_id');

            // Filter: type
            if ($request->filled('type')) {
                $query->whereHas('movement', fn ($q) => $q->where('type', $request->input('type')));
            }

            // Filter: date range (from / until)
            if ($request->filled('from')) {
                $query->whereHas('movement', fn ($q) => $q->whereDate('movement_date', '>=', $request->input('from')));
            }
            if ($request->filled('until')) {
                $query->whereHas('movement', fn ($q) => $q->whereDate('movement_date', '<=', $request->input('until')));
            }

            // Filter: location (source OR destination)
            if ($request->filled('location_id')) {
                $locationId = $request->input('location_id');
                $query->whereHas('movement', fn ($q) => $q->where(function ($q2) use ($locationId) {
                    $q2->where('source_location_id', $locationId)
                       ->orWhere('destination_location_id', $locationId);
                }));
            }

            // Filter: item
            if ($request->filled('item_id')) {
                $query->where('item_id', $request->input('item_id'));
            }

            // Filter: PIC
            if ($request->filled('pic')) {
                $query->whereHas('movement', fn ($q) => $q->where('pic', $request->input('pic')));
            }

            $query->each(function (StockMovementLine $line) use ($handle): void {
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
