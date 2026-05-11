<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Enums\ItemProcessingStatus;
use App\Enums\ItemProcessingType;
use App\Jobs\GenerateAudiowaveform;
use App\Jobs\GenerateDiffusionMedia;
use App\Models\ItemProcessingState;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;

class MediaProcessingSchema
{
    public static function make(): Section
    {
        return Section::make('Traitements & Variations')
            ->schema([
                RepeatableEntry::make('processingStates')
                    ->poll('10s')
                    ->label('État des traitements')
                    ->hidden(fn ($record) => $record->processingStates->isEmpty())
                    ->table([
                        TableColumn::make('Type'),
                        TableColumn::make('Statut'),
                        TableColumn::make('Message'),
                        TableColumn::make('Début'),
                        TableColumn::make('Fin'),
                        TableColumn::make('Actions'),
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
                        Actions::make([
                            Action::make('reprocess')
                                ->label('Relancer')
                                ->icon('heroicon-m-arrow-path')
                                ->color('warning')
                                ->requiresConfirmation()
                                ->action(function (ItemProcessingState $record) {
                                    if ($record->process_type === ItemProcessingType::WAVEFORM) {
                                        GenerateAudiowaveform::dispatch($record->item);
                                        Notification::make()
                                            ->title('Génération de la waveform lancée')
                                            ->success()
                                            ->send();
                                    } elseif ($record->process_type === ItemProcessingType::DIFFUSION) {
                                        GenerateDiffusionMedia::dispatch($record->item);
                                        Notification::make()
                                            ->title('Génération du média de diffusion lancée')
                                            ->success()
                                            ->send();
                                    } else {
                                        Notification::make()
                                            ->title('Aucun job associé à ce type de traitement')
                                            ->warning()
                                            ->send();
                                    }
                                }),
                        ]),
                    ])
                    ->columnSpanFull(),

                TextEntry::make('no_processingStates')
                    ->label('État des traitements')
                    ->default('Aucun traitement en cours ou terminé.')
                    ->visible(fn ($record) => $record->processingStates->isEmpty())
                    ->columnSpanFull(),

                RepeatableEntry::make('mediaVariations')
                    ->label('Fichiers générés')
                    ->hidden(fn ($record) => $record->mediaVariations->isEmpty())
                    ->table([
                        TableColumn::make('Profil'),
                        TableColumn::make('Mime'),
                        TableColumn::make('Chemin'),
                        TableColumn::make('Créé le'),
                    ])
                    ->schema([
                        TextEntry::make('profile_name')->label('Profil'),
                        TextEntry::make('mime_type'),
                        TextEntry::make('file_path')->label('Chemin relatif'),
                        TextEntry::make('created_at')->dateTime(),
                    ])
                    ->columnSpanFull(),

                TextEntry::make('no_mediaVariations')
                    ->label('Fichiers générés')
                    ->default('Aucun fichier n\'a encore été généré.')
                    ->visible(fn ($record) => $record->mediaVariations->isEmpty())
                    ->columnSpanFull(),
            ]);
    }
}
