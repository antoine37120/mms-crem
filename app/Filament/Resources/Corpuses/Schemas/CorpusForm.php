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
                Select::make('fonds')
                    ->label('Fonds')
                    ->relationship('fonds', 'code')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live() // Pour la réactivité
                    ->afterStateUpdated(function ($state, callable $get, callable $set) { // Auto-suggestion du code basé sur le premier fonds sélectionné
                        if($get('code') != '') {
                            return ;
                        }
                        if (!empty($state)) { // Prendre le premier fonds sélectionné
                            $fondId = $state[0];
                            $fond = Fond::find($fondId);
                            if ($fond) { // Compter les corpus existants pour suggérer le prochain numéro
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
                    ->label('Titre')
                    ->default(null),
                // Auto-remplir l'utilisateur connecté
                Hidden::make('created_by')
                    ->default(auth()->id()),

            ])
            ->columns(2);
    }
}
