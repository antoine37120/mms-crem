<?php

namespace App\Filament\Resources\Collections\Pages;

use App\Filament\Infolists\Components\CollectionStatsAndLogs;
use App\Filament\Resources\Collections\CollectionResource;
use App\Filament\Resources\Corpuses\CorpusResource;
use App\Filament\Resources\fonds\FondResource;
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
use Illuminate\Support\HtmlString;

use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;
class ViewCollection extends ViewRecord
{
    protected static string $resource = CollectionResource::class;

    public function getRelationManagers(): array
    {
        return [
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
                    'focus' => 'collection',
                    'id' => $this->record->id
                ])),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // Breadcrumb hiérarchique
                /*Section::make()
                    ->schema([
                        TextEntry::make('id')
                            ->hiddenLabel()
                            ->formatStateUsing(fn ($record) => new HtmlString(
                                '< <a href="' . FondResource::getUrl('view', ['record' => $record->corpuses->fond->id]) . '" class="text-gray-600 hover:text-primary-800 font-medium underline decoration-dotted">' .
                                $record->corpuses->fond->code . '</a>'.
                                ' < <a href="' . CorpusResource::getUrl('view', ['record' => $record->corpuses->id]) . '" class="text-gray-600 hover:text-primary-800 font-medium underline decoration-dotted">' .
                                $record->corpuses->code . '</a>'
                            ))
                            ->size(TextSize::Medium)
                            ->columnSpanFull(),
                    ])
                    ->compact()
                    ->columnSpanFull(),*/

                // En-tête avec icône et informations principales
                Section::make()
                    ->schema([
                        Flex::make([
                            Grid::make(2)
                                ->schema([
                                    TextEntry::make('code')
                                        ->label('Code de la collection')
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
                                    TextEntry::make('id')
                                        ->label('Corpus associés')
                                        ///->icon('heroicon-o-book-open')
                                        //->badge()
                                        //->iconColor('primary')
                                        ->formatStateUsing(function ($record) {
                                            if ($record->corpuses->isEmpty()) {
                                                return 'Aucun corpus associé';
                                            }

                                            $corpuses = $record->corpuses->map(function ($corpus) {
                                                return '
                                                <a href="' . CorpusResource::getUrl('view', ['record' => $corpus->id]) . '" class="text-primary-600 hover:text-primary-800 underline">
                                                <div class="fi-in-text-item  fi-in-text-has-badges fi-wrapped  fi-in-text">
                                                <span class="fi-color fi-color-primary fi-text-color-600 dark:fi-text-color-200 fi-badge fi-size-sm">' .
                                                    $corpus->code . '</span></div></a>';
                                            })->implode('');

                                            return new HtmlString($corpuses);
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
                    ->description('Vue d\'ensemble du contenu de la collection')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                CollectionStatsAndLogs::make('id')
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

        if ($itemsCount > 0) {
            return 'success';
        }

        return 'warning';
    }

    protected function getStatusLabel(): string
    {
        $itemsCount = $this->record->items()->count();

        if ($itemsCount > 0) {
            return 'Actif';
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
