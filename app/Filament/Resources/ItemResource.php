<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemResource\Pages;
use App\Models\Item;
use App\Services\ItemCodeGenerator;
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
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

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
            TextInput::make('name')->label('Nama')->required()->maxLength(255)->unique(ignoreRecord: true),
            Select::make('item_category_id')
                ->label('Jenis')
                ->relationship('category', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(fn (?int $state, Set $set): mixed => $set('code', app(ItemCodeGenerator::class)->generateForCategoryId($state))),
            Select::make('unit_id')->label('Satuan')->relationship('unit', 'symbol')->searchable()->preload()->required(),
            TextInput::make('price')
                ->label('Harga')
                ->inputMode('decimal')
                ->prefix('Rp')
                ->mask(RawJs::make('$money($input, ",", ".", 2)'))
                ->dehydrateStateUsing(fn (mixed $state): ?string => self::normalizeMoneyState($state))
                ->mutateStateForValidationUsing(fn (mixed $state): ?string => self::normalizeMoneyState($state))
                ->rule('numeric')
                ->step('0.01')
                ->placeholder('0,00')
                ->formatStateUsing(fn (mixed $state): ?string => $state === null ? null : number_format((float) $state, 2, ',', '.'))
                ->default('0,00')
                ->required(),
            TextInput::make('opening_balance')
                ->label('Stok Awal')
                ->numeric()
                ->inputMode('decimal')
                ->step('0.001')
                ->placeholder('0.000')
                ->formatStateUsing(fn (mixed $state): ?string => $state === null ? null : number_format((float) $state, 3, '.', ''))
                ->default('0.000')
                ->required()
                ->visibleOn('create'),
            TextEntry::make('current_stock')
                ->label('Stok Saat Ini')
                ->state(fn (?Item $record): string => number_format((float) ($record?->current_stock ?? 0), 3, '.', ''))
                ->badge()
                ->color(fn (?Item $record): string => match ($record?->stock_status) {
                    'Negative', 'Empty' => 'danger',
                    'Low Stock' => 'warning',
                    default => 'success',
                })
                ->visibleOn('edit'),
            TextInput::make('minimum_stock')
                ->label('Stok Minimum')
                ->numeric()
                ->inputMode('decimal')
                ->step('0.001')
                ->placeholder('0.000')
                ->formatStateUsing(fn (mixed $state): ?string => $state === null ? null : number_format((float) $state, 3, '.', ''))
                ->default('2.000')
                ->required(),
            Toggle::make('requires_serial_tracking')->label('Siapkan pelacakan serial')->default(false),
            Toggle::make('is_active')->label('Aktif')->default(true),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('code')->label('Kode')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('category.name')->label('Jenis')->sortable(),
                TextColumn::make('unit.symbol')->label('Satuan'),
                TextColumn::make('current_stock')->label('Stok Saat Ini')->numeric(3)->badge()
                    ->color(fn (Item $record): string => match ($record->stock_status) {
                        'Negative', 'Empty' => 'danger',
                        'Low Stock' => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('stock_status')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state, Item $record): string => $record->stock_status_label)
                    ->color(fn (string $state): string => match ($state) {
                        'Negative', 'Empty' => 'danger',
                        'Low Stock' => 'warning',
                        default => 'success',
                    }),
                TextColumn::make('minimum_stock')->label('Stok Minimum')->numeric(3)->toggleable(),
                TextColumn::make('price')->label('Harga')->money('IDR')->toggleable(),
                IconColumn::make('requires_serial_tracking')->label('Serial')->boolean()->toggleable(),
                IconColumn::make('is_active')->label('Aktif')->boolean()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('item_category_id')->relationship('category', 'name')->label('Jenis'),
            ])
            ->headerActions([
                Action::make('exportCurrentStock')
                    ->label('Ekspor CSV')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(route('exports.current-stock'), shouldOpenInNewTab: true),
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
            'index' => Pages\ManageItems::route('/'),
        ];
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
}
