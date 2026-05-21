<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create'),
                Select::make('role')
                    ->options(UserRole::options())
                    ->required()
                    ->default(UserRole::CHERCHEUR->value)
                    ->live(),
                Toggle::make('admin_access')
                    ->required(),
                Section::make('Périmètre d\'intervention (Documentalistes)')
                    ->description('Sélectionnez les fonds, corpus et collections sur lesquels ce documentaliste peut intervenir (inclut les enfants automatiquements).')
                    ->schema([
                        Select::make('scopedFonds')
                            ->label('Fonds autorisés')
                            ->multiple()
                            ->relationship('scopedFonds', 'code')
                            ->preload()
                            ->searchable(),
                        Select::make('scopedCorpuses')
                            ->label('Corpus autorisés')
                            ->multiple()
                            ->relationship('scopedCorpuses', 'code')
                            ->preload()
                            ->searchable(),
                        Select::make('scopedCollections')
                            ->label('Collections autorisées')
                            ->multiple()
                            ->relationship('scopedCollections', 'code')
                            ->preload()
                            ->searchable(),
                    ])
                    ->visible(fn (Get $get) => $get('role') === UserRole::DOCUMENTALISTE->value)
                    ->columns(3)
                    ->columnSpanFull(),
            ]);
    }
}
