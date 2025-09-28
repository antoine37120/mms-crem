<?php

namespace App\Filament\Resources\Corpuses\RelationManagers;

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


class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Items Directs';
    protected static ?string $modelLabel = 'item';
    protected static ?string $pluralModelLabel = 'items';

    protected static ?string $recordTitleAttribute = 'code';


    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FusedGroup::make([
                    TextInput::make('code_prefix')
                        ->label('Code de l\'Item')
                        ->autofocus(false)
                        ->default(function (RelationManager $livewire): string {
                            return $livewire->getOwnerRecord()->full_code;
                        })
                        ->required()
                        ->unique(modifyRuleUsing: function (Unique $rule, Get $get) {
                            return $rule->where('code', $get('code_prefix').$get('code_suffix'));
                        })
                        ->placeholder('Ex: CNRSMH_Arnaud_001'),
                    TextInput::make('code_suffix')
                        ->label('Suffixe')
                        ->autofocus(false)
                        ->visible(function (Get $get): bool {
                            $itemTypeId = $get('item_type_id');
                            return !empty($itemTypeId);
                        })
                        ->required(function (Get $get): bool {
                            $itemTypeId = $get('item_type_id');
                            return !empty($itemTypeId);
                        })
                        ->placeholder('Ex: _TRA_en'),
                ])->label('Code')
                    ->columns(2)->columnSpanFull(),

                TextInput::make('title')
                    ->label('Titre')
                    ->placeholder('Ex: Documentation générale')
                    ->columnSpan(2),

                Select::make('item_type_id')
                    ->label('Type d\'Item')
                    ->relationship('itemType', 'name')
                    ->placeholder('Sélectionner un type (optionnel)')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        // Réinitialiser le champ langue si le type change
                        if (!$state) {
                            $set('language_code', null);
                            $set('code_suffix', '');
                            return;
                        }

                        $itemType = ItemType::find($state);
                        if ($itemType && $itemType->suffix) {
                            $set('code_suffix', $itemType->suffix);
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
                        $itemTypeId = $get('item_type_id');
                        if (!$itemTypeId) {
                            return;
                        }

                        $itemType = ItemType::find($itemTypeId);
                        if ($itemType && $itemType->suffix) {
                            if (!$state) {
                                $set('code_suffix', $itemType->suffix);
                            } else {
                                $set('code_suffix', $itemType->suffix . '_' . $state);
                            }
                        }
                    }),

                FileUpload::make('file_path')
                    ->label('Fichier')
                    ->required()
                    ->acceptedFileTypes(['audio/*', 'video/*', 'application/pdf', 'text/plain', 'application/msword', 'image/*'])
                    ->maxSize(50 * 1024) // 50MB
                    ->storeFileNamesIn('file_name')
                    ->columnSpanFull(),

                // Champs cachés auto-remplis
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
                TextColumn::make('code')
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
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
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
