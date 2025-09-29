<?php

namespace App\Filament\Resources\Collections;

use App\Filament\Resources\Collections\Pages\CreateCollection;
use App\Filament\Resources\Collections\Pages\EditCollection;
use App\Filament\Resources\Collections\Pages\ListCollections;
use App\Filament\Resources\Collections\Pages\ViewCollection;
use App\Filament\Resources\Collections\Schemas\CollectionForm;
use App\Filament\Resources\Collections\Schemas\CollectionInfolist;
use App\Filament\Resources\Collections\Tables\CollectionsTable;
use App\Models\Collection;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class CollectionResource extends Resource
{
    protected static ?string $model = Collection::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBoxArrowDown;
    protected static string | UnitEnum | null $navigationGroup = 'Gestion des Archives';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'code';
    protected static ?string $navigationLabel = 'Collection';
    protected static ?string $pluralModelLabel = 'Collections';


    // Configuration des permissions par défaut
    protected static bool $shouldRegisterNavigation = true;

    public static function form(Schema $schema): Schema
    {
        return CollectionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CollectionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CollectionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
            \App\Filament\Resources\Items\RelationManagers\SubItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCollections::route('/'),
            'create' => CreateCollection::route('/create'),
            'view' => ViewCollection::route('/{record}'),
            'edit' => EditCollection::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    // Configuration des badges de navigation pour afficher des statistiques
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'primary';
    }

    // Configuration globale des requêtes pour optimiser les performances
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['corpus', 'creator']);
    }

    // Configuration des permissions basées sur les rôles (similaire aux fonds)
    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole(['documentaliste', 'administrateur']) ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasRole(['documentaliste', 'administrateur']) ||
            $record->created_by === auth()->id();
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasRole(['administrateur']) ||
            ($record->created_by === auth()->id() && $record->collections()->count() === 0);
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()?->hasRole(['administrateur']) ?? false;
    }

    // Configuration pour les actions en lot
    public static function canBulkDelete(): bool
    {
        return auth()->user()?->hasRole(['administrateur']) ?? false;
    }
}
