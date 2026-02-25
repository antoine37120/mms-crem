<?php

namespace App\Filament\Resources\Corpuses\RelationManagers;

use App\Models\Collection;
use Filament\Forms;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CollectionsRelationManager extends RelationManager
{
    protected static string $relationship = "collections";

    protected static ?string $title = "Collections";
    protected static ?string $modelLabel = "collection";
    protected static ?string $pluralModelLabel = "collections";

    protected static ?string $recordTitleAttribute = "code";

    // Activer l'édition dans les pages de vue
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make("code")
                    ->label("Code de la Collection")
                    ->autofocus(false)
                    ->default(function (RelationManager $livewire): string {
                        $corpus = $livewire->getOwnerRecord();
                        // Suggestion basée sur le corpus parent
                        return $corpus->code . "_";
                    })
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->placeholder("Ex: CNRSMH_I_2024_001"),

                TextInput::make("title")
                    ->label("Titre")
                    ->placeholder("Ex: Cérémonies de mariage")
                    ->columnSpan(2),

                // Champs cachés auto-remplis
                Hidden::make("created_by")
                    ->default(auth()->id()),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute("code")
            ->columns([
                TextColumn::make("code")
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make("title")
                    ->searchable()
                    ->placeholder("Sans titre")
                    ->limit(50),

                TextColumn::make("items_count")
                    ->label("Items")
                    ->counts("items")
                    ->color("success"),

                TextColumn::make("created_at")
                    ->label("Créé le")
                    ->dateTime("d/m/Y H:i")
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make("creator.name")
                    ->label("Créé par")
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalAutofocus(false)
                    ->successNotificationTitle("Collection créée avec succès"),

                AttachAction::make()
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->url(fn ($record) => route('filament.mms-admin.resources.collections.view', ['record' => $record])),
                    Action::make('viewInHierarchy')
                        ->label('Hiérarchie')
                        ->icon('heroicon-o-folder')
                        ->color('info')
                        ->url(fn ($record) => route('filament.mms-admin.pages.hierarchy-explorer', [
                            'focus' => 'collection',
                            'id' => $record->id
                        ])),
                    DetachAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    ForceDeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ])
                ->withCount("items"));
    }
}
