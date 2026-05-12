<?php

namespace App\Filament\Resources;

use App\Enums\MovementType;
use App\Filament\Resources\MovementPurposeResource\Pages;
use App\Models\MovementPurpose;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MovementPurposeResource extends Resource
{
    protected static ?string $model = MovementPurpose::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan Admin';

    protected static ?string $modelLabel = 'Keperluan Pergerakan';

    protected static ?string $pluralModelLabel = 'Keperluan Pergerakan';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nama')->required()->maxLength(255)->unique(ignoreRecord: true),
            Select::make('type')->label('Jenis')->options(self::typeOptions())->required(),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('type')->label('Jenis')->badge()->formatStateUsing(fn (?string $state): string => self::typeOptions()[$state] ?? (string) $state),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ManageMovementPurposes::route('/')];
    }

    /**
     * @return array<string, string>
     */
    private static function typeOptions(): array
    {
        return collect(MovementType::cases())
            ->mapWithKeys(fn (MovementType $type): array => [$type->value => $type->label()])
            ->all();
    }
}
