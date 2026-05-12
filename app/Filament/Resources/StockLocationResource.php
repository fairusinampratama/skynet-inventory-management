<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockLocationResource\Pages;
use App\Models\StockLocation;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockLocationResource extends Resource
{
    protected static ?string $model = StockLocation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan Admin';

    protected static ?string $modelLabel = 'Lokasi Stok';

    protected static ?string $pluralModelLabel = 'Lokasi Stok';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('code')->label('Kode')->maxLength(255)->unique(ignoreRecord: true),
            Select::make('type')->label('Jenis')->options(self::typeOptions())->default('warehouse')->required(),
            Toggle::make('is_active')->label('Aktif')->default(true),
            Textarea::make('notes')->label('Catatan')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('code')->label('Kode')->searchable(),
                TextColumn::make('type')->label('Jenis')->badge()
                    ->formatStateUsing(fn (string $state): string => self::typeOptions()[$state] ?? $state),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageStockLocations::route('/')];
    }

    /**
     * @return array<string, string>
     */
    private static function typeOptions(): array
    {
        return [
            'warehouse' => 'Gudang',
            'branch' => 'Cabang',
            'field' => 'Lapangan / Teknisi',
        ];
    }
}
