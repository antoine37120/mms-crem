<?php

namespace App\Filament\Resources\Items\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Filters\TernaryFilter;
use App\Models\Item;
use App\Models\Fond;
use App\Models\Corpus;
use App\Models\Collection;

class ItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('code')->label('Cote')
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Copié!')
                    ->copyMessageDuration(1500)
                    ->searchable(),
                TextColumn::make('title')
                    ->wrap()
                    ->sortable()
                    ->label('Titre')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('itemable.code')
                    ->label('Cote parent')
                    ->url(fn ($record) => match($record->itemable_type) {
                        \App\Models\Fond::class => \App\Filament\Resources\Fonds\FondResource::getUrl('view', ['record' => $record->itemable_id]),
                        \App\Models\Corpus::class => \App\Filament\Resources\Corpuses\CorpusResource::getUrl('view', ['record' => $record->itemable_id]),
                        \App\Models\Collection::class => \App\Filament\Resources\Collections\CollectionResource::getUrl('view', ['record' => $record->itemable_id]),
                        \App\Models\Item::class => \App\Filament\Resources\Items\ItemResource::getUrl('view', ['record' => $record->itemable_id]),
                        default => null,
                    })
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                /*TextColumn::make('itemable_type')
                    ->label('Type de parent')
                    ->formatStateUsing(fn ($state) => match($state) {
                        Fond::class => 'Fonds',
                        Corpus::class => 'Corpus',
                        Collection::class => 'Collection',
                        Item::class => 'Item',
                        default => $state
                    })
                    ->searchable(),*/
                TextColumn::make('secondary_items_count')
                    ->sortable()
                    ->counts('secondaryItems')
                    ->wrapHeader()
                    ->label('Médias associés')
                    ->toggleable(isToggledHiddenByDefault: false),
                    // Hidden is_sub since we filter it via resource query
                    /*TextColumn::make('itemType.name')
                    ->label('Type')
                    ->searchable(),
                TextColumn::make('language_code')
                    ->label('Langue')
                    ->searchable(),*/
                TextColumn::make('file_name')
                    ->label('Nom d\'origine du fichier')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable(),
                TextColumn::make('file_type')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->label('Type mime')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('file_path')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('file_size')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('file_extension')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable()
                    ->searchable(),
                TextColumn::make('duration')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('upload_date')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->date()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('uploaded_by')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('views_count')
                    ->counts('views')
                    ->label('Vues')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('creator.name')
                    ->label('Créé par')
                    ->numeric()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->label('Supprimé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
