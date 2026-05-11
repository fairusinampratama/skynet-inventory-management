<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Services\ItemCodeGenerator;
use Illuminate\Console\Command;

class GenerateMissingItemCodes extends Command
{
    protected $signature = 'items:generate-missing-codes';

    protected $description = 'Generate item codes for items that do not have one yet.';

    public function handle(ItemCodeGenerator $generator): int
    {
        $updated = 0;

        Item::query()
            ->with('category')
            ->where(fn ($query) => $query->whereNull('code')->orWhere('code', ''))
            ->orderBy('id')
            ->each(function (Item $item) use ($generator, &$updated): void {
                $item->code = $generator->generate($item->category);
                $item->save();
                $updated++;
            });

        $this->info("Generated codes for {$updated} item(s).");

        return self::SUCCESS;
    }
}
