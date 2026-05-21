<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Models\MediaVariation;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Illuminate\Support\Facades\Storage;

class MediaInfoSchema
{
    public static function make(): Section
    {
        return Section::make('Aperçu & Fichiers')
            ->schema([
                ViewEntry::make('preview')
                    ->view('filament.infolists.components.media-preview')
                    ->visible(fn ($record) => $record && filled($record->file_path) && Storage::disk('original_medias')->exists($record->file_path))
                    ->columnSpanFull(),

                RepeatableEntry::make('mediaVariations')
                    ->poll('10s')
                    ->label('Fichiers & Variations')
                    ->hidden(fn ($record) => ! $record || $record->mediaVariations->isEmpty())
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('profile_name')
                                    ->label('Profil'),
                                TextEntry::make('type')
                                    ->label('Type'),
                                TextEntry::make('mime_type')
                                    ->label('Mime Type'),
                            ]),
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('file_size')
                                    ->label('Taille totale')
                                    ->formatStateUsing(fn ($state) => $state ? \Illuminate\Support\Number::fileSize($state) : '-'),
                                TextEntry::make('is_streaming')
                                    ->label('Streaming')
                                    ->formatStateUsing(fn (bool $state): string => $state ? 'Oui' : 'Non'),
                                TextEntry::make('action')
                                    ->label('Action')
                                    ->state('Ouvrir/Télécharger')
                                    ->badge()
                                    ->color('primary')
                                    ->url(fn (MediaVariation $record): ?string => match (true) {
                                        $record->is_streaming => route('media.master', ['code' => $record->item->code]),
                                        $record->profile_name === 'original' => route('media.master', ['code' => $record->item->code]),
                                        $record->profile_name === 'waveform_json' => route('media.waveform', ['code' => $record->item->code]),
                                        default => null,
                                    })
                                    ->visible(fn (MediaVariation $record): bool => match (true) {
                                        $record->is_streaming => true,
                                        $record->profile_name === 'original' => true,
                                        $record->profile_name === 'waveform_json' => true,
                                        default => false,
                                    })
                                    ->openUrlInNewTab(),
                            ]),
                    ])
                    ->columns(1),

                TextEntry::make('no_mediaVariations')
                    ->label('Fichiers & Variations')
                    ->default('Aucune variation disponible.')
                    ->visible(fn ($record) => ! $record || $record->mediaVariations->isEmpty())
                    ->columnSpanFull(),
            ]);
    }
}
