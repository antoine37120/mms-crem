<?php

namespace App\Filament\Resources\MediaAssocies\Tables;

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

class MediaAssociesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('itemable.code')
                    ->label('Associé à')
                    ->icon(fn ($record) => match($record->itemable_type) {
                        \App\Models\Fond::class => \Filament\Support\Icons\Heroicon::OutlinedBuildingLibrary,
                        \App\Models\Corpus::class => \Filament\Support\Icons\Heroicon::OutlinedBookOpen,
                        \App\Models\Collection::class => \Filament\Support\Icons\Heroicon::OutlinedArchiveBoxArrowDown,
                        \App\Models\Item::class => \Filament\Support\Icons\Heroicon::OutlinedDocument,
                        default => null,
                    })
                    ->url(fn ($record) => match($record->itemable_type) {
                        \App\Models\Fond::class => \App\Filament\Resources\Fonds\FondResource::getUrl('view', ['record' => $record->itemable_id]),
                        \App\Models\Corpus::class => \App\Filament\Resources\Corpuses\CorpusResource::getUrl('view', ['record' => $record->itemable_id]),
                        \App\Models\Collection::class => \App\Filament\Resources\Collections\CollectionResource::getUrl('view', ['record' => $record->itemable_id]),
                        \App\Models\Item::class => \App\Filament\Resources\Items\ItemResource::getUrl('view', ['record' => $record->itemable_id]),
                        default => null,
                    })
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('code')->label('Cote')
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Copié!')
                    ->copyMessageDuration(1500)
                    ->searchable(),
                TextColumn::make('itemType.name')
                    ->label('Type de média')
                    ->wrapHeader()
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('language_code')
                    ->label('Langue')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('file_name')
                    ->label('Nom d\'origine du fichier')
                    ->wrapHeader()
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('file_type')
                    ->label('Type mime')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('file_path')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('file_size')
                    ->numeric()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('file_extension')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('duration')
                    ->numeric()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('upload_date')
                    ->date()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('uploaded_by')
                    ->numeric()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('views_count')
                    ->counts('views')
                    ->label('Vues')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('creator.name')
                    ->label('Créé par')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
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
                SelectFilter::make('itemable_type')
                    ->label('Associé à un')
                    ->options([
                        Fond::class => 'Fond',
                        Corpus::class => 'Corpus',
                        Collection::class => 'Collection',
                        Item::class => 'Item',
                    ]),
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
