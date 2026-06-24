<?php

namespace App\Filament\Resources;

use App\Enums\MovementType;
use App\Filament\Resources\StockMovementResource\Pages;

use App\Models\StockMovement;
use App\Support\StockFormatter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class StockMovementResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static ?string $modelLabel = 'Pergerakan Stok';

    protected static ?string $pluralModelLabel = 'Pergerakan Stok';

    protected static ?string $recordTitleAttribute = 'movement_number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('movement_number')
                ->label('Nomor')
                ->default(fn (): string => StockMovement::nextMovementNumber())
                ->disabled()
                ->dehydrated()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            DatePicker::make('movement_date')->label('Tanggal')->helperText('Tanggal aktual pergerakan barang.')->required()->default(now()),
            Select::make('type')
                ->label('Jenis Pergerakan')
                ->helperText('Pilih Barang Masuk jika stok bertambah, atau Barang Keluar jika stok berkurang.')
                ->options(collect(MovementType::cases())->mapWithKeys(fn (MovementType $type): array => [$type->value => $type->label()])->all())
                ->required()
                ->live()
                ->afterStateUpdated(function (MovementType|string|null $state, Get $get, Set $set): void {
                    $type = $state instanceof MovementType ? $state->value : $state;

                    if ($type === MovementType::Adjustment->value) {
                        $set('adjustment_direction', null);
                        $set('source_location_id', null);
                        $set('destination_location_id', null);
                    }

                    if (! in_array($type, [MovementType::StockOut->value, MovementType::Transfer->value, MovementType::Adjustment->value], true)) {
                        $set('source_location_id', null);
                    }

                    if (! in_array($type, [MovementType::StockIn->value, MovementType::Transfer->value, MovementType::Adjustment->value], true)) {
                        $set('destination_location_id', null);
                    }

                    if ($type !== MovementType::Adjustment->value) {
                        $set('stock_adjustment_reason_id', null);
                    }


                }),
            Select::make('adjustment_direction')
                ->label('Tipe Penyesuaian')
                ->options([
                    'increase' => 'Tambah Stok',
                    'decrease' => 'Kurangi Stok',
                ])
                ->live()
                ->dehydrated(false)
                ->afterStateHydrated(function (Select $component, ?StockMovement $record): void {
                    if (! $record || $record->type !== MovementType::Adjustment) {
                        return;
                    }

                    if ($record->destination_location_id) {
                        $component->state('increase');

                        return;
                    }

                    if ($record->source_location_id) {
                        $component->state('decrease');
                    }
                })
                ->afterStateUpdated(function (?string $state, Set $set): void {
                    if ($state === 'increase') {
                        $set('source_location_id', null);
                    }

                    if ($state === 'decrease') {
                        $set('destination_location_id', null);
                    }
                })
                ->visible(fn (Get $get): bool => self::movementTypeIs($get, MovementType::Adjustment))
                ->required(fn (Get $get): bool => self::movementTypeIs($get, MovementType::Adjustment)),
            Select::make('source_location_id')
                ->label('Lokasi Asal')
                ->helperText('Gudang atau lokasi asal barang diambil.')
                ->relationship('sourceLocation', 'name')
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                    if (filled($state) && self::movementTypeIs($get, MovementType::Adjustment)) {
                        $set('destination_location_id', null);
                    }
                })
                ->visible(fn (Get $get): bool => self::movementTypeIs($get, MovementType::StockOut, MovementType::Transfer) || self::adjustmentDirectionIs($get, 'decrease'))
                ->required(fn (Get $get): bool => self::movementTypeIs($get, MovementType::StockOut, MovementType::Transfer) || self::adjustmentDirectionIs($get, 'decrease'))
                ->rule('prohibits:destination_location_id', fn (Get $get): bool => self::movementTypeIs($get, MovementType::Adjustment) && filled($get('source_location_id'))),
            Select::make('destination_location_id')
                ->label('Lokasi Tujuan')
                ->helperText('Gudang atau lokasi tujuan barang diletakkan.')
                ->relationship('destinationLocation', 'name')
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(function (mixed $state, Get $get, Set $set): void {
                    if (filled($state) && self::movementTypeIs($get, MovementType::Adjustment)) {
                        $set('source_location_id', null);
                    }
                })
                ->visible(fn (Get $get): bool => self::movementTypeIs($get, MovementType::StockIn, MovementType::Transfer) || self::adjustmentDirectionIs($get, 'increase'))
                ->required(fn (Get $get): bool => self::movementTypeIs($get, MovementType::StockIn, MovementType::Transfer) || self::adjustmentDirectionIs($get, 'increase'))
                ->rule('prohibits:source_location_id', fn (Get $get): bool => self::movementTypeIs($get, MovementType::Adjustment) && filled($get('destination_location_id'))),
            Select::make('stock_adjustment_reason_id')
                ->label('Alasan Penyesuaian')
                ->helperText('Pilih alasan jika ini adalah koreksi stok.')
                ->options(fn (): array => \App\Models\StockAdjustmentReason::query()
                    ->orderBy('name')
                    ->get()
                    ->groupBy('type')
                    ->map(fn ($items) => $items->pluck('name', 'id'))
                    ->toArray())
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => self::movementTypeIs($get, MovementType::Adjustment))
                ->required(fn (Get $get): bool => self::movementTypeIs($get, MovementType::Adjustment)),
            TextInput::make('pic')->label('PIC')->helperText('Penanggung jawab pergerakan (opsional).')->maxLength(255),
            Textarea::make('notes')->label('Catatan')->helperText('Detail tambahan tentang pergerakan ini.')->columnSpanFull(),
            Repeater::make('lines')
                ->label('Detail Barang')
                ->relationship()
                ->schema([
                    Select::make('item_id')
                        ->label('Barang')
                        ->helperText('Pilih barang.')
                        ->relationship(
                            name: 'item',
                            titleAttribute: 'name',
                            modifyQueryUsing: function (\Illuminate\Database\Eloquent\Builder $query, Get $get) {
                                $type = $get('../../type');
                                $sourceLocationId = $get('../../source_location_id');
                                $direction = $get('../../adjustment_direction');

                                $needsStock = ($type === \App\Enums\MovementType::StockOut->value || $type === \App\Enums\MovementType::Transfer->value) ||
                                              ($type === \App\Enums\MovementType::Adjustment->value && $direction === 'decrease');

                                if ($needsStock && $sourceLocationId) {
                                    $query->whereRaw("
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
                                    ", [$sourceLocationId, $sourceLocationId]);
                                }
                            }
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),
                    TextInput::make('quantity')
                        ->label('Jumlah')
                        ->helperText('Banyaknya barang.')
                        ->suffix(function (Get $get) {
                            $itemId = $get('item_id');
                            if (!$itemId) return null;
                            return \App\Models\Item::with('unit')->find($itemId)?->unit?->symbol;
                        })
                        ->hint(function (Get $get) {
                            $itemId = $get('item_id');
                            if (!$itemId) return null;

                            $sourceLocationId = $get('../../source_location_id');
                            $destinationLocationId = $get('../../destination_location_id');
                            $type = $get('../../type');
                            
                            $locationId = $sourceLocationId ?? $destinationLocationId;
                            
                            $item = \App\Models\Item::with('unit')->find($itemId);
                            if (!$item) return null;
                            
                            $unit = $item->unit?->symbol ?? '';
                            $unitText = $unit ? " {$unit}" : '';

                            if (!$locationId) {
                                $globalStock = $item->current_stock ?? 0;
                                return 'Total Stok (Semua Lokasi): ' . \App\Support\StockFormatter::format($globalStock) . $unitText;
                            }
                            
                            $stock = $item->stockForLocation($locationId) ?? 0;
                            
                            if ($type === \App\Enums\MovementType::StockIn->value || $locationId === $destinationLocationId) {
                                return 'Stok Saat Ini (Tujuan): ' . \App\Support\StockFormatter::format($stock) . $unitText;
                            }
                                
                            return 'Sisa Stok (Asal): ' . \App\Support\StockFormatter::format($stock) . $unitText;
                        })
                        ->rules([
                            function (Get $get) {
                                return function (string $attribute, $value, \Closure $fail) use ($get) {
                                    $itemId = $get('item_id');
                                    $sourceLocationId = $get('../../source_location_id');
                                    $type = $get('../../type');
                                    $direction = $get('../../adjustment_direction');
                                    
                                    $needsStock = in_array($type, [\App\Enums\MovementType::StockOut->value, \App\Enums\MovementType::Transfer->value]) || 
                                                  ($type === \App\Enums\MovementType::Adjustment->value && $direction === 'decrease');

                                    if ($needsStock && $sourceLocationId && $itemId) {
                                        $item = \App\Models\Item::with('unit')->find($itemId);
                                        $availableStock = $item?->stockForLocation($sourceLocationId) ?? 0;
                                        $unit = $item?->unit?->symbol ?? '';
                                        $unitText = $unit ? " {$unit}" : '';
                                            
                                        if ((float) $value > (float) $availableStock) {
                                            $fail('Jumlah melebihi sisa stok yang tersedia di lokasi asal (Sisa: ' . StockFormatter::format($availableStock) . $unitText . ').');
                                        }
                                    }
                                };
                            },
                        ])
                        ->numeric()
                        ->inputMode('decimal')
                        ->step('0.001')
                        ->placeholder('0')
                        ->minValue(0.001)
                        ->formatStateUsing(fn (mixed $state): ?string => $state === null ? null : StockFormatter::format($state))
                        ->required()
                        ->live(debounce: 500),
                ])
                ->columns(2)
                ->minItems(1)
                ->rules([
                    function () {
                        return function (string $attribute, $value, \Closure $fail) {
                            $itemIds = collect($value)->pluck('item_id')->filter()->values();
                            if ($itemIds->count() !== $itemIds->unique()->count()) {
                                $fail('Terdapat barang yang sama lebih dari sekali. Silakan gabungkan jumlahnya dalam satu baris.');
                            }
                        };
                    },
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('movement_number')
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->orderByDesc('id'))
            ->columns([
                TextColumn::make('movement_number')->label('Nomor')->searchable()->sortable(),
                TextColumn::make('movement_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->tooltip(fn (StockMovement $record): string => $record->movement_date?->format('d F Y'))
                    ->sortable(),
                TextColumn::make('type')->label('Jenis')->badge()->formatStateUsing(fn (MovementType|string $state): string => $state instanceof MovementType ? $state->label() : MovementType::from($state)->label()),
                TextColumn::make('location_summary')
                    ->label('Lokasi')
                    ->state(function (StockMovement $record): string {
                        $source = $record->sourceLocation?->name;
                        $dest = $record->destinationLocation?->name;
                        if ($source && $dest) return "{$source} → {$dest}";
                        if ($source) return "↑ {$source}";
                        if ($dest) return "↓ {$dest}";
                        return '-';
                    }),
                TextColumn::make('pic')->label('PIC')->searchable(),
                TextColumn::make('lines_count')->counts('lines')->label('Jumlah Barang'),
                TextColumn::make('notes')->label('Catatan')->limit(40)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('Dibuat')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options(collect(MovementType::cases())->mapWithKeys(fn (MovementType $type): array => [$type->value => $type->label()])->all()),
                Filter::make('movement_date')
                    ->label('Rentang Tanggal')
                    ->form([
                        DatePicker::make('from')->label('Dari Tanggal')->displayFormat('d/m/Y'),
                        DatePicker::make('until')->label('Sampai Tanggal')->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('movement_date', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('movement_date', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators[] = 'Dari: ' . Carbon::parse($data['from'])->format('d M Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators[] = 'Sampai: ' . Carbon::parse($data['until'])->format('d M Y');
                        }
                        return $indicators;
                    }),
                SelectFilter::make('location')
                    ->label('Gudang / Lokasi')
                    ->options(fn (): array => \App\Models\StockLocation::orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $locationId = $data['value'] ?? null;
                        if (! $locationId) return $query;
                        return $query->where(function (Builder $q) use ($locationId) {
                            $q->where('source_location_id', $locationId)
                              ->orWhere('destination_location_id', $locationId);
                        });
                    })
                    ->searchable()
                    ->preload(),
                SelectFilter::make('item')
                    ->label('Barang')
                    ->options(fn (): array => \App\Models\Item::orderBy('name')->pluck('name', 'id')->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $itemId = $data['value'] ?? null;
                        if (! $itemId) return $query;
                        return $query->whereHas('lines', fn (Builder $q) => $q->where('item_id', $itemId));
                    })
                    ->searchable()
                    ->preload(),
                SelectFilter::make('pic')
                    ->label('PIC')
                    ->options(fn (): array => StockMovement::query()
                        ->whereNotNull('pic')
                        ->distinct()
                        ->pluck('pic', 'pic')
                        ->all())
                    ->searchable(),
            ])
            ->headerActions([
                Action::make('exportMovementHistory')
                    ->label('Ekspor CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(route('exports.movement-history'), shouldOpenInNewTab: true),
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn (): bool => auth()->user()?->isAdmin() ?? false),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageStockMovements::route('/'),
        ];
    }

    private static function movementTypeIs(Get $get, MovementType ...$types): bool
    {
        $state = $get('type');
        $value = $state instanceof MovementType ? $state->value : $state;

        foreach ($types as $type) {
            if ($value === $type->value) {
                return true;
            }
        }

        return false;
    }

    private static function adjustmentDirectionIs(Get $get, string $direction): bool
    {
        return self::movementTypeIs($get, MovementType::Adjustment)
            && $get('adjustment_direction') === $direction;
    }
}
