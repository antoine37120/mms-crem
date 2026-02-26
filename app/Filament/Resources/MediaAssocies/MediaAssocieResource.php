<?php

namespace App\Filament\Resources\MediaAssocies;

use App\Filament\Pages\HierarchyExplorer;
use App\Filament\Resources\MediaAssocies\Pages\CreateMediaAssocie;
use App\Filament\Resources\MediaAssocies\Pages\EditMediaAssocie;
use App\Filament\Resources\MediaAssocies\Pages\ListMediaAssocies;
use App\Filament\Resources\MediaAssocies\Pages\ViewMediaAssocie;
use App\Filament\Resources\MediaAssocies\Schemas\MediaAssocieForm;
use App\Filament\Resources\MediaAssocies\Schemas\MediaAssocieInfolist;
use App\Filament\Resources\MediaAssocies\Tables\MediaAssociesTable;
use App\Models\Item;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class MediaAssocieResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static ?string $modelLabel = 'Média associé';
    protected static ?string $pluralModelLabel = 'Médias associés';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocument;
    protected static string | UnitEnum | null $navigationGroup = 'Médias associés';
    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return MediaAssocieForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return MediaAssocieInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MediaAssociesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
            'view' => AuditsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaAssocies::route('/'),
            'create' => CreateMediaAssocie::route('/create'),
            'view' => ViewMediaAssocie::route('/{record}'),
            'edit' => EditMediaAssocie::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->where('is_sub', true);
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGlobalSearchResultUrl(Model $record): string
    {
        return HierarchyExplorer::getUrl(['focus' => 'item', 'id' => $record->id]);
    }
}
