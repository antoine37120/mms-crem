<?php

namespace App\Filament\Resources\ItemTypes;

use App\Filament\Resources\ItemTypes\Pages\CreateItemType;
use App\Filament\Resources\ItemTypes\Pages\EditItemType;
use App\Filament\Resources\ItemTypes\Pages\ListItemTypes;
use App\Filament\Resources\ItemTypes\Pages\ViewItemType;
use App\Filament\Resources\ItemTypes\Schemas\ItemTypeForm;
use App\Filament\Resources\ItemTypes\Schemas\ItemTypeInfolist;
use App\Filament\Resources\ItemTypes\Tables\ItemTypesTable;
use App\Models\ItemType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class ItemTypeResource extends Resource
{
    protected static ?string $model = ItemType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Document;
    protected static string | UnitEnum | null $navigationGroup = 'Gestion des Archives';
    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ItemTypeForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ItemTypeInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ItemTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
            //AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListItemTypes::route('/'),
            'create' => CreateItemType::route('/create'),
            'view' => ViewItemType::route('/{record}'),
            'edit' => EditItemType::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
