<?php

namespace App\Filament\Resources\Items\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
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
            ->columns([
                TextColumn::make('code')
                    ->copyable()
                    ->copyMessage('Copié!')
                    ->copyMessageDuration(1500)
                    ->searchable(),
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
                    ->counts('secondaryItems')
                    ->label('Nombre de meta items'),
                /*TextColumn::make('itemable.code')
                    ->label('Code parent')
                    ->copyable()
                    ->copyMessage('Copié!')
                    ->copyMessageDuration(1500)
                    ->searchable(),*/

                IconColumn::make('is_sub')
                    ->label('Meta item')
                    ->boolean(),
                /*TextColumn::make('itemType.name')
                    ->label('Type')
                    ->searchable(),
                TextColumn::make('language_code')
                    ->label('Langue')
                    ->searchable(),*/
                TextColumn::make('file_name')
                    ->label('Nom d\'origine du fichier')
                    ->searchable(),
                /*TextColumn::make('file_type')
                    ->label('Type mime')
                    ->searchable(),*/
                /*TextColumn::make('file_path')
                    ->searchable(),
                TextColumn::make('file_size')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('file_extension')
                    ->searchable(),
                TextColumn::make('duration')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('upload_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('uploaded_by')
                    ->numeric()
                    ->sortable(),*/
                TextColumn::make('creator.name')
                    ->label('Créé par')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
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
                SelectFilter::make('itemable_type')
                    ->options([
                        Fond::class => 'Fonds',
                        Corpus::class => 'Corpus',
                        Collection::class => 'Collection',
                        Item::class => 'Item',
                    ]),
                TernaryFilter::make('is_sub')
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
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
