<?php

namespace App\Filament\Resources\Corpuses\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;


class CorpusInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('fond.code')
                    ->label('Fonds')
                    ->numeric(),
                TextEntry::make('code')->label('Cote'),
                TextEntry::make('title')
                    ->label('Titre'),

                TextEntry::make('creator.name')
                    ->label('Créé par'),

                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('deleted_at')
                    ->dateTime(),
            ]);
    }
}
