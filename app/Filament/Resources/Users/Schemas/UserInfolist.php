<?php

namespace App\Filament\Resources\Users\Schemas;


use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations utilisateur')
                    ->description('Détails du compte et accès')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name'),
                        TextEntry::make('email')
                            ->label('Email address'),
                        TextEntry::make('role')
                            ->badge(),
                        IconEntry::make('admin_access')
                            ->label('Accès administration')
                            ->boolean(),
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->label('Créé le'),
                        TextEntry::make('updated_at')
                            ->dateTime()
                            ->label('Modifié le'),
                    ]),
            ]);
    }
}
