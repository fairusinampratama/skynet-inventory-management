<?php

namespace App\Filament\Resources;

use App\Enums\MovementType;
use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\MovementPurpose;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
            DatePicker::make('movement_date')->label('Tanggal')->required()->default(now()),
            Select::make('type')
                ->label('Jenis Pergerakan')
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

                    $purpose = $get('movement_purpose_id')
                        ? MovementPurpose::find($get('movement_purpose_id'))
                        : null;

                    if ($purpose && $purpose->type !== $type) {
                        $set('movement_purpose_id', null);
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
            Select::make('movement_purpose_id')
                ->label('Keperluan')
                ->options(function (Get $get): array {
                    $type = $get('type');
                    $type = $type instanceof MovementType ? $type->value : $type;

                    return MovementPurpose::query()
                        ->where('is_active', true)
                        ->when($type, fn (Builder $query, string $type): Builder => $query->where('type', $type))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all();
                })
                ->searchable()
                ->preload(),
            Select::make('stock_adjustment_reason_id')
                ->label('Alasan Penyesuaian')
                ->relationship('adjustmentReason', 'name')
                ->searchable()
                ->preload()
                ->visible(fn (Get $get): bool => self::movementTypeIs($get, MovementType::Adjustment))
                ->required(fn (Get $get): bool => self::movementTypeIs($get, MovementType::Adjustment)),
            TextInput::make('pic')->label('PIC')->maxLength(255),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
            Repeater::make('lines')
                ->label('Detail Barang')
                ->relationship()
                ->schema([
                    Select::make('item_id')->label('Barang')->relationship('item', 'name')->searchable()->preload()->required(),
                    TextInput::make('quantity')
                        ->label('Jumlah')
                        ->numeric()
                        ->inputMode('decimal')
                        ->step('0.001')
                        ->placeholder('0')
                        ->minValue(0.001)
                        ->formatStateUsing(fn (mixed $state): ?string => $state === null ? null : StockFormatter::format($state))
                        ->required(),
                ])
                ->columns(2)
                ->minItems(1)
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
                TextColumn::make('movement_date')->label('Tanggal')->date()->sortable(),
                TextColumn::make('type')->label('Jenis')->badge()->formatStateUsing(fn (MovementType|string $state): string => $state instanceof MovementType ? $state->label() : MovementType::from($state)->label()),
                TextColumn::make('sourceLocation.name')->label('Asal'),
                TextColumn::make('destinationLocation.name')->label('Tujuan'),
                TextColumn::make('purpose.name')->label('Keperluan'),
                TextColumn::make('pic')->label('PIC')->searchable(),
                TextColumn::make('lines_count')->counts('lines')->label('Baris'),
                TextColumn::make('created_at')->label('Dibuat')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')->label('Jenis')->options(collect(MovementType::cases())->mapWithKeys(fn (MovementType $type): array => [$type->value => $type->label()])->all()),
                SelectFilter::make('movement_purpose_id')->relationship('purpose', 'name')->label('Keperluan'),
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
