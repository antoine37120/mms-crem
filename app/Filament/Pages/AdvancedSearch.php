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
    protected static string|null|\UnitEnum $navigationGroup = 'Recherche & Exploration';
    protected static ?string $navigationLabel = 'Recherche Avancée';
    protected static ?string $title = 'Recherche Avancée';
    protected static ?int $navigationSort = 1;

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
                    ->label('Cote')
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
                    ->size('sm')
                    ->toggleable(isToggledHiddenByDefault: false),

                // Type d'item
                IconColumn::make('is_sub')
                    ->label('Média associé')
                    ->alignCenter()
                    ->wrapHeader(true)
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: false),

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
                    })
                    ->toggleable(isToggledHiddenByDefault: false),

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
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: false),

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
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Langue si applicable
                TextColumn::make('language_code')
                    ->label('Langue')
                    ->formatStateUsing(fn ($state) => strtoupper($state ?? '-'))
                    ->placeholder('-')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Informations upload
                TextColumn::make('uploader.name')
                    ->label('Uploadé par')
                    ->alignCenter()
                    ->wrapHeader(true)
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('upload_date')
                    ->label('Date Upload')
                    ->alignCenter()
                    ->wrapHeader(true)
                    ->date('d/m/Y')
                    ->sortable()
                    ->since()
                    ->tooltip(fn ($state) => $state?->format('d/m/Y à H:i'))
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('created_at')
                    ->label('Créé le')
                    ->alignCenter()
                    ->wrapHeader(true)
                    ->date('d/m/Y')
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Texte libre
                Filter::make('global_search')
                    ->columnSpan('full')
                    ->form([
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

                // Hiérarchie (Fond, Corpus, Collection) - Filtre combiné
                Filter::make('hierarchy')
                    ->form([
                        \Filament\Schemas\Components\Fieldset::make('Classification hiérarchique')
                            ->schema([
                                Select::make('fond_id')
                                    ->label('Fonds')
                                    ->placeholder('Tous les fonds')
                                    ->options(Fond::pluck('code', 'id'))
                                    ->live()
                                    ->afterStateUpdated(function ($set) {
                                        $set('corpus_ids', null);
                                        $set('collection_ids', null);
                                    }),
                                
                                Select::make('corpus_ids')
                                    ->label('Corpus')
                                    ->placeholder('Tous les corpus')
                                    ->multiple()
                                    ->options(function ($get) {
                                        $fondId = $get('fond_id');
                                        if ($fondId) {
                                            return Corpus::whereHas('fonds', fn($q) => $q->where('fonds.id', $fondId))->pluck('code', 'id');
                                        }
                                        return Corpus::pluck('code', 'id');
                                    })
                                    ->live()
                                    ->afterStateUpdated(function ($set) {
                                        $set('collection_ids', null);
                                    }),
                                    
                                Select::make('collection_ids')
                                    ->label('Collections')
                                    ->placeholder('Toutes les collections')
                                    ->multiple()
                                    ->options(function ($get) {
                                        $corpusIds = $get('corpus_ids') ?? [];
                                        $fondId = $get('fond_id');
                                        
                                        $query = Collection::query();
                                        
                                        if (!empty($corpusIds)) {
                                            $query->whereHas('corpuses', fn($q) => $q->whereIn('corpuses.id', $corpusIds));
                                        } elseif ($fondId) {
                                            $query->whereHas('corpuses', fn($q) => $q->whereHas('fonds', fn($f) => $f->where('fonds.id', $fondId)));
                                        }
                                        
                                        return $query->pluck('code', 'id');
                                    }),
                            ])
                            ->columns(3),
                    ])
                    ->columnSpan('full')
                    ->query(function (Builder $query, array $data): Builder {
                        $fondId = $data['fond_id'] ?? null;
                        $corpusIds = $data['corpus_ids'] ?? [];
                        $collectionIds = $data['collection_ids'] ?? [];

                        if (empty($fondId) && empty($corpusIds) && empty($collectionIds)) {
                            return $query;
                        }

                        return $query->where(function (Builder $q) use ($fondId, $corpusIds, $collectionIds) {
                            // Niveau le plus profond : Collections sélectionnées
                            if (!empty($collectionIds)) {
                                $q->where('itemable_type', 'App\Models\Collection')
                                  ->whereIn('itemable_id', $collectionIds);
                            }
                            // Niveau intermédiaire : Corpus sélectionnés (sans collections)
                            elseif (!empty($corpusIds)) {
                                $collectionSubquery = \App\Models\Collection::whereHas('corpuses', fn($q) => $q->whereIn('corpuses.id', $corpusIds))->select('collections.id');
                                
                                $q->where(function ($sub) use ($corpusIds, $collectionSubquery) {
                                    $sub->where('itemable_type', 'App\Models\Corpus')
                                        ->whereIn('itemable_id', $corpusIds)
                                        ->orWhere(function ($sub2) use ($collectionSubquery) {
                                            $sub2->where('itemable_type', 'App\Models\Collection')
                                                 ->whereIn('itemable_id', $collectionSubquery);
                                        });
                                });
                            }
                            // Niveau le plus haut : Fond sélectionné (sans corpus, sans collections)
                            elseif ($fondId) {
                                $corpusSubquery = \App\Models\Corpus::whereHas('fonds', fn($q) => $q->where('fonds.id', $fondId))->select('corpuses.id');
                                $collectionSubquery = \App\Models\Collection::whereHas('corpuses', fn($q) => $q->whereHas('fonds', fn($f) => $f->where('fonds.id', $fondId)))->select('collections.id');
                                
                                $q->where(function ($sub) use ($fondId, $corpusSubquery, $collectionSubquery) {
                                    $sub->where(function ($sub2) use ($fondId) {
                                            $sub2->where('itemable_type', 'App\Models\Fond')
                                                 ->where('itemable_id', $fondId);
                                        })
                                        ->orWhere(function ($sub2) use ($corpusSubquery) {
                                            $sub2->where('itemable_type', 'App\Models\Corpus')
                                                 ->whereIn('itemable_id', $corpusSubquery);
                                        })
                                        ->orWhere(function ($sub2) use ($collectionSubquery) {
                                            $sub2->where('itemable_type', 'App\Models\Collection')
                                                 ->whereIn('itemable_id', $collectionSubquery);
                                        });
                                });
                            }
                        });
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (!empty($data['fond_id'])) {
                            $fond = Fond::find($data['fond_id']);
                            if ($fond) $indicators['fond_id'] = 'Fonds: ' . $fond->code;
                        }
                        if (!empty($data['corpus_ids'])) {
                            $indicators['corpus_ids'] = count($data['corpus_ids']) . ' Corpus sélectionné(s)';
                        }
                        if (!empty($data['collection_ids'])) {
                            $indicators['collection_ids'] = count($data['collection_ids']) . ' Collection(s) sélectionnée(s)';
                        }
                        return $indicators;
                    }),

                // Groupe 2: Type et Format
                Filter::make('nature')
                    ->columnSpan(1)
                    ->form([
                        \Filament\Schemas\Components\Fieldset::make('Description')
                            ->schema([
                                \Filament\Forms\Components\ToggleButtons::make('is_sub')
                                    ->label('Type d\'élément')
                                    ->options(['all' => 'Tous', 'items' => 'Items', 'medias' => 'Médias associés'])
                                    ->colors(['all' => 'gray', 'items' => 'primary', 'medias' => 'info'])
                                    ->grouped()
                                    ->default('all')
                                    ->columnSpan('full'),
                                    
                                Select::make('item_type_id')
                                    ->label('Type de média associé')
                                    ->placeholder('Tous les types')
                                    ->options(ItemType::pluck('name', 'id')->mapWithKeys(fn($n, $id) => [$id => "📄 $n"])->toArray())
                                    ->columnSpan('full'),
                                    
                                Select::make('file_extension')
                                    ->label('Format')
                                    ->placeholder('Tous les formats')
                                    ->options(fn() => Item::whereNotNull('file_extension')->distinct()->pluck('file_extension')->mapWithKeys(fn($e) => [$e => strtoupper($e)])->sort())
                                    ->columnSpan('full'),

                                Select::make('language_code')
                                    ->label('Langue')
                                    ->placeholder('Toutes')
                                    ->options(fn() => Item::whereNotNull('language_code')->distinct()->pluck('language_code')->mapWithKeys(fn($l) => [$l => strtoupper($l)])->sort())
                                    ->columnSpan('full'),
                            ])
                            ->columns(2),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(isset($data['is_sub']) && $data['is_sub'] === 'items', fn($q) => $q->where('is_sub', false))
                            ->when(isset($data['is_sub']) && $data['is_sub'] === 'medias', fn($q) => $q->where('is_sub', true))
                            ->when($data['item_type_id'] ?? null, fn($q, $v) => $q->where('item_type_id', $v))
                            ->when($data['file_extension'] ?? null, fn($q, $v) => $q->where('file_extension', $v))
                            ->when($data['language_code'] ?? null, fn($q, $v) => $q->where('language_code', $v));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (isset($data['is_sub'])) {
                            if ($data['is_sub'] === 'items') $indicators['is_sub'] = 'Items principaux';
                            if ($data['is_sub'] === 'medias') $indicators['is_sub'] = 'Médias associés';
                        }
                        if (!empty($data['item_type_id'])) $indicators['item_type_id'] = 'Type média: ' . ItemType::find($data['item_type_id'])?->name;
                        if (!empty($data['file_extension'])) $indicators['file_extension'] = 'Format: ' . strtoupper($data['file_extension']);
                        if (!empty($data['language_code'])) $indicators['language_code'] = 'Langue: ' . strtoupper($data['language_code']);
                        return $indicators;
                    }),
                    
                // Groupe 3: Propriétés Physiques
                Filter::make('properties')
                    ->columnSpan(1)
                    ->form([
                        \Filament\Schemas\Components\Fieldset::make('Poids & Durée')
                            ->schema([
                                TextInput::make('size_min')->label('Taille min (MB)')->numeric()->suffix('MB'),
                                TextInput::make('size_max')->label('Taille max (MB)')->numeric()->suffix('MB'),
                                TextInput::make('duration_min')->label('Durée min (s)')->numeric()->suffix('s'),
                                TextInput::make('duration_max')->label('Durée max (s)')->numeric()->suffix('s'),
                            ])
                            ->columns(2),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['size_min'] ?? null, fn($q, $v) => $q->where('file_size', '>=', $v * 1024 * 1024))
                            ->when($data['size_max'] ?? null, fn($q, $v) => $q->where('file_size', '<=', $v * 1024 * 1024))
                            ->when($data['duration_min'] ?? null, fn($q, $v) => $q->where('duration', '>=', $v))
                            ->when($data['duration_max'] ?? null, fn($q, $v) => $q->where('duration', '<=', $v))
                            ->when($data['language_code'] ?? null, fn($q, $v) => $q->where('language_code', $v));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (!empty($data['size_min'])) $indicators['size_min'] = 'Taille > ' . $data['size_min'] . ' MB';
                        if (!empty($data['size_max'])) $indicators['size_max'] = 'Taille < ' . $data['size_max'] . ' MB';
                        if (!empty($data['duration_min'])) $indicators['duration_min'] = 'Durée > ' . $data['duration_min'] . 's';
                        if (!empty($data['duration_max'])) $indicators['duration_max'] = 'Durée < ' . $data['duration_max'] . 's';
                        if (!empty($data['language_code'])) $indicators['language_code'] = 'Langue: ' . strtoupper($data['language_code']);
                        return $indicators;
                    }),
                    
                // Groupe 4: Traçabilité
                Filter::make('traceability')
                    ->columnSpan(1)
                    ->form([
                        \Filament\Schemas\Components\Fieldset::make('Traçabilité')
                            ->schema([
                                Select::make('uploaded_by')->label('Uploadé par')->placeholder('Tous les utilisateurs')->options(User::pluck('name', 'id'))->columnSpan('full'),
                                Select::make('created_by')->label('Créé par')->placeholder('Tous les créateurs')->options(User::pluck('name', 'id'))->columnSpan('full'),
                                DatePicker::make('upload_from')->label('Uploadé depuis le')->columnSpan('full'),
                                DatePicker::make('upload_until')->label('Uploadé avant le')->columnSpan('full'),
                            ])
                            ->columns(2),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['uploaded_by'] ?? null, fn($q, $v) => $q->where('uploaded_by', $v))
                            ->when($data['created_by'] ?? null, fn($q, $v) => $q->where('created_by', $v))
                            ->when($data['upload_from'] ?? null, fn($q, $v) => $q->whereDate('upload_date', '>=', $v))
                            ->when($data['upload_until'] ?? null, fn($q, $v) => $q->whereDate('upload_date', '<=', $v));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (!empty($data['uploaded_by'])) $indicators['uploaded_by'] = 'Uploadé par: ' . User::find($data['uploaded_by'])?->name;
                        if (!empty($data['created_by'])) $indicators['created_by'] = 'Créé par: ' . User::find($data['created_by'])?->name;
                        if (!empty($data['upload_from'])) $indicators['upload_from'] = 'Depuis le: ' . \Carbon\Carbon::parse($data['upload_from'])->format('d/m/Y');
                        if (!empty($data['upload_until'])) $indicators['upload_until'] = 'Jusqu\'au: ' . \Carbon\Carbon::parse($data['upload_until'])->format('d/m/Y');
                        return $indicators;
                    }),

            ], layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->recordActions([
                Action::make('view')
                    ->label('Voir')
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => $record->is_sub 
                        ? route('filament.mms-admin.resources.media-associes.view', ['record' => $record])
                        : route('filament.mms-admin.resources.items.view', ['record' => $record])
                    ),

                /*Action::make('download')
                    ->label('Télécharger')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(fn ($record) => response()->download(storage_path('app/' . $record->file_path), $record->file_name))
                    ->hidden(fn ($record) => !$record->file_path),*/

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
