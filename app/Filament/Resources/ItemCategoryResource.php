<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ItemCategoryResource\Pages;
use App\Models\ItemCategory;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemCategoryResource extends Resource
{
    protected static ?string $model = ItemCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan Admin';

    protected static ?string $modelLabel = 'Kategori Barang';

    protected static ?string $pluralModelLabel = 'Kategori Barang';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('code')
                ->label('Kode')
                ->helperText('Maksimal 3 karakter, dipakai sebagai prefix kode barang.')
                ->required()
                ->maxLength(3)
                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? str($state)->upper()->toString() : null)
                ->formatStateUsing(fn (?string $state): ?string => filled($state) ? str($state)->upper()->toString() : null)
                ->unique(ignoreRecord: true),
            Toggle::make('is_active')->label('Aktif')->default(true),
            Textarea::make('description')->label('Deskripsi')->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('code')->label('Kode')->badge()->searchable()->sortable(),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageItemCategories::route('/')];
    }
}
