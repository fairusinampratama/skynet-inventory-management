<?php

namespace App\Filament\Resources;

use App\Enums\MovementType;
use App\Filament\Resources\StockMovementResource\Pages;
use App\Models\StockMovement;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
            DatePicker::make('movement_date')->label('Tanggal')->required()->default(now()),
            Select::make('type')
                ->label('Jenis Pergerakan')
                ->options(collect(MovementType::cases())->mapWithKeys(fn (MovementType $type): array => [$type->value => $type->label()])->all())
                ->required()
                ->live(),
            Select::make('source_location_id')->label('Lokasi Asal')->relationship('sourceLocation', 'name')->searchable()->preload(),
            Select::make('destination_location_id')->label('Lokasi Tujuan')->relationship('destinationLocation', 'name')->searchable()->preload(),
            Select::make('movement_purpose_id')->label('Keperluan')->relationship('purpose', 'name')->searchable()->preload(),
            Select::make('stock_adjustment_reason_id')->label('Alasan Penyesuaian')->relationship('adjustmentReason', 'name')->searchable()->preload(),
            TextInput::make('pic')->label('PIC')->maxLength(255),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
            Repeater::make('lines')
                ->label('Detail Barang')
                ->relationship()
                ->schema([
                    Select::make('item_id')->label('Barang')->relationship('item', 'name')->searchable()->preload()->required(),
                    TextInput::make('quantity')->label('Jumlah')->numeric()->minValue(0.001)->required(),
                    Textarea::make('notes')->label('Catatan'),
                ])
                ->columns(3)
                ->minItems(1)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('movement_number')
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
}
