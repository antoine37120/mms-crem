<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Enums\ItemProcessingStatus;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;

class MediaProcessingSchema
{
    public static function make(): Section
    {
        return Section::make('Traitements & Variations')
            ->schema([
                RepeatableEntry::make('processingStates')
                    ->label('État des traitements')
                    ->table([
                        TableColumn::make('Type'),
                        TableColumn::make('Statut'),
                        TableColumn::make('Message'),
                        TableColumn::make('Début'),
                        TableColumn::make('Fin'),
                    ])
                    ->schema([
                        TextEntry::make('process_type'),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (ItemProcessingStatus $state): string => match ($state) {
                                ItemProcessingStatus::PENDING => 'gray',
                                ItemProcessingStatus::PROCESSING => 'warning',
                                ItemProcessingStatus::COMPLETED => 'success',
                                ItemProcessingStatus::FAILED => 'danger',
                            }),
                        TextEntry::make('message'),
                        TextEntry::make('started_at')->dateTime(),
                        TextEntry::make('finished_at')->dateTime(),
                    ])
                    ->columnSpanFull(),

                RepeatableEntry::make('mediaVariations')
                    ->label('Fichiers générés')
                    ->table([
                        //TableColumn::make('Profil'),
                        //TableColumn::make('Type'),
                        TableColumn::make('Mime'),
                        //TableColumn::make('Streaming'),
                        TableColumn::make('Chemin'),
                        //TableColumn::make('Disque'),
                        TableColumn::make('Créé le'),
                    ])
                    ->schema([
                        //TextEntry::make('profile_name'),
                        //TextEntry::make('type'),
                        TextEntry::make('mime_type'),
                        //IconEntry::make('is_streaming')->boolean(),
                        TextEntry::make('file_path')->label('Chemin relatif'),
                        //TextEntry::make('disk'),
                        TextEntry::make('created_at')->dateTime(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
