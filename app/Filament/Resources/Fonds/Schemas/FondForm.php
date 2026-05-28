<?php

namespace App\Filament\Resources\Fonds\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FondForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')->label('Cote')
                    ->required(),
                TextInput::make('title')
                    ->default(null),
                // Auto-remplir l'utilisateur connecté
                Hidden::make('created_by')
                    ->default(auth()->id()),
                Section::make('Accès')
                    ->schema([
                        \Filament\Forms\Components\Select::make('public_access')
                            ->label('Accès public')
                            ->options(config('mms.access.options'))
                            ->default(config('mms.access.defaults.fond')),
                    ]),
            ]);
    }
}
