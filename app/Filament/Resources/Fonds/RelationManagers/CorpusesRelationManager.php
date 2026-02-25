<?php

namespace App\Filament\Resources\Fonds\RelationManagers;

use App\Filament\Resources\Corpuses\CorpusResource;
use App\Models\ItemType;
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
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\FusedGroup;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rules\Unique;

class CorpusesRelationManager extends RelationManager
{
    protected static string $relationship = 'corpuses';

    //protected static ?string $relatedResource = CorpusResource::class;

    // Activer l'édition dans les pages de vue
    public function isReadOnly(): bool
    {
        return false;
    }


    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Cote')
                    ->autofocus(false)
                    ->default(function (RelationManager $livewire): string {
                        return $livewire->getOwnerRecord()->full_code ;
                    })
                    ->required()
                    //->unique(ignoreRecord: true)
                    ->unique(modifyRuleUsing: function (Unique $rule, Get $get) {
                        return $rule->where('code', $get('code'));
                    })
                    ->placeholder('Ex: CNRSMH_E_2009'),
                TextInput::make('title')
                    ->label('Titre')
                    ->placeholder('Ex: Documentation générale')
                    ->columnSpan(2),

                // Champs cachés auto-remplis
                Hidden::make('created_by')
                    ->default(auth()->id()),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->columns([
                TextColumn::make('code')->label('Cote')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Ajouté le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('Ajouté par')
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalAutofocus(false),
                AttachAction::make()
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->url(fn ($record) => route('filament.mms-admin.resources.corpuses.view', ['record' => $record])),
                    Action::make('viewInHierarchy')
                        ->label('Hiérarchie')
                        ->icon('heroicon-o-folder')
                        ->color('info')
                        ->url(fn ($record) => route('filament.mms-admin.pages.hierarchy-explorer', [
                            'focus' => 'corpus',
                            'id' => $record->id
                        ])),
                    EditAction::make(),
                    DetachAction::make(),
                    DeleteAction::make(),
                    ForceDeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                    DetachBulkAction::make(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('code')
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]));
    }
}
