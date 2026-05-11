<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemCategory;

class ItemCodeGenerator
{
    /**
     * @var array<string, string>
     */
    private const PREFIXES = [
        'Distribusi' => 'DST',
        'Feeder' => 'FDR',
        'IKR/PSB' => 'IKR',
        'ONT/Router' => 'ONT',
        'Aksesoris' => 'AKS',
        'Alat' => 'ALT',
        'Bahan Habis Pakai' => 'BHP',
        'Lainnya' => 'LNY',
    ];

    public function generate(?ItemCategory $category = null): string
    {
        $prefix = $this->prefixFor($category);
        $highest = 0;

        Item::query()
            ->where('code', 'like', $prefix.'-%')
            ->pluck('code')
            ->each(function (?string $code) use ($prefix, &$highest): void {
                if (! $code || ! preg_match('/^'.preg_quote($prefix, '/').'-(\d+)$/', $code, $matches)) {
                    return;
                }

                $highest = max($highest, (int) $matches[1]);
            });

        return sprintf('%s-%04d', $prefix, $highest + 1);
    }

    public function generateForCategoryId(?int $categoryId): string
    {
        return $this->generate($categoryId ? ItemCategory::find($categoryId) : null);
    }

    private function prefixFor(?ItemCategory $category): string
    {
        if (! $category) {
            return 'BRG';
        }

        return self::PREFIXES[$category->name] ?? 'BRG';
    }
}
