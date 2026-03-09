<?php

namespace App\Filament\Resources\Users\Schemas;


use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use App\Models\User;
use Illuminate\Support\Number;

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
                Section::make('Statistiques')
                    ->description('Nombre d\'entités créées par l\'utilisateur')
                    ->icon('heroicon-o-chart-bar')
                    ->columns(6)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('fonds_count')
                            ->label('Fonds')
                            ->getStateUsing(fn (User $record) => $record->createdFonds()->count())
                            ->badge(),
                        TextEntry::make('corpuses_count')
                            ->label('Corpus')
                            ->getStateUsing(fn (User $record) => $record->createdCorpuses()->count())
                            ->badge(),
                        TextEntry::make('collections_count')
                            ->label('Collections')
                            ->getStateUsing(fn (User $record) => $record->createdCollections()->count())
                            ->badge(),
                        TextEntry::make('items_count')
                            ->label('Items')
                            ->getStateUsing(fn (User $record) => $record->createdItems()->where('is_sub', false)->count())
                            ->badge(),
                        TextEntry::make('media_associes_count')
                            ->label('Médias associés')
                            ->getStateUsing(fn (User $record) => $record->createdItems()->where('is_sub', true)->count())
                            ->badge(),
                        TextEntry::make('total_upload_size')
                            ->label('Taille totale uploadée')
                            ->getStateUsing(fn (User $record) => Number::fileSize($record->uploadedItems()->sum('file_size') ?? 0))
                            ->badge()
                            ->color('info'),
                    ]),
            ]);
    }
}
