<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemResource\Pages;
use App\Models\Item;
use App\Models\StockAdjustmentReason;
use App\Models\StockLocation;
use App\Services\ItemCodeGenerator;
use App\Services\StockAdjustmentService;
use App\Support\StockFormatter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $modelLabel = 'Barang';

    protected static ?string $pluralModelLabel = 'Barang';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label('Kode')
                ->helperText('Dibuat otomatis oleh sistem.')
                ->default(fn (): string => app(ItemCodeGenerator::class)->generateForCategoryId(null))
                ->disabled()
                ->dehydrated()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('name')->label('Nama')->helperText('Nama lengkap barang yang akan disimpan.')->required()->maxLength(255)->unique(ignoreRecord: true),
            Select::make('item_category_id')
                ->label('Kategori')
                ->helperText('Pilih kategori pengelompokan barang.')
                ->relationship('category', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->createOptionForm([
                    TextInput::make('name')->label('Nama')->required(),
                    TextInput::make('code')->label('Kode'),
                    Textarea::make('description')->label('Deskripsi'),
                ])
                ->live()
                ->afterStateUpdated(fn (?int $state, Set $set): mixed => $set('code', app(ItemCodeGenerator::class)->generateForCategoryId($state))),
            Select::make('unit_id')
                ->label('Satuan')
                ->helperText('Satuan ukur barang (misal: Pcs, Roll, Meter).')
                ->relationship('unit', 'symbol')
                ->searchable()
                ->preload()
                ->required()
                ->createOptionForm([
                    TextInput::make('name')->label('Nama Satuan')->required(),
                    TextInput::make('symbol')->label('Simbol')->required(),
                ]),
            TextInput::make('price')
                ->label('Harga')
                ->helperText('Harga acuan untuk satu satuan barang.')
                ->inputMode('decimal')
                ->prefix('Rp')
                ->mask(RawJs::make('$money($input, \',\', \'.\', 2)'))
                ->dehydrateStateUsing(fn (mixed $state): ?string => self::normalizeMoneyState($state))
                ->mutateStateForValidationUsing(fn (mixed $state): ?string => self::normalizeMoneyState($state))
                ->rule('numeric')
                ->step('0.01')
                ->placeholder('0,00')
                ->formatStateUsing(fn (mixed $state): ?string => $state === null ? null : number_format((float) $state, 2, ',', '.'))
                ->default('0,00')
                ->required(),

            \Filament\Forms\Components\Placeholder::make('current_stock')
                ->label('Total Stok')
                ->helperText('Jumlah total stok barang saat ini.')
                ->content(fn (?Item $record): string => StockFormatter::format($record?->current_stock ?? 0) . ($record?->unit ? " {$record->unit->symbol}" : ''))
                ->visibleOn('edit'),
            \Filament\Forms\Components\Placeholder::make('stock_per_location')
                ->label('Detail Stok Per Lokasi')
                ->helperText('Distribusi riil barang di seluruh lokasi (Otomatis).')
                ->content(function (?Item $record) {
                    if (!$record) return '-';
                    $stocks = $record->stock_per_location;
                    if (empty($stocks)) return 'Belum ada stok';
                    
                    $unitText = $record->unit ? " {$record->unit->symbol}" : '';
                    $html = '<ul class="list-disc pl-4 space-y-1">';
                    foreach ($stocks as $name => $qty) {
                        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
                        $html .= "<li><strong>{$safeName}</strong>: " . StockFormatter::format($qty) . $unitText . "</li>";
                    }
                    $html .= '</ul>';
                    return new \Illuminate\Support\HtmlString($html);
                })
                ->visibleOn('edit'),
            TextInput::make('minimum_stock')
                ->label('Stok Minimum')
                ->helperText('Batas peringatan untuk stok rendah. Jika stok mencapai angka ini, sistem akan menampilkan peringatan kuning.')
                ->numeric()
                ->inputMode('decimal')
                ->step('0.001')
                ->placeholder('0')
                ->formatStateUsing(fn (mixed $state): ?string => $state === null ? null : StockFormatter::format($state))
                ->default('2')
                ->required(),

            Textarea::make('notes')->label('Catatan')->helperText('Informasi tambahan lainnya tentang barang ini.')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('category.name')->label('Kategori')->sortable(),
                TextColumn::make('unit.symbol')->label('Satuan'),
                TextColumn::make('stock_per_location_list')
                    ->label('Stok Per Lokasi')
                    ->state(function (Item $record, \Livewire\Component $livewire) {
                        $locationId = data_get($livewire->tableFilters, 'location.value');
                        $unitText = $record->unit ? " {$record->unit->symbol}" : '';

                        // If a location filter is active, show only that location's stock
                        if ($locationId) {
                            $location = \App\Models\StockLocation::find($locationId);
                            $qty = $record->stockForLocation((int) $locationId);
                            return [$location?->name . ': ' . StockFormatter::format($qty) . $unitText];
                        }

                        // Otherwise show all locations
                        $stocks = $record->stock_per_location;
                        $list = [];
                        foreach ($stocks as $name => $qty) {
                            $list[] = "{$name}: " . StockFormatter::format($qty) . $unitText;
                        }
                        return empty($list) ? ['Kosong'] : $list;
                    })
                    ->listWithLineBreaks()
                    ->bulleted(),
                TextColumn::make('current_stock')
                    ->label(fn (\Livewire\Component $livewire): string => data_get($livewire->tableFilters, 'location.value')
                        ? 'Stok di Lokasi'
                        : 'Total Stok'
                    )
                    ->state(function (Item $record, \Livewire\Component $livewire): float {
                        $locationId = data_get($livewire->tableFilters, 'location.value');
                        return $locationId ? $record->stockForLocation((int) $locationId) : $record->current_stock;
                    })
                    ->formatStateUsing(fn (mixed $state, Item $record): string => StockFormatter::format($state) . ($record->unit ? " {$record->unit->symbol}" : ''))
                    ->badge()
                    ->color(function (Item $record, \Livewire\Component $livewire): string {
                        $locationId = data_get($livewire->tableFilters, 'location.value');
                        $stock = $locationId ? $record->stockForLocation((int) $locationId) : $record->current_stock;
                        if ($stock < 0) return 'danger';
                        if ($stock == 0) return 'danger';
                        if ($stock <= (float) $record->minimum_stock) return 'warning';
                        return 'success';
                    }),
                TextColumn::make('stock_status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state, Item $record): string => $record->stock_status_label)
                    ->color(fn (string $state): string => match ($state) {
                        'Negative', 'Empty' => 'danger',
                        'Low Stock' => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('minimum_stock')->label('Stok Minimum')->formatStateUsing(fn (mixed $state): string => StockFormatter::format($state))->toggleable(),
                TextColumn::make('price')->label('Harga')->money('IDR')->toggleable(),

            ])
            ->filters([
                SelectFilter::make('item_category_id')
                    ->relationship('category', 'name')
                    ->label('Kategori')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('unit_id')
                    ->relationship('unit', 'symbol')
                    ->label('Satuan')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('stock_status')
                    ->label('Status Stok')
                    ->options([
                        'negative' => 'Stok Minus',
                        'empty'    => 'Kosong',
                        'low'      => 'Stok Menipis',
                        'ok'       => 'Stok Aman',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $status = $data['value'] ?? null;
                        if (! $status) {
                            return $query;
                        }

                        $stockSql = self::currentStockSubquery();

                        return match ($status) {
                            'negative' => $query->whereRaw("({$stockSql}) < 0"),
                            'empty'    => $query->whereRaw("({$stockSql}) = 0"),
                            'low'      => $query->whereRaw("({$stockSql}) > 0 AND ({$stockSql}) <= items.minimum_stock"),
                            'ok'       => $query->whereRaw("({$stockSql}) > items.minimum_stock"),
                            default    => $query,
                        };
                    }),
                TernaryFilter::make('needs_reorder')
                    ->label('Perlu Reorder')
                    ->trueLabel('Ya (Stok ≤ Minimum)')
                    ->falseLabel('Tidak')
                    ->queries(
                        true: fn (Builder $q) => $q->whereRaw('(' . self::currentStockSubquery() . ') > 0')
                            ->whereRaw('(' . self::currentStockSubquery() . ') <= items.minimum_stock'),
                        false: fn (Builder $q) => $q->whereRaw('(' . self::currentStockSubquery() . ') > items.minimum_stock
                            OR (' . self::currentStockSubquery() . ') <= 0'),
                    ),
                SelectFilter::make('location')
                    ->label('Gudang / Lokasi')
                    ->options(fn (): array => \App\Models\StockLocation::orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $locationId = $data['value'] ?? null;
                        if (! $locationId) {
                            return $query;
                        }

                        // Only show items that have stock > 0 at this location
                        return $query->whereRaw('
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
                    })
                    ->searchable()
                    ->preload(),
            ])
            ->headerActions([
                Action::make('exportCurrentStock')
                    ->label('Ekspor CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(function (Action $action): string {
                        $livewire = $action->getTable()->getLivewire();

                        $params = [];

                        $category = $livewire->getTableFilterState('item_category_id');
                        if (! empty($category['value'])) {
                            $params['category_id'] = $category['value'];
                        }

                        $unit = $livewire->getTableFilterState('unit_id');
                        if (! empty($unit['value'])) {
                            $params['unit_id'] = $unit['value'];
                        }

                        $stockStatus = $livewire->getTableFilterState('stock_status');
                        if (! empty($stockStatus['value'])) {
                            $params['stock_status'] = $stockStatus['value'];
                        }

                        $needsReorder = $livewire->getTableFilterState('needs_reorder');
                        if (isset($needsReorder['value']) && $needsReorder['value'] !== null && $needsReorder['value'] !== '') {
                            $params['needs_reorder'] = $needsReorder['value'] ? '1' : '0';
                        }

                        $location = $livewire->getTableFilterState('location');
                        if (! empty($location['value'])) {
                            $params['location_id'] = $location['value'];
                        }

                        return route('exports.current-stock', $params);
                    }, shouldOpenInNewTab: true),
                CreateAction::make(),
            ])
            ->recordActions([
                Action::make('adjustStock')
                    ->label('Sesuaikan Stok')
                    ->modalDescription('Gunakan fitur ini untuk menyelaraskan stok fisik di lokasi dengan sistem (misal: saat opname, hilang, atau rusak).')
                    ->icon('heroicon-o-adjustments-horizontal')
                    ->modalHeading(fn (Item $record): string => "Sesuaikan Stok: {$record->name}")
                    ->modalSubmitActionLabel('Simpan Penyesuaian')
                    ->schema([
                        Select::make('stock_location_id')
                            ->label('Lokasi')
                            ->helperText('Pilih gudang atau lokasi tempat penyesuaian stok dilakukan.')
                            ->options(fn (): array => \App\Models\StockLocation::query()
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(fn (\App\Models\StockLocation $location): array => [$location->id => $location->display_name])
                                ->all())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required(),
                        \Filament\Forms\Components\Placeholder::make('current_location_stock')
                            ->label('Stok Saat Ini di Lokasi')
                            ->helperText('Jumlah stok yang tercatat di sistem saat ini untuk lokasi yang dipilih.')
                            ->content(fn ($get, Item $record): string => filled($get('stock_location_id'))
                                ? self::formatStock($record->stockForLocation((int) $get('stock_location_id')))
                                : '-'),
                        TextInput::make('actual_stock')
                            ->label('Stok Aktual')
                            ->helperText('Masukkan jumlah fisik riil barang yang baru saja Anda hitung di lokasi.')
                            ->numeric()
                            ->inputMode('decimal')
                            ->step('0.001')
                            ->minValue(0)
                            ->placeholder('0')
                            ->required(),
                        Select::make('stock_adjustment_reason_id')
                            ->label('Alasan Penyesuaian')
                            ->helperText('Pilih alasan mengapa stok ini disesuaikan (misal: audit, hilang, rusak).')
                            ->options(fn (): array => \App\Models\StockAdjustmentReason::query()
                                ->orderBy('name')
                                ->get()
                                ->groupBy('type')
                                ->map(fn ($items) => $items->pluck('name', 'id'))
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('pic')
                            ->label('PIC')
                            ->helperText('Penanggung jawab lapangan (otomatis terisi nama Anda).')
                            ->default(fn (): ?string => auth()->user()?->name)
                            ->maxLength(255),
                        Textarea::make('notes')
                            ->label('Catatan')
                            ->helperText('Keterangan opsional mengenai penyesuaian ini.')
                            ->columnSpanFull(),
                    ])
                    ->action(function (array $data, Item $record): void {
                        $locationId = (int) $data['stock_location_id'];
                        $actualStock = (float) $data['actual_stock'];
                        $currentStock = $record->stockForLocation($locationId);
                        $difference = round($actualStock - $currentStock, 3);

                        $movement = app(StockAdjustmentService::class)->adjustToActualStock(
                            item: $record,
                            locationId: $locationId,
                            actualStock: $actualStock,
                            reasonId: (int) $data['stock_adjustment_reason_id'],
                            pic: $data['pic'] ?? null,
                            notes: $data['notes'] ?? null,
                        );

                        if (! $movement) {
                            Notification::make()
                                ->title('Stok sudah sesuai')
                                ->body('Tidak ada pergerakan stok yang dibuat.')
                                ->info()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Stok berhasil disesuaikan')
                            ->body(sprintf(
                                'Stok lokasi berubah dari %s menjadi %s (%s).',
                                self::formatStock($currentStock),
                                self::formatStock($actualStock),
                                self::formatSignedStock($difference),
                            ))
                            ->success()
                            ->send();
                    }),
                EditAction::make()
                    ->modalDescription('Ubah detail informasi barang. Pastikan data barang sudah sesuai.'),
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                    ->before(function (DeleteAction $action, \App\Models\Item $record) {
                        if ($record->movementLines()->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Gagal Menghapus')
                                ->body('Barang ini tidak dapat dihapus karena sudah memiliki riwayat pergerakan stok. Penghapusan akan merusak audit trail inventaris.')
                                ->send();
                            
                            $action->cancel();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => auth()->user()?->isAdmin() ?? false)
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            $failedCount = 0;
                            foreach ($records as $record) {
                                if ($record->movementLines()->exists()) {
                                    $failedCount++;
                                } else {
                                    $record->delete();
                                }
                            }
                            
                            if ($failedCount > 0) {
                                \Filament\Notifications\Notification::make()
                                    ->warning()
                                    ->title('Penghapusan Sebagian Berhasil')
                                    ->body("{$failedCount} barang tidak dapat dihapus karena sudah memiliki riwayat pergerakan stok. Hal ini untuk melindungi integritas audit trail inventaris.")
                                    ->send();
                            } else {
                                \Filament\Notifications\Notification::make()
                                    ->success()
                                    ->title('Semua barang berhasil dihapus')
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageItems::route('/'),
        ];
    }

    private static function formatStock(float $stock): string
    {
        return StockFormatter::format($stock);
    }

    private static function formatSignedStock(float $stock): string
    {
        return StockFormatter::signed($stock);
    }

    private static function normalizeMoneyState(mixed $state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        $state = preg_replace('/[^\d,.-]/', '', (string) $state) ?? '';

        $lastComma = strrpos($state, ',');
        $lastDot = strrpos($state, '.');

        if (($lastComma !== false) && (($lastDot === false) || ($lastComma > $lastDot))) {
            $state = str_replace('.', '', $state);
            $state = str_replace(',', '.', $state);
        } else {
            $state = str_replace(',', '', $state);

            if (preg_match('/^\d{1,3}(\.\d{3})+$/', $state)) {
                $state = str_replace('.', '', $state);
            }
        }

        return number_format((float) $state, 2, '.', '');
    }

    /**
     * Reusable SQL subquery to compute the current stock of an item
     * from stock_movement_lines, used by table filters.
     */
    private static function currentStockSubquery(): string
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
}
