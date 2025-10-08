<?php

namespace App\Filament\Pages;

use App\Models\Corpus;
use App\Models\Collection;
use App\Models\Fond;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Support\Enums\Width;
use Filament\Actions\CreateAction;

class AdvancedSearch extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-magnifying-glass';
    protected string $view = 'filament.pages.advanced-search';
    protected static string|null|\UnitEnum $navigationGroup = 'Médias & Items';
    protected static ?string $navigationLabel = 'Recherche Avancée';
    protected static ?string $title = 'Recherche Avancée';
    protected static ?int $navigationSort = 3;

    protected ?string $heading = 'Recherche Avancée dans les Médias';

    public function getHeadingDescription(): ?string
    {
        return 'Utilisez les filtres ci-dessous pour effectuer une recherche précise dans tous vos items';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Item::query())
            ->columns([
                // Code avec icône selon le type
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Code copié!')
                    ->formatStateUsing(function ($record) {
                        return $record->code;
                    }),
                    //->description(fn ($record) => $record->title),

                // Parent hiérarchique
                TextColumn::make('itemable_type')
                    ->label('Parent')
                    ->formatStateUsing(function ($record) {
                        $type = match($record->itemable_type) {
                            'App\Models\Fond' => 'Fonds',
                            'App\Models\Corpus' => 'Corpus',
                            'App\Models\Collection' => 'Collection',
                            'App\Models\Item' => 'Item',
                            default => ''
                        };

                        return $type . ': ' . ($record->itemable->code ?? 'N/A');
                    })
                    /*->color(fn ($record) => match($record->itemable_type) {
                        'App\Models\Fond' => 'primary',
                        'App\Models\Corpus' => 'success',
                        'App\Models\Collection' => 'info',
                        'App\Models\Item' => 'warning',
                        default => 'gray'
                    })*/
                    ->size('sm'),

                // Type d'item
                IconColumn::make('is_sub')
                    ->label('Meta item')
                    ->boolean(),

                // Format et taille
                TextColumn::make('file_extension')
                    ->label('Format')
                    ->formatStateUsing(fn ($state) => strtoupper($state ?? 'N/A'))
                    ->badge()
                    ->color(fn ($state) => match(strtolower($state ?? '')) {
                        'wav', 'mp3', 'flac' => 'success',
                        'mp4', 'avi', 'mov' => 'info',
                        'pdf' => 'danger',
                        'jpg', 'png', 'gif' => 'warning',
                        default => 'gray'
                    }),

                TextColumn::make('file_size')
                    ->label('Taille')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return 'N/A';

                        $units = ['B', 'KB', 'MB', 'GB'];
                        $power = floor(log($state, 1024));
                        $power = min($power, count($units) - 1);

                        return round($state / pow(1024, $power), 2) . ' ' . $units[$power];
                    })
                    ->sortable()
                    ->alignEnd(),

                // Durée pour audio/vidéo
                TextColumn::make('duration')
                    ->label('Durée')
                    ->formatStateUsing(function ($state) {
                        if (!$state) return '-';

                        $minutes = floor($state / 60);
                        $seconds = $state % 60;
                        return sprintf('%d:%02d', $minutes, $seconds);
                    })
                    ->placeholder('-')
                    ->alignCenter(),

                // Langue si applicable
                TextColumn::make('language_code')
                    ->label('Langue')
                    ->formatStateUsing(fn ($state) => strtoupper($state ?? '-'))
                    ->placeholder('-')
                    ->alignCenter(),

                // Informations upload
                TextColumn::make('uploader.name')
                    ->label('Uploadé par')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('upload_date')
                    ->label('Date Upload')
                    ->date('d/m/Y')
                    ->sortable()
                    ->since()
                    ->tooltip(fn ($state) => $state?->format('d/m/Y à H:i')),

                TextColumn::make('created_at')
                    ->label('Créé')
                    ->date('d/m/Y')
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Texte libre
                Filter::make('global_search')
                    ->schema([
                        TextInput::make('search')
                            ->label('Recherche globale')
                            ->placeholder('Code, titre, nom de fichier...')
                            ->suffixIcon('heroicon-o-magnifying-glass')
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['search'] ?? null,
                            fn (Builder $query, $search): Builder => $query->where(function (Builder $query) use ($search) {
                                $query->where('code', 'like', "%{$search}%")
                                    ->orWhere('title', 'like', "%{$search}%")
                                    ->orWhere('file_name', 'like', "%{$search}%");
                            })
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if ($data['search'] ?? null) {
                            return 'Recherche: ' . $data['search'];
                        }
                        return null;
                    }),

                // Hiérarchie - Fonds
                SelectFilter::make('fond')
                    ->label('Fonds')
                    ->placeholder('Tous les fonds')
                    ->options(Fond::pluck('code', 'id'))
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            function (Builder $query, $fondId): Builder {
                                return $query->where(function (Builder $query) use ($fondId) {
                                    $query->where('itemable_type', 'App\Models\Fond')
                                        ->where('itemable_id', $fondId)
                                        ->orWhereHas('itemable', function (Builder $query) use ($fondId) {
                                            $query->when(
                                                $query->getModel() instanceof Corpus,
                                                fn (Builder $q) => $q->where('fond_id', $fondId)
                                            );
                                        })
                                        ->orWhereHas('itemable', function (Builder $query) use ($fondId) {
                                            $query->when(
                                                $query->getModel() instanceof Collection,
                                                fn (Builder $q) => $q->whereHas('corpus', fn (Builder $q) => $q->where('fond_id', $fondId))
                                            );
                                        });
                                });
                            }
                        );
                    }),

                // Hiérarchie - Corpus
                SelectFilter::make('corpus')
                    ->label('Corpus')
                    ->placeholder('Tous les corpus')
                    ->options(Corpus::with('fond')->get()->mapWithKeys(fn ($corpus) => [$corpus->id => $corpus->code]))
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            function (Builder $query, $corpusId): Builder {
                                return $query->where(function (Builder $query) use ($corpusId) {
                                    $query->where('itemable_type', 'App\Models\Corpus')
                                        ->where('itemable_id', $corpusId)
                                        ->orWhereHas('itemable', function (Builder $query) use ($corpusId) {
                                            $query->when(
                                                $query->getModel() instanceof Collection,
                                                fn (Builder $q) => $q->where('corpus_id', $corpusId)
                                            );
                                        });
                                });
                            }
                        );
                    }),

                // Hiérarchie - Collection
                SelectFilter::make('collection')
                    ->label('Collection')
                    ->placeholder('Toutes les collections')
                    ->options(Collection::with(['corpus.fond'])->get()->mapWithKeys(fn ($collection) => [
                        $collection->id => $collection->code
                    ]))
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $query, $collectionId): Builder => $query->where('itemable_type', 'App\Models\Collection')->where('itemable_id', $collectionId)
                        );
                    }),

                // Type d'item
                SelectFilter::make('item_type')
                    ->label('Type d\'item')
                    ->placeholder('Tous les types')
                    ->options([
                            'principal' => '🎵 Items principaux',
                            'secondaire' => '📎 Items secondaires'
                        ] + ItemType::pluck('name', 'id')->mapWithKeys(fn ($name, $id) => [$id => '📄 ' . $name])->toArray())
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            function (Builder $query, $value): Builder {
                                if ($value === 'principal') {
                                    return $query->whereNull('item_type_id');
                                } elseif ($value === 'secondaire') {
                                    return $query->whereNotNull('item_type_id');
                                } else {
                                    return $query->where('item_type_id', $value);
                                }
                            }
                        );
                    }),

                // Format de fichier
                SelectFilter::make('file_extension')
                    ->label('Format')
                    ->placeholder('Tous les formats')
                    ->options(function () {
                        return Item::whereNotNull('file_extension')
                            ->distinct()
                            ->pluck('file_extension')
                            ->mapWithKeys(fn ($ext) => [$ext => strtoupper($ext)])
                            ->sort();
                    }),

                // Utilisateurs
                SelectFilter::make('uploaded_by')
                    ->label('Uploadé par')
                    ->placeholder('Tous les utilisateurs')
                    ->options(User::pluck('name', 'id')),

                SelectFilter::make('created_by')
                    ->label('Créé par')
                    ->placeholder('Tous les créateurs')
                    ->options(User::pluck('name', 'id')),

                // Date d'upload
                Filter::make('upload_date')
                    ->label('Période d\'upload')
                    ->schema([
                        DatePicker::make('upload_from'),
                        DatePicker::make('upload_until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['upload_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('upload_date', '>=', $date),
                            )
                            ->when(
                                $data['upload_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('upload_date', '<=', $date),
                            );
                    }),

                // Taille de fichier
                Filter::make('file_size_range')
                    ->schema([
                        TextInput::make('size_min')
                            ->label('Taille min (MB)')
                            ->numeric()
                            ->suffix('MB'),
                        TextInput::make('size_max')
                            ->label('Taille max (MB)')
                            ->numeric()
                            ->suffix('MB'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['size_min'] ?? null,
                                fn (Builder $query, $size): Builder => $query->where('file_size', '>=', $size * 1024 * 1024)
                            )
                            ->when(
                                $data['size_max'] ?? null,
                                fn (Builder $query, $size): Builder => $query->where('file_size', '<=', $size * 1024 * 1024)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['size_min'] ?? null) {
                            $indicators['size_min'] = 'Taille min: ' . $data['size_min'] . ' MB';
                        }

                        if ($data['size_max'] ?? null) {
                            $indicators['size_max'] = 'Taille max: ' . $data['size_max'] . ' MB';
                        }

                        return $indicators;
                    }),

                // Durée pour audio/vidéo
                Filter::make('duration_range')
                    ->schema([
                        TextInput::make('duration_min')
                            ->label('Durée min (sec)')
                            ->numeric()
                            ->suffix('sec'),
                        TextInput::make('duration_max')
                            ->label('Durée max (sec)')
                            ->numeric()
                            ->suffix('sec'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['duration_min'] ?? null,
                                fn (Builder $query, $duration): Builder => $query->where('duration', '>=', $duration)
                            )
                            ->when(
                                $data['duration_max'] ?? null,
                                fn (Builder $query, $duration): Builder => $query->where('duration', '<=', $duration)
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['duration_min'] ?? null) {
                            $indicators['duration_min'] = 'Durée min: ' . $data['duration_min'] . ' sec';
                        }

                        if ($data['duration_max'] ?? null) {
                            $indicators['duration_max'] = 'Durée max: ' . $data['duration_max'] . ' sec';
                        }

                        return $indicators;
                    }),

                // Langue
                SelectFilter::make('language_code')
                    ->label('Langue')
                    ->placeholder('Toutes les langues')
                    ->options(function () {
                        return Item::whereNotNull('language_code')
                            ->distinct()
                            ->pluck('language_code')
                            ->mapWithKeys(fn ($lang) => [$lang => strtoupper($lang)])
                            ->sort();
                    }),

            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                Action::make('view')
                    ->label('Voir')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn ($record) => '🔍 ' . $record->code)
                    ->modalContent(fn (\Illuminate\Database\Eloquent\Model $record): View => view(
                        'filament.items.view-modal',
                        ['record' => $record]
                    ))
                    ->modalCancelAction(fn (Action $action) => $action->label('Close'))
                    ->modalSubmitAction(false)
                    ->modalWidth(Width::FourExtraLarge),

                Action::make('download')
                    ->label('Télécharger')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn ($record) => response()->download(storage_path('app/' . $record->file_path), $record->file_name))
                    ->hidden(fn ($record) => !$record->file_path),

                Action::make('viewInHierarchy')
                    ->label('Hiérarchie')
                    ->icon('heroicon-o-folder')
                    ->color('info')
                    ->url(fn ($record) => route('filament.mms-admin.pages.hierarchy-explorer', [
                        'focus' => 'item',
                        'id' => $record->id
                    ]))
                    ->openUrlInNewTab(),

                /*Action::make('copyCode')
                    ->label('Copier Code')
                    ->icon('heroicon-o-clipboard')
                    ->color('gray')
                    ->action(fn ($record) => null)
                    ->extraAttributes([
                        'x-on:click' => 'navigator.clipboard.writeText("' . '{{ $getRecord()->code }}' . '"); $tooltip("Code copié!", { timeout: 2000 })'
                    ]),*/
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('export')
                        ->label('Exporter Sélection')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->color('info')
                        ->action(function ($records) {
                            // Logique d'export
                            $csvContent = "Code,Type,Parent,Format,Taille,Date Upload,Uploadé par\n";

                            foreach ($records as $record) {
                                $csvContent .= sprintf(
                                    "%s,%s,%s,%s,%s,%s,%s\n",
                                    $record->code,
                                    $record->itemType?->name ?? 'Principal',
                                    $record->itemable?->code ?? '',
                                    strtoupper($record->file_extension ?? ''),
                                    $record->file_size ?? '',
                                    $record->upload_date?->format('d/m/Y') ?? '',
                                    $record->uploader?->name ?? ''
                                );
                            }

                            return response()->streamDownload(function () use ($csvContent) {
                                echo $csvContent;
                            }, 'items-export-' . date('Y-m-d') . '.csv');
                        }),

                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalDescription('Êtes-vous sûr de vouloir supprimer ces items ? Cette action est irréversible.')
                ]),
            ])
            ->emptyStateIcon('heroicon-o-magnifying-glass')
            ->emptyStateHeading('Aucun résultat')
            ->emptyStateDescription('Essayez de modifier vos critères de recherche pour trouver des items.')
            ->defaultSort('created_at', 'desc')
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->striped();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetFilters')
                ->label('Réinitialiser les filtres')
                ->icon('heroicon-o-x-circle')
                ->color('gray')
                ->action(fn () => $this->resetTableFilters()),

            Action::make('exportAll')
                ->label('Exporter tout')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('success')
                ->action(function () {
                    $items = $this->getFilteredTableQuery()->get();

                    $csvContent = "Code,Type,Parent,Format,Taille,Date Upload,Uploadé par\n";

                    foreach ($items as $item) {
                        $csvContent .= sprintf(
                            "%s,%s,%s,%s,%s,%s,%s\n",
                            $item->code,
                            $item->itemType?->name ?? 'Principal',
                            $item->itemable?->code ?? '',
                            strtoupper($item->file_extension ?? ''),
                            $item->file_size ?? '',
                            $item->upload_date?->format('d/m/Y') ?? '',
                            $item->uploader?->name ?? ''
                        );
                    }

                    return response()->streamDownload(function () use ($csvContent) {
                        echo $csvContent;
                    }, 'items-complet-export-' . date('Y-m-d') . '.csv');
                })
                ->requiresConfirmation()
                ->modalDescription('Exporter tous les résultats de la recherche actuelle en CSV ?'),

            Action::make('quickStats')
                ->label('Statistiques')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->modalHeading('📊 Statistiques de recherche')
                ->modalContent(function () {
                    $query = $this->getFilteredTableQuery();

                    $stats = [
                        'total' => $query->count(),
                        'principaux' => $query->whereNull('item_type_id')->count(),
                        'secondaires' => $query->whereNotNull('item_type_id')->count(),
                        'taille_totale' => $query->sum('file_size'),
                        'par_format' => $query->selectRaw('file_extension, COUNT(*) as count')
                            ->groupBy('file_extension')
                            ->pluck('count', 'file_extension'),
                    ];

                    return view('filament.pages.search-stats', ['stats' => $stats]);
                })
                ->modalCancelAction(fn (Action $action) => $action->label('Close'))
                ->modalSubmitAction(false)
                ->modalWidth(Width::TwoExtraLarge),
        ];
    }
}
