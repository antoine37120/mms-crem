<?php

namespace App\Filament\Resources\MediaClients\Schemas;

use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class MediaClientForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('app_id')
                    ->unique(ignoreRecord: true)
                    ->disabled(fn ($operation) => $operation === 'edit')
                    ->required()
                    ->hint('Identifiant technique utilisé dans le token (ex: omekas)'),
                TextInput::make('name')
                    ->label('Nom du client')
                    ->required()
                    ->hint('Nom lisible (ex: Omeka S — CREM)'),
                TextInput::make('secret')
                    ->password()
                    ->label('Secret')
                    ->visibleOn('create')
                    ->dehydrated(true)
                    ->required(fn ($operation) => $operation === 'create')
                    ->suffixAction(
                        Action::make('generate')
                            ->icon('heroicon-o-arrow-path')
                            ->action(fn ($set) => $set('secret', Str::random(64)))
                    ),
                Repeater::make('allowed_origins')
                    ->label('Origines autorisées')
                    ->schema([
                        TextInput::make('origin')
                            ->label('URL')
                            ->url()
                            ->required(),
                    ])
                    ->hint('Domaines depuis lesquels les médias sont chargés. Ex: https://omekas.crem.fr'),
                Select::make('token_ttl')
                    ->label('Durée de vie du token')
                    ->options([
                        3600 => '1 heure',
                        21600 => '6 heures',
                        86400 => '24 heures',
                    ])
                    ->default(3600),
                Toggle::make('is_active')
                    ->label('Actif')
                    ->default(true),
                Toggle::make('can_access_not_public')
                    ->label('Accès aux contenus restreints')
                    ->default(true)
                    ->helperText('Si activé, ce client peut accéder aux items qui ne sont pas en accès public complet.'),
            ]);
    }
}
