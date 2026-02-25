<?php

namespace App\Filament\Resources\Items\RelationManagers;

use App\Models\ItemType;
use App\Models\Item;
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
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\FusedGroup;
use Illuminate\Validation\Rules\Unique;

use Filament\Schemas\Components\Text;

use Filament\Infolists\Components\TextEntry;

class SubItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Items';
    protected static ?string $modelLabel = 'item';
    protected static ?string $pluralModelLabel = 'items';

    protected static ?string $recordTitleAttribute = 'code';

    // Activer l'édition dans les pages de vue
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                /*Select::make('item_type_id')
                    ->label('Type d\'Item')
                    ->relationship('itemType', 'name')
                    ->placeholder('Sélectionner un type (optionnel)')
                    ->searchable()
                    ->preload()
                    ->live() // ← IMPORTANT : remplace "reactive()"
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        // Réinitialiser le champ langue si le type change
                        if (!$state) {
                            $set('language_code', null);
                        }
                        if (!$state) {
                            return ;
                        }
                        $suffix = ItemType::find($state)->suffix ;
                        $itemLang = $get('language_code');
                        if ($suffix) {
                            $set('code_suffix', '_'.$suffix);
                        } else {
                            $set('code_suffix', '_'.$state.'_'.$itemLang);
                        }
                    }),
                TextInput::make('language_code')
                    ->label('Code Langue')
                    ->placeholder('Ex: fr, en')
                    ->maxLength(5)
                    ->live()
                    ->visible(function (Get $get): bool {
                        $itemTypeId = $get('item_type_id');
                        if (!$itemTypeId) {
                            return false;
                        }
                        $itemType = ItemType::find($itemTypeId);
                        return $itemType && $itemType->requires_language;
                    })
                    ->required(function (Get $get): bool {
                        $itemTypeId = $get('item_type_id');
                        if (!$itemTypeId) {
                            return false;
                        }
                        $itemType = ItemType::find($itemTypeId);
                        return $itemType && $itemType->requires_language;
                    })
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        // Réinitialiser le champ langue si le type change
                        $itemTypeId = $get('item_type_id');
                        $itemType = ItemType::find($itemTypeId)->suffix ;
                        if($itemType) {
                            if (!$state) {
                                $set('code_suffix', $itemType);
                            } else {
                                $set('code_suffix', $itemType . '_' . $state);
                            }
                        }
                    }),*/
                FusedGroup::make([
                    TextInput::make('code_prefix')
                        ->label('Cote de l\'Item')
                        ->autofocus(false)
                        ->default(function (RelationManager $livewire): string {
                            return $livewire->getOwnerRecord()->code ;
                        })
                        ->disabled()
                        ->dehydrated()
                        ->required()
                        //->unique(ignoreRecord: true)
                        ->unique(modifyRuleUsing: function (Unique $rule, Get $get) {
                            if($get('code_suffix') != '') {
                                return $rule->where('code', $get('code_prefix').'_'.$get('code_suffix'));
                            }
                            return $rule->where('code', $get('code_prefix'));
                        })
                        ->placeholder('Ex: CNRSMH_Arnaud_001'),
                    TextInput::make('code_suffix')
                        ->label('Code de l\'Item')
                        ->prefix('_')
                        ->autofocus(false)
                        /*->visible(function (Get $get): bool {
                            $itemTypeId = $get('item_type_id');

                            if (!$itemTypeId) {
                                return false;
                            }
                            return true;
                        })*/
                        ->required(function (Get $get): bool {
                            /*$itemTypeId = $get('item_type_id');

                            if (!$itemTypeId) {
                                return false;
                            }*/
                            return true;
                        })
                        ->placeholder('Ex: TRA_en ou 02'),
                        Text::make(<<<'JS'
                            $get('code_suffix') ? `Cote enregistrée : ${$get('code_prefix')}_${$get('code_suffix')}` : `Cote enregistrée : ${$get('code_prefix')}`
                            JS)
                        ->js()
                ])->label('code')

                    /*->afterLabel(function (Get $get): string {
                        if($get('code_suffix') != '') {
                            return $get('code_prefix').'_'.$get('code_suffix') ;
                        }
                        return $get('code_prefix');
                    })*/
                    ->columns(2)->columnSpanFull(),
                TextInput::make('title')
                    ->label('Titre')
                    ->placeholder('Ex: Documentation générale')
                    ->columnSpan(2),

                TextInput::make('file_path')
                    ->label('Chemin du fichier')
                    ->disabled()
                    ->visible(fn ($record) => $record && $record->file_path),
                // Champs cachés auto-remplis
                Hidden::make('is_sub')
                    ->default(false),
                Hidden::make('created_by')
                    ->default(auth()->id()),
                Hidden::make('uploaded_by')
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
                TextColumn::make('file_size')
                    ->label('Taille')
                    ->formatStateUsing(fn ($state) => $this->formatFileSize($state))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Ajouté le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('uploader.name')
                    ->label('Ajouté par')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->modalAutofocus(false),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->url(fn ($record) => $record->is_sub 
                            ? route('filament.mms-admin.resources.media-associes.view', ['record' => $record])
                            : route('filament.mms-admin.resources.items.view', ['record' => $record])),
                    Action::make('viewInHierarchy')
                        ->label('Hiérarchie')
                        ->icon('heroicon-o-folder')
                        ->color('info')
                        ->url(fn ($record) => route('filament.mms-admin.pages.hierarchy-explorer', [
                            'focus' => 'item',
                            'id' => $record->id
                        ])),
                    EditAction::make(),
                    DeleteAction::make(),
                    ForceDeleteAction::make(),
                    RestoreAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('is_sub', false) 
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]));
    }

    private function formatFileSize(?int $bytes): string
    {
        if (!$bytes) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        $size = $bytes / pow(1024, $power);
        return round($size, 2) . ' ' . $units[$power];
    }

}
