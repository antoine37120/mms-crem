<?php

namespace App\Filament\Resources\ItemTypes\Schemas;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemTypeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(3)
                    ->schema([
                        Group::make()
                            ->schema([
                                Section::make('Informations principales')
                                    ->schema([
                                        TextEntry::make('name')
                                            ->label('Nom')
                                            ->weight('bold'),
                                        TextEntry::make('suffix')
                                            ->label('Suffixe'),
                                        TextEntry::make('description')
                                            ->label('Description')
                                            ->columnSpanFull()
                                            ->markdown(),
                                        TextEntry::make('allowed_extensions')
                                            ->label('Extensions autorisées')
                                            ->badge()
                                            ->getStateUsing(fn ($record) => !empty($record->allowed_extensions) ? array_filter(array_map('trim', explode(',', $record->allowed_extensions))) : [])
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ])
                            ->columnSpan(['lg' => 2]),

                        Group::make()
                            ->schema([
                                Section::make('Paramètres')
                                    ->schema([
                                        IconEntry::make('is_active')
                                            ->label('Actif')
                                            ->boolean(),
                                        IconEntry::make('requires_language')
                                            ->label('Nécessite une langue')
                                            ->boolean(),
                                    ]),

                                Section::make('Métadonnées')
                                    ->schema([
                                        TextEntry::make('creator.name')
                                            ->label('Créé par')
                                            ->icon('heroicon-m-user'),
                                        TextEntry::make('created_at')
                                            ->label('Créé le')
                                            ->dateTime()
                                            ->icon('heroicon-m-calendar'),
                                        TextEntry::make('updated_at')
                                            ->label('Modifié le')
                                            ->dateTime()
                                            ->icon('heroicon-m-clock'),
                                        TextEntry::make('deleted_at')
                                            ->label('Supprimé le')
                                            ->dateTime()
                                            ->icon('heroicon-m-trash')
                                            ->visible(fn ($record) => $record && method_exists($record, 'trashed') && $record->trashed()),
                                    ]),
                            ])
                            ->columnSpan(['lg' => 1]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
