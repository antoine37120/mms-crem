<?php

namespace App\Filament\Resources\Corpuses\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;
use App\Models\Fond;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CorpusForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('fond_id')
                    ->label('Fonds')
                    ->relationship('fond', 'code')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live() // Pour la réactivité
                    ->afterStateUpdated(function ($state, callable $set) {
                        // Auto-suggestion du code basé sur le fonds parent
                        if ($state) {
                            $fond = Fond::find($state);
                            if ($fond) {
                                // Compter les corpus existants pour suggérer le prochain numéro
                                $existingCount = $fond->corpuses()->count();
                                $nextNumber = str_pad($existingCount + 1, 3, '0', STR_PAD_LEFT);
                                $set('code', $fond->code . '_' . $nextNumber);
                            }
                        }
                    }),

                TextInput::make('code')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->placeholder('Ex: CNRSMH_Arnaud_001'),
                TextInput::make('title')
                    ->default(null),
                // Auto-remplir l'utilisateur connecté
                Hidden::make('created_by')
                    ->default(auth()->id()),

            ])
            ->columns(2);
    }
}
