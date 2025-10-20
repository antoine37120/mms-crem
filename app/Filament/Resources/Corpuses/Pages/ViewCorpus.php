<?php

namespace App\Filament\Resources\Corpuses\Pages;


use App\Filament\Resources\Corpuses\CorpusResource;
use App\Filament\Resources\Collections\CollectionResource;
use App\Filament\Resources\Fonds\FondResource;

use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists;
use Illuminate\Support\HtmlString;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Support\Enums\IconPosition;
use App\Filament\Infolists\Components\CorpusStatsAndLogs;
use Filament\Support\Enums\TextSize;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class ViewCorpus extends ViewRecord
{
    protected static string $resource = CorpusResource::class;

    public function getRelationManagers(): array
    {
        return [
            \App\Filament\Resources\Corpuses\RelationManagers\CollectionsRelationManager::class,
            \App\Filament\Resources\Items\RelationManagers\SubItemsRelationManager::class,
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
                    'focus' => 'corpus',
                    'id' => $this->record->id
                ])),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // Breadcrumb hiérarchique
                Section::make()
                    ->schema([
                        TextEntry::make('id')
                            ->hiddenLabel()
                            ->formatStateUsing(function ($record) { if ($record->fonds->isEmpty()) { return 'Aucun fonds associé'; }
                                $fondsLinks = $record->fonds->map(function ($fond) {
                                    return '<a href="' . FondResource::getUrl('view', ['record' => $fond->id]) . '" class="text-gray-600 hover:text-primary-800 font-medium underline decoration-dotted">' .
                                        $fond->code . '</a>';
                                })->implode(' | ');

                                return new HtmlString($fondsLinks);
                            })
                            ->size(TextSize::Medium)
                            ->columnSpanFull(),
                    ])
                    ->compact()
                    ->columnSpanFull(),

                // En-tête avec icône et informations principales
                Section::make()
                    ->schema([
                        Flex::make([
                            Grid::make(2)
                                ->schema([
                                    TextEntry::make('code')
                                        ->label('Code du Corpus')
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
                                        ->icon('heroicon-o-tag')
                                        ->size(TextSize::Large),
                                ]),

                            Grid::make(3)
                                ->schema([
                                    TextEntry::make('fond.code')
                                        ->label('Fonds parent')
                                        ->icon('heroicon-o-building-library')
                                        ->badge()
                                        ->color('primary')
                                        ->formatStateUsing(function ($record) {
                                            if ($record->fonds->isEmpty()) {
                                                return 'Aucun fond associé';
                                            }

                                            $fonds = $record->fonds->map(function ($fond) {
                                                return '
                                                <a href="' . FondResource::getUrl('view', ['record' => $fond->id]) . '" class="text-primary-600 hover:text-primary-800 underline">
                                                <div class="fi-in-text-item  fi-in-text-has-badges fi-wrapped  fi-in-text">
                                                <span class="fi-color fi-color-primary fi-text-color-600 dark:fi-text-color-200 fi-badge fi-size-sm">' .
                                                    $fond->code . '</span></div></a>';
                                            })->implode('');

                                            return new HtmlString($fonds);
                                        })
                                        ->openUrlInNewTab(false),

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
                        ])
                    ])
                    ->compact()
                    ->columnSpanFull(),

                // Statistiques en cartes
                Section::make('Statistiques')
                    ->description('Vue d\'ensemble du contenu du corpus')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                CorpusStatsAndLogs::make('id')
                                    ->hiddenLabel()
                                    ->columnSpanFull()
                            ])
                    ])
                    ->collapsible()
                    ->columnSpanFull(),

            ]);
    }

    protected function formatFileSize(?int $bytes): string
    {
        if (!$bytes) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        $size = $bytes / pow(1024, $power);
        return round($size, 2) . ' ' . $units[$power];
    }

    protected function getStatusColor(): string
    {
        $itemsCount = $this->record->items()->count();
        $collectionsCount = $this->record->collections()->count();

        if ($itemsCount > 0 || $collectionsCount > 0) {
            return 'success';
        }

        return 'warning';
    }

    protected function getStatusLabel(): string
    {
        $itemsCount = $this->record->items()->count();
        $collectionsCount = $this->record->collections()->count();

        if ($itemsCount > 0 && $collectionsCount > 0) {
            return 'Actif';
        }

        if ($collectionsCount > 0) {
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
