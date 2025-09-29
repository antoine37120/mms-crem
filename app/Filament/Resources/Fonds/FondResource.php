<?php

namespace App\Filament\Resources\Fonds;

use App\Filament\Resources\Fonds\Pages\CreateFond;
use App\Filament\Resources\Fonds\Pages\EditFond;
use App\Filament\Resources\Fonds\Pages\ListFonds;
use App\Filament\Resources\Fonds\Pages\ViewFond;
use App\Filament\Resources\Fonds\Schemas\FondForm;
use App\Filament\Resources\Fonds\Schemas\FondInfolist;
use App\Filament\Resources\Fonds\Tables\FondsTable;
use App\Models\Fond;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FondResource extends Resource
{
    protected static ?string $model = Fond::class;

    // Icône spécifique pour les fonds d'archives
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-building-library';

    // Navigation groupée et triée selon la documentation
    protected static string|null|\UnitEnum $navigationGroup = 'Gestion des Archives';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Fonds';
    protected static ?string $pluralModelLabel = 'Fonds';

    // Configuration des breadcrumbs
    protected static ?string $recordTitleAttribute = 'code';

    // Configuration des permissions par défaut
    protected static bool $shouldRegisterNavigation = true;

    public static function form(Schema $schema): Schema
    {
        return FondForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FondInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FondsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [

            // Relations qui seront affichées dans des onglets
            RelationManagers\CorpusesRelationManager::class,
            \App\Filament\Resources\Items\RelationManagers\SubItemsRelationManager::class,

        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFonds::route('/'),
            'create' => CreateFond::route('/create'),
            'view' => ViewFond::route('/{record}'),
            'edit' => EditFond::route('/{record}/edit'),
        ];
    }

    // Configuration pour gérer les soft deletes dans les relations
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
            ->withCompleteStats()
            ->with(['creator']);

    }

    // Configuration des permissions basées sur les rôles
    public static function canCreate(): bool
    {
        return auth()->user()->hasRole(['documentaliste', 'administrateur']);
    }

    public static function canEdit($record): bool
    {
        return auth()->user()->hasRole(['documentaliste', 'administrateur']) ||
            $record->created_by === auth()->id();
    }

    public static function canDelete($record): bool
    {
        return auth()->user()->hasRole(['administrateur']) ||
            ($record->created_by === auth()->id() && $record->corpuses()->count() === 0);
    }

    public static function canDeleteAny(): bool
    {
        return auth()->user()->hasRole(['administrateur']);
    }

    // Configuration pour les actions en lot
    public static function canBulkDelete(): bool
    {
        return auth()->user()->hasRole(['administrateur']);
    }
}
