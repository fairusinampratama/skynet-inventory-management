<?php

namespace App\Services;

use App\Models\Item;
use App\Models\ItemCategory;

class ItemCodeGenerator
{
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

        return filled($category->code) ? str($category->code)->upper()->toString() : 'BRG';
    }
}
