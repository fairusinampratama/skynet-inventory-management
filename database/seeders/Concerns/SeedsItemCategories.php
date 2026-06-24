<?php

namespace Database\Seeders\Concerns;

use App\Models\ItemCategory;

trait SeedsItemCategories
{
    /**
     * @return array<string, string>
     */
    private function defaultItemCategoryCodes(): array
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

    /**
     * @return array<string, ItemCategory>
     */
    private function seedDefaultItemCategories(?string $description = null): array
    {
        return collect($this->defaultItemCategoryCodes())
            ->mapWithKeys(fn (string $code, string $name): array => [
                $name => ItemCategory::updateOrCreate(
                    ['name' => $name],
                    array_filter([
                        'code' => $code,
                        'description' => $description,
                    ], fn (mixed $value): bool => $value !== null),
                ),
            ])
            ->all();
    }
}
