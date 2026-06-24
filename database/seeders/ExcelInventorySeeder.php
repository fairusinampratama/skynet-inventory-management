<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Unit;
use App\Services\ItemCodeGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;

class ExcelInventorySeeder extends Seeder
{
    private const WORKBOOK = 'Stock Material Skynet NEW (1).xlsx';

    /**
     * @var array<string, mixed>
     */
    private array $summary = [];

    public function run(): void
    {
        $path = base_path(self::WORKBOOK);

        if (! is_file($path)) {
            $this->command?->warn('Excel inventory workbook not found: '.self::WORKBOOK);

            return;
        }

        DB::transaction(function () use ($path): void {
            $sheets = $this->readWorkbook($path);
            $masterRows = $sheets['Stok Material'] ?? [];

            if ($masterRows === []) {
                throw new RuntimeException('Sheet "Stok Material" is missing or empty.');
            }

            $report = $this->importMasterRows($masterRows);
            $report += $this->inspectMovementRows($sheets, $report['master_item_names']);

            unset($report['master_item_names']);

            $this->summary = $report;
        });

        $this->writeSummary();
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return $this->summary;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array<string, mixed>
     */
    private function importMasterRows(array $rows): array
    {
        $created = 0;
        $updated = 0;
        $blankCodes = 0;
        $duplicateCodes = [];
        $blankCategories = 0;
        $blankUnits = 0;
        $blankPrices = 0;
        $blankStocks = 0;
        $seenCodes = [];
        $masterItemNames = [];

        foreach ($rows as $row) {
            $name = $this->clean($row['Item name'] ?? '');

            if ($name === '') {
                continue;
            }

            $masterItemNames[] = mb_strtolower($name);

            $categoryName = $this->clean($row['Type'] ?? '');
            if ($categoryName === '') {
                $categoryName = 'Lainnya';
                $blankCategories++;
            }

            $unitSymbol = $this->normalizeUnit($row['Satuan'] ?? '');
            if ($unitSymbol === 'Pcs' && $this->clean($row['Satuan'] ?? '') === '') {
                $blankUnits++;
            }

            $price = $this->decimalOrZero($row['Price'] ?? '');
            if ($this->clean($row['Price'] ?? '') === '') {
                $blankPrices++;
            }

            $stock = $this->decimalOrZero($row['Stok Akhir'] ?? '');
            if ($this->clean($row['Stok Akhir'] ?? '') === '') {
                $blankStocks++;
            }

            $category = ItemCategory::firstOrCreate(
                ['name' => $categoryName],
                ['code' => $this->categoryCode($categoryName)],
            );

            if (! $category->code && $code = $this->categoryCode($categoryName)) {
                $category->update(['code' => $code]);
            }

            $unit = Unit::firstOrCreate(
                ['symbol' => $unitSymbol],
                ['name' => $unitSymbol],
            );

            $excelCode = $this->clean($row['Column 1'] ?? '');
            $code = $excelCode;

            if ($code === '') {
                $blankCodes++;
            } elseif (isset($seenCodes[$code]) && $seenCodes[$code] !== $name) {
                $duplicateCodes[] = $code;
                $code = '';
            }

            if ($excelCode !== '') {
                $seenCodes[$excelCode] = $name;
            }

            $item = Item::where('name', $name)->first();
            $code = $this->resolveCode($item, $code, $category->id);

            $attributes = [
                'code' => $code,
                'item_category_id' => $category->id,
                'unit_id' => $unit->id,
                'price' => $price,
                'minimum_stock' => 0,
                'notes' => $this->notesFor($row, $excelCode, $code),
            ];

            if ($item) {
                $item->update($attributes);
                $updated++;
            } else {
                $item = Item::create(['name' => $name] + $attributes);
                $created++;
            }

            if ($stock > 0) {
                $location = \App\Models\StockLocation::firstOrCreate(
                    ['name' => 'Gudang Utama'],
                    ['code' => 'MAIN', 'type' => 'warehouse']
                );
                

                $movement = \App\Models\StockMovement::create([
                    'movement_number' => \App\Models\StockMovement::nextMovementNumber() . '-INI-' . $item->id,
                    'movement_date' => now(),
                    'type' => \App\Enums\MovementType::StockIn->value,
                    'destination_location_id' => $location->id,
                    'notes' => 'Migrasi otomatis stok awal dari Excel.',
                ]);

                \App\Models\StockMovementLine::create([
                    'stock_movement_id' => $movement->id,
                    'item_id' => $item->id,
                    'quantity' => $stock,
                ]);
            } else if ($stock < 0) {
                $location = \App\Models\StockLocation::firstOrCreate(
                    ['name' => 'Gudang Utama'],
                    ['code' => 'MAIN', 'type' => 'warehouse']
                );
                

                $movement = \App\Models\StockMovement::create([
                    'movement_number' => \App\Models\StockMovement::nextMovementNumber() . '-INI-' . $item->id,
                    'movement_date' => now(),
                    'type' => \App\Enums\MovementType::StockOut->value,
                    'source_location_id' => $location->id,
                    'notes' => 'Migrasi otomatis koreksi minus dari Excel.',
                ]);

                \App\Models\StockMovementLine::create([
                    'stock_movement_id' => $movement->id,
                    'item_id' => $item->id,
                    'quantity' => abs($stock),
                ]);
            }
        }

        return [
            'master_rows' => count($rows),
            'items_created' => $created,
            'items_updated' => $updated,
            'blank_codes' => $blankCodes,
            'duplicate_codes' => array_values(array_unique($duplicateCodes)),
            'blank_categories' => $blankCategories,
            'blank_units' => $blankUnits,
            'blank_prices' => $blankPrices,
            'blank_stocks' => $blankStocks,
            'master_item_names' => array_values(array_unique($masterItemNames)),
        ];
    }

    /**
     * @param  array<string, array<int, array<string, string>>>  $sheets
     * @param  array<int, string>  $masterItemNames
     * @return array<string, mixed>
     */
    private function inspectMovementRows(array $sheets, array $masterItemNames): array
    {
        $movementRows = 0;
        $movementMaterials = [];

        foreach (($sheets['INOUT'] ?? []) as $row) {
            $material = $this->clean($row['Material'] ?? '');

            if ($material === '') {
                continue;
            }

            $movementRows++;
            $movementMaterials[] = mb_strtolower($material);
        }

        foreach (($sheets['barang masuk'] ?? []) as $row) {
            $material = $this->clean($row['Material'] ?? '');

            if ($material === '') {
                continue;
            }

            $movementRows++;
            $movementMaterials[] = mb_strtolower($material);
        }

        return [
            'movement_rows_inspected' => $movementRows,
            'unmatched_movement_materials' => Collection::make($movementMaterials)
                ->unique()
                ->reject(fn (string $material): bool => in_array($material, $masterItemNames, true))
                ->sort()
                ->values()
                ->all(),
        ];
    }

    private function resolveCode(?Item $item, string $requestedCode, int $categoryId): string
    {
        if ($item && $requestedCode !== '' && $item->code === $requestedCode) {
            return $requestedCode;
        }

        if (
            $requestedCode !== ''
            && ! Item::where('code', $requestedCode)
                ->when($item, fn ($query) => $query->whereKeyNot($item->id))
                ->exists()
        ) {
            return $requestedCode;
        }

        if ($item && filled($item->code) && ! str_starts_with((string) $item->code, 'BRG-')) {
            return (string) $item->code;
        }

        return app(ItemCodeGenerator::class)->generateForCategoryId($categoryId);
    }

    private function normalizeUnit(string $unit): string
    {
        $unit = $this->clean($unit);

        return match (mb_strtolower($unit)) {
            '', 'pcs' => 'Pcs',
            'meter' => 'Meter',
            'roll' => 'Roll',
            'pack' => 'Pack',
            default => $unit,
        };
    }

    private function categoryCode(string $category): ?string
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
        ][$category] ?? null;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function notesFor(array $row, string $excelCode, string $storedCode): ?string
    {
        $notes = [];

        if ($this->clean($row['Notes'] ?? '') !== '') {
            $notes[] = $this->clean($row['Notes']);
        }

        if ($date = $this->excelDate($row['TANGGAL'] ?? '')) {
            $notes[] = 'Tanggal Excel: '.$date;
        }

        if ($this->clean($row['Column 1 2'] ?? '') !== '') {
            $notes[] = 'Catatan Excel: '.$this->clean($row['Column 1 2']);
        }

        if ($this->clean($row['Status'] ?? '') !== '') {
            $notes[] = 'Status Excel: '.$this->clean($row['Status']);
        }

        if ($excelCode !== '' && $excelCode !== $storedCode) {
            $notes[] = 'Kode Excel: '.$excelCode;
        }

        return $notes === [] ? null : implode("\n", $notes);
    }

