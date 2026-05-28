<?php

namespace App\Filament\Resources\Collections\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CollectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('corpuses')
                    ->label('Corpus associés')
                    ->relationship('corpuses', 'code')
                    ->multiple()
                    ->default(fn () => request()->has('corpus_id') ? [request()->query('corpus_id')] : null),
                TextInput::make('code')->label('Cote')
                    ->required(),
                TextInput::make('title')
                    ->label('Titre')
                    ->default(null),
                // ->required(),
                // Auto-remplir l'utilisateur connecté
                Hidden::make('created_by')
                    ->default(auth()->id()),
                Section::make('Accès')
                    ->schema([
                        \Filament\Forms\Components\Select::make('public_access')
                            ->label('Accès public')
                            ->options(config('mms.access.options'))
                            ->default(config('mms.access.defaults.collection')),
                    ]),
            ]);
    }
}
