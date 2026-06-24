<?php

namespace App\Models;

use App\Services\ItemCodeGenerator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['code', 'name', 'item_category_id', 'unit_id', 'price', 'minimum_stock', 'notes'])]
class Item extends Model
{
    use LogsActivity;

    protected static function booted(): void
    {
        static::creating(function (Item $item): void {
            $item->code = $item->code ? trim($item->code) : null;

            if (! $item->code) {
                $item->code = app(ItemCodeGenerator::class)->generateForCategoryId($item->item_category_id);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'minimum_stock' => 'decimal:3',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ItemCategory::class, 'item_category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function movementLines(): HasMany
    {
        return $this->hasMany(StockMovementLine::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query;
    }

    public function stockForLocation(?int $locationId = null): float
    {
        $stock = 0.0;

        foreach ($this->movementLines()->with('movement')->get() as $line) {
            $movement = $line->movement;

            if (! $movement) {
                continue;
            }

            $quantity = (float) $line->quantity;

            if ($locationId !== null) {
                if ($movement->isIncomingFor($locationId)) {
                    $stock += $quantity;
                }

                if ($movement->isOutgoingFor($locationId)) {
                    $stock -= $quantity;
                }

                continue;
            }

            $stock += $movement->globalQuantityEffect($quantity);
        }

        return $stock;
    }

    public function getCurrentStockAttribute(): float
    {
        return $this->stockForLocation();
    }

    public function getStockStatusAttribute(): string
    {
        $stock = $this->current_stock;

        if ($stock < 0) {
            return 'Negative';
        }

        if ($stock == 0.0) {
            return 'Empty';
        }

        if ($stock <= (float) $this->minimum_stock) {
            return 'Low Stock';
        }

        return 'In Stock';
    }

    public function getStockStatusLabelAttribute(): string
    {
        return match ($this->stock_status) {
            'Negative' => 'Stok Minus',
            'Empty' => 'Kosong',
            'Low Stock' => 'Stok Menipis',
            default => 'Stok Aman',
        };
    }

    public function getStockPerLocationAttribute(): array
    {
        $stocks = [];

        foreach ($this->movementLines()->with('movement.destinationLocation', 'movement.sourceLocation')->get() as $line) {
            $movement = $line->movement;
            if (! $movement) {
                continue;
            }

            if ($movement->destination_location_id) {
                $locName = $movement->destinationLocation?->name ?? 'Unknown';
                $stocks[$locName] = ($stocks[$locName] ?? 0.0) + (float) $line->quantity;
            }

            if ($movement->source_location_id) {
                $locName = $movement->sourceLocation?->name ?? 'Unknown';
                $stocks[$locName] = ($stocks[$locName] ?? 0.0) - (float) $line->quantity;
            }
        }

        // Filter out zero stocks
        return array_filter($stocks, fn($qty) => round($qty, 3) != 0);
    }
}
