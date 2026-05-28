<?php

namespace App\Filament\Resources\Corpuses\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CorpusesTable
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
                    ->searchable(),
                TextColumn::make('public_access')
                    ->label('Accès')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'full' => 'success',
                        'restricted' => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('fonds.code')
                    ->label('Fonds')
                    ->wrap()
                    ->html()
                    ->getStateUsing(function ($record) {
                        return $record->fonds->map(function ($fond) {
                            $url = \App\Filament\Resources\Fonds\FondResource::getUrl('view', ['record' => $fond->id]);

                            return "<a href='{$url}'>{$fond->code}</a>";
                        })->implode('<br>');
                    })
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('collections_count')
                    ->sortable()
                    ->counts('collections')
                    ->label('Collections'),
                TextColumn::make('secondary_items_count')
                    ->sortable()
                    ->counts('secondaryItems')
                    ->wrapHeader()
                    ->label('Médias associés'),
                TextColumn::make('creator.name')
                    ->label('Créé par')
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
            ->defaultSort('code')
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ]),
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
