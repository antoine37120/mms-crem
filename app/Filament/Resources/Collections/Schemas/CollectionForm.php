<?php

namespace App\Filament\Resources\Collections\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CollectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('corpus_id')
                    ->relationship('corpus', 'code')
                    ->required(),
                TextInput::make('code')
                    ->required(),
                TextInput::make('title')
                    ->label('Titre')
                    ->default(null),
                // Auto-remplir l'utilisateur connecté
                Hidden::make('created_by')
                    ->default(auth()->id()),
            ]);
    }
}
