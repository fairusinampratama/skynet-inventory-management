<?php

namespace App\Filament\Resources\StockMovementResource\Pages;

use App\Enums\MovementType;
use App\Filament\Resources\StockMovementResource;
use App\Models\StockMovement;
use App\Support\StockFormatter;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManageStockMovements extends ManageRecords
{
    protected static string $resource = StockMovementResource::class;

    /**
     * Override record creation to wrap in a transaction and run BE stock validation.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $record = parent::handleRecordCreation($data);
            $this->validateStockLevels($record);
            return $record;
        });
    }

    /**
     * Override record update to wrap in a transaction and run BE stock validation.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $updated = parent::handleRecordUpdate($record, $data);
            $this->validateStockLevels($updated);
            return $updated;
        });
    }

    /**
     * "Last line of defense": after the record is saved (within the same transaction),
     * reload all item stocks and ensure none went below zero.
     * If any did, throw a ValidationException — Filament will display it as a form error,
     * and the transaction will be rolled back automatically.
     */
    private function validateStockLevels(StockMovement $movement): void
    {
        $type = $movement->type instanceof MovementType ? $movement->type : MovementType::from($movement->type);

        $needsStockCheck = in_array($type, [
            MovementType::StockOut,
            MovementType::Transfer,
        ]) || ($type === MovementType::Adjustment && $movement->source_location_id !== null);

        if (! $needsStockCheck) {
            return;
        }

        $sourceLocationId = $movement->source_location_id;
        $errors = [];

        foreach ($movement->lines()->with('item.unit')->get() as $line) {
            $item = $line->item;
            if (! $item) {
                continue;
            }

            $remainingStock = $item->stockForLocation($sourceLocationId);

            if ($remainingStock < 0) {
                $unit = $item->unit?->symbol ?? '';
                $unitText = $unit ? " {$unit}" : '';
                $errors[] = "Stok \"{$item->name}\" tidak mencukupi. Kekurangan: " . StockFormatter::format(abs($remainingStock)) . $unitText . '.';
            }
        }

        if (! empty($errors)) {
            throw ValidationException::withMessages([
                'lines' => $errors,
            ]);
        }
    }
}
