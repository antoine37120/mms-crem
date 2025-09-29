<?php

namespace App\Filament\Resources\Corpuses;

use App\Filament\Resources\Corpuses\Pages\CreateCorpus;
use App\Filament\Resources\Corpuses\Pages\EditCorpus;
use App\Filament\Resources\Corpuses\Pages\ListCorpuses;
use App\Filament\Resources\Corpuses\Pages\ViewCorpus;
use App\Filament\Resources\Corpuses\Schemas\CorpusForm;
use App\Filament\Resources\Corpuses\Schemas\CorpusInfolist;
use App\Filament\Resources\Corpuses\Tables\CorpusesTable;
use App\Models\Corpus;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class CorpusResource extends Resource
{
    protected static ?string $model = Corpus::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;
    protected static string | UnitEnum | null $navigationGroup = 'Gestion des Archives';
    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Corpus';
    protected static ?string $pluralModelLabel = 'Corpus';

    protected static ?string $recordTitleAttribute = 'code';
    // Configuration des permissions par défaut
    protected static bool $shouldRegisterNavigation = true;


    public static function form(Schema $schema): Schema
    {
        return CorpusForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CorpusInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CorpusesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            // Relations qui seront affichées dans des onglets
            RelationManagers\CollectionsRelationManager::class,
            \App\Filament\Resources\Items\RelationManagers\SubItemsRelationManager::class,

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCorpuses::route('/'),
            'create' => CreateCorpus::route('/create'),
            'view' => ViewCorpus::route('/{record}'),
            'edit' => EditCorpus::route('/{record}/edit'),
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
            ->with(['fond', 'creator']);
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
