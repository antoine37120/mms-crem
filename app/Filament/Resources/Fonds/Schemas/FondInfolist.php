<?php

namespace App\Filament\Resources\Fonds\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class FondInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('code'),
                TextEntry::make('title'),
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
