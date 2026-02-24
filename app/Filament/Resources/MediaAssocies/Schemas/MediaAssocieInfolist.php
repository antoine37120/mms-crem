<?php

namespace App\Filament\Resources\MediaAssocies\Schemas;

use App\Filament\Resources\Items\Schemas\MediaInfoSchema;
use App\Filament\Resources\Items\Schemas\MediaProcessingSchema;
use App\Models\Collection;
use App\Models\Corpus;
use App\Models\Fond;
use App\Models\Item;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Number;

class MediaAssocieInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations générales')
                    //->description('Prevent abuse by limiting the number of requests per period')
                    ->schema([
                        TextEntry::make('title')
                            ->inlineLabel()
                            ->label('Titre')
                            ->default('Aucun titre'),
                        TextEntry::make('code')->label('Cote')
                            ->inlineLabel()
                            ->copyable()
                            ->copyMessage('Copié!')
                            ->copyMessageDuration(1500),
                        TextEntry::make('itemable_type')
                            ->label('Element parent')
                            ->inlineLabel()
                            ->formatStateUsing(fn ($state) => match($state) {
                                Fond::class => 'Fonds',
                                Corpus::class => 'Corpus',
                                Collection::class => 'Collection',
                                Item::class => 'Item',
                                default => $state
                            }),
                        TextEntry::make('itemable.full_code')
                            ->inlineLabel()
                            ->copyable()
                            ->copyMessage('Copié!')
                            ->copyMessageDuration(1500)
                            ->label('Code Parent'),
                        TextEntry::make('itemType.name')
                            ->inlineLabel()
                            ->label('Type d\'item'),
                        TextEntry::make('language_code')
                            ->inlineLabel(),
                        TextEntry::make('creator.name')
                            ->inlineLabel()
                            ->label('Créé par'),
                        TextEntry::make('created_at')
                            ->label('Créé le')
                            ->inlineLabel()
                            ->dateTime(),
                        TextEntry::make('updated_at')
                            ->label('Modifié le')
                            ->inlineLabel()
                            ->dateTime(),
                        TextEntry::make('deleted_at')
                            ->label('Supprimé le')
                            ->inlineLabel()
                            ->dateTime(),
                    ])
                    ->columns(1),

                Section::make('Fichier')
                    //->description('Prevent abuse by limiting the number of requests per period')
                    ->schema([
                            TextEntry::make('file_path')
                                ->inlineLabel()
                                ->label('Chemin du fichier'),
                            TextEntry::make('file_name')
                                ->inlineLabel()
                                ->label('Nom d\'origine du fichier'),
                            TextEntry::make('file_size')
                                ->formatStateUsing(fn (string $state): string =>
                                    Number::fileSize(bytes:$state, precision: 2)
                                    )
                                ->inlineLabel()
                                ->label('Taille'),
                            TextEntry::make('file_type')
                                ->inlineLabel()
                                ->label('Type mime'),
                            TextEntry::make('file_extension')
                                ->inlineLabel()
                                ->label('Extention de fichier'),
                            TextEntry::make('duration')
                                ->inlineLabel()
                                ->label('Durée')
                                ->time('H:i:s'),
                            TextEntry::make('upload_date')
                                ->inlineLabel()
                                ->label('Envoyé le')
                                ->date(),
                            TextEntry::make('uploader.name')
                                ->inlineLabel()
                                ->label('Envoyer par'),
                        ])
                    ->columns(1),
                MediaProcessingSchema::make(),
                MediaInfoSchema::make(),
            ])->columns(2);
    }
}
