<?php

namespace App\Filament\Resources\Fonds\Pages;

use App\Filament\Infolists\Components\FondsStatsAndLogs;
use App\Filament\Resources\Fonds\FondResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class ViewFond extends ViewRecord
{
    protected static string $resource = FondResource::class;

    public function getRelationManagers(): array
    {
        return [
            \App\Filament\Resources\Fonds\RelationManagers\CorpusesRelationManager::class,
            \App\Filament\Resources\MediaAssocies\RelationManagers\SubItemsRelationManager::class,
            AuditsRelationManager::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Éditer')
                ->icon('heroicon-o-pencil'),

            Action::make('viewHierarchy')
                ->label('Voir Hiérarchie')
                ->icon('heroicon-o-folder')
                ->color('info')
                ->url(fn () => route('filament.mms-admin.pages.hierarchy-explorer', [
                    'focus' => 'fond',
                    'id' => $this->record->id,
                ])),
        ];
    }

    public function infolist(Schema $chema): Schema
    {
        return $chema
            ->schema([
                // En-tête avec icône et breadcrumb
                Section::make()
                    ->schema([
                        Flex::make([
                            Grid::make(2)
                                ->schema([
                                    TextEntry::make('code')
                                        ->label('Cote')
                                        // ->icon('heroicon-o-archive-box-arrow-down')
                                        ->iconColor('gray')
                                        ->icon(Heroicon::OutlinedClipboardDocument)
                                        ->iconPosition(IconPosition::After)
                                        ->copyable()
                                        ->copyMessage('Code copié!')
                                        ->size(TextSize::Large)
                                        ->weight('bold'),

                                    TextEntry::make('title')
                                        ->label('Titre')
                                        ->placeholder('Aucun titre défini')
                                        // ->icon('heroicon-o-tag')
                                        ->size(TextSize::Large),
                                ]),

                            Grid::make(2)
                                ->schema([
                                    TextEntry::make('creator.name')
                                        ->label('Créé par')
                                        ->icon('heroicon-o-user')
                                        ->badge(),

                                    TextEntry::make('created_at')
                                        ->label('Créé le')
                                        ->icon('heroicon-o-calendar')
                                        ->dateTime('d/m/Y à H:i')
                                        ->since()
                                        ->tooltip(fn ($state) => $state?->format('d/m/Y à H:i:s')),
                                ]),
                        ]),
                    ])
                    ->compact()
                    ->columnSpanFull(),

                // Statistiques en cartes
                Section::make('Statistiques')
                    ->description('Vue d\'ensemble du contenu du fonds')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                FondsStatsAndLogs::make('id')
                                    ->hiddenLabel()
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }

    protected function formatFileSize(?int $bytes): string
    {
        if (! $bytes) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        $size = $bytes / pow(1024, $power);

        return round($size, 2).' '.$units[$power];
    }

    protected function getStatusColor(): string
    {
        $itemsCount = $this->record->items()->count();
        $corpusesCount = $this->record->corpuses()->count();

        if ($itemsCount > 0 || $corpusesCount > 0) {
            return 'success';
        }

        return 'warning';
    }

    protected function getStatusLabel(): string
    {
        $itemsCount = $this->record->items()->count();
        $corpusesCount = $this->record->corpuses()->count();

        if ($itemsCount > 0 && $corpusesCount > 0) {
            return 'Actif';
        }

        if ($corpusesCount > 0) {
            return 'En cours';
        }

        return 'Vide';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Widgets optionnels pour la page
        ];
    }
}