    private function decimalOrZero(string $value): string
    {
        $value = $this->clean($value);

        return is_numeric($value) ? (string) (float) $value : '0';
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function excelDate(string $value): ?string
    {
        $value = $this->clean($value);

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return CarbonImmutable::create(1899, 12, 30)->addDays((int) floor((float) $value))->toDateString();
    }

    /**
     * @return array<string, array<int, array<string, string>>>
     */
    private function readWorkbook(string $path): array
    {
        $sharedStrings = $this->sharedStrings($path);
        $workbook = $this->xml($path, 'xl/workbook.xml');
        $relationships = $this->relationships($path);
        $sheets = [];

        foreach ($workbook->sheets->sheet as $sheet) {
            $attributes = $sheet->attributes();
            $relationAttributes = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $name = (string) $attributes['name'];
            $relationId = (string) $relationAttributes['id'];
            $target = $relationships[$relationId] ?? null;

            if (! $target) {
                continue;
            }

            $sheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/'.ltrim($target, '/');
            $sheets[$name] = $this->readSheet($path, $sheetPath, $sharedStrings);
        }

        return $sheets;
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(string $path): array
    {
        $content = $this->readArchiveEntry($path, 'xl/sharedStrings.xml', false);

        if ($content === null) {
            return [];
        }

        $xml = $this->xmlFromString($content, 'xl/sharedStrings.xml');
        $strings = [];

        foreach ($xml->si as $string) {
            $text = '';

            foreach ($string->xpath('.//*[local-name()="t"]') ?: [] as $node) {
                $text .= (string) $node;
            }

            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @return array<string, string>
     */
    private function relationships(string $path): array
    {
        $xml = $this->xml($path, 'xl/_rels/workbook.xml.rels');
        $relationships = [];

        foreach ($xml->Relationship as $relationship) {
            $attributes = $relationship->attributes();
            $relationships[(string) $attributes['Id']] = (string) $attributes['Target'];
        }

        return $relationships;
    }

    /**
     * @param  array<int, string>  $sharedStrings
     * @return array<int, array<string, string>>
     */
    private function readSheet(string $workbookPath, string $path, array $sharedStrings): array
    {
        $xml = $this->xml($workbookPath, $path);
        $headers = [];
        $rows = [];

        foreach ($xml->sheetData->row as $row) {
            $values = [];

            foreach ($row->c as $cell) {
                $attributes = $cell->attributes();
                $column = $this->columnNumber((string) $attributes['r']);
                $type = (string) $attributes['t'];
                $raw = isset($cell->v) ? (string) $cell->v : '';

                $values[$column] = $type === 's' && is_numeric($raw)
                    ? ($sharedStrings[(int) $raw] ?? '')
                    : $raw;
            }

            if ($headers === []) {
                $headers = $values;

                continue;
            }

            $mapped = [];

            foreach ($headers as $column => $header) {
                $header = $this->clean((string) $header);

                if ($header !== '') {
                    $mapped[$header] = $this->clean((string) ($values[$column] ?? ''));
                }
            }

            if (array_filter($mapped, fn (string $value): bool => $value !== '') !== []) {
                $rows[] = $mapped;
            }
        }

        return $rows;
    }

    private function xml(string $workbookPath, string $path): SimpleXMLElement
    {
        return $this->xmlFromString($this->readArchiveEntry($workbookPath, $path), $path);
    }

    private function xmlFromString(string $content, string $path): SimpleXMLElement
    {
        $xml = simplexml_load_string($content);

        if (! $xml) {
            throw new RuntimeException('Invalid XML in workbook file: '.$path);
        }

        return $xml;
    }

    private function readArchiveEntry(string $archivePath, string $entryPath, bool $required = true): ?string
    {
        $command = sprintf('unzip -p %s %s', escapeshellarg($archivePath), escapeshellarg($entryPath));
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            if (! $required) {
                return null;
            }

            throw new RuntimeException('Missing workbook file: '.$entryPath);
        }

        return implode("\n", $output);
    }

    private function columnNumber(string $cellReference): int
    {
        preg_match('/^[A-Z]+/', $cellReference, $matches);
        $letters = $matches[0] ?? '';
        $number = 0;

        foreach (str_split($letters) as $letter) {
            $number = ($number * 26) + ord($letter) - 64;
        }

        return $number;
    }

    private function writeSummary(): void
    {
        if (! $this->command || $this->summary === []) {
            return;
        }

        $this->command->info(sprintf(
            'Excel inventory import: %d master rows, %d created, %d updated.',
            $this->summary['master_rows'],
            $this->summary['items_created'],
            $this->summary['items_updated'],
        ));

        if ($this->summary['duplicate_codes'] !== []) {
            $this->command->warn('Duplicate Excel codes: '.implode(', ', $this->summary['duplicate_codes']));
        }

        if ($this->summary['unmatched_movement_materials'] !== []) {
            $this->command->warn('Movement materials without exact master match: '.implode(', ', $this->summary['unmatched_movement_materials']));
        }
    }
}
