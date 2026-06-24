<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['movement_number', 'movement_date', 'type', 'source_location_id', 'destination_location_id', 'movement_purpose_id', 'stock_adjustment_reason_id', 'pic', 'notes', 'created_by'])]
class StockMovement extends Model
{
    use LogsActivity;

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'type' => MovementType::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StockMovement $movement): void {
            if (! $movement->movement_number) {
                $movement->movement_number = static::nextMovementNumber();
            }

            if (! $movement->created_by && auth()->check()) {
                $movement->created_by = auth()->id();
            }
        });
    }

    public static function nextMovementNumber(): string
    {
        return 'SM-'.now()->format('YmdHis').'-'.str_pad((string) random_int(1, 999), 3, '0', STR_PAD_LEFT);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function lines(): HasMany
    {
        return $this->hasMany(StockMovementLine::class);
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(StockLocation::class, 'destination_location_id');
    }


    public function adjustmentReason(): BelongsTo
    {
        return $this->belongsTo(StockAdjustmentReason::class, 'stock_adjustment_reason_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function globalQuantityEffect(float $quantity): float
    {
        return match ($this->type) {
            MovementType::StockIn => $quantity,
            MovementType::StockOut => -$quantity,
            MovementType::Adjustment => $this->destination_location_id ? $quantity : -$quantity,
            MovementType::Transfer => 0.0,
        };
    }

    public function isIncomingFor(int $locationId): bool
    {
        return match ($this->type) {
            MovementType::StockIn, MovementType::Transfer => (int) $this->destination_location_id === $locationId,
            MovementType::Adjustment => $this->destination_location_id && (int) $this->destination_location_id === $locationId,
            MovementType::StockOut => false,
        };
    }

    public function isOutgoingFor(int $locationId): bool
    {
        return match ($this->type) {
            MovementType::StockOut, MovementType::Transfer => (int) $this->source_location_id === $locationId,
            MovementType::Adjustment => $this->source_location_id && (int) $this->source_location_id === $locationId,
            MovementType::StockIn => false,
        };
    }
}
