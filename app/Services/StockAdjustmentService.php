<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Models\Item;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class StockAdjustmentService
{
    public function adjustToActualStock(
        Item $item,
        int $locationId,
        float $actualStock,
        int $reasonId,
        ?string $pic = null,
        ?string $notes = null,
    ): ?StockMovement {
        $currentStock = $item->stockForLocation($locationId);
        $difference = round($actualStock - $currentStock, 3);

        if ($difference === 0.0) {
            return null;
        }

        return DB::transaction(function () use ($item, $locationId, $difference, $reasonId, $pic, $notes): StockMovement {
            $movement = StockMovement::create([
                'movement_date' => now()->toDateString(),
                'type' => MovementType::Adjustment,
                'source_location_id' => $difference < 0 ? $locationId : null,
                'destination_location_id' => $difference > 0 ? $locationId : null,
                'stock_adjustment_reason_id' => $reasonId,
                'pic' => $pic,
                'notes' => $notes,
            ]);

            $movement->lines()->create([
                'item_id' => $item->id,
                'quantity' => abs($difference),
            ]);

            return $movement;
        });
    }
}
