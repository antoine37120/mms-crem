<?php

namespace App\Filament\Resources\MediaClients\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TagsColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MediaClientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('app_id')
                    ->badge()
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TagsColumn::make('allowed_origins')
                    ->limit(2),
                TextColumn::make('token_ttl')
                    ->formatStateUsing(fn ($state) => ($state / 3600).' heures'),
                IconColumn::make('is_active')
                    ->boolean(),
                IconColumn::make('can_access_not_public')
                    ->label('Contenus restreints')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->filters([
                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Actifs',
                        '0' => 'Inactifs',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
