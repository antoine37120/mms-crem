<?php

namespace App\Filament\Pages;

use App\Models\Fond;
use App\Models\Corpus;
use App\Models\Collection;
use App\Models\Item;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class HierarchyExplorer extends Page implements HasForms
{
    use InteractsWithForms;

    // Configuration de base
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-folder';
    protected static string|null|\UnitEnum $navigationGroup = 'Explorateur';
    protected static ?string $navigationLabel = 'Vue Hiérarchique';
    protected static ?string $title = '';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'hierarchy-explorer';

    protected string $view = 'filament.pages.hierarchy-explorer';

    // État de recherche
    public string $searchTerm = '';

    // Mode d'exploration
    public string $mode = 'collections'; // 'collections' (default) or 'fonds'

    // États de sélection
    public ?string $selectedType = null; // 'fond', 'corpus', 'collection', 'item'
    public ?int $selectedId = null;
    public ?array $selectedElement = null;

    // État de sélection item (colonne 2)
    public ?int $selectedItemId = null;
    public ?array $selectedItem = null;

    // États d'expansion dans les arbres
    public array $expandedFonds = [];
    public array $expandedCorpuses = [];
    public array $expandedCollections = []; // Pour le mode Collections (expand mainItems)
    public array $expandedItems = [];

    // Pagination pour le mode Collections
    public int $collectionsPerPage = 100;
    public int $collectionsPage = 1;
    public bool $loadingMoreCollections = false;
    public bool $hasMoreCollections = true;

    // Scroll bidirectionnel
    public int $collectionsStartIndex = 0;
    public bool $hasMoreCollectionsBefore = false;
    public bool $loadingMoreCollectionsBefore = false;

    // Propriétés pour les paramètres URL
    public ?string $focus = null;
    public ?int $id = null;

    protected $queryString = [
        'mode' => ['except' => 'collections'],
        'focus' => ['except' => ''],
        'id' => ['except' => null],
        'searchTerm' => ['except' => ''],
    ];

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                TextInput::make('searchTerm')
                    ->hiddenLabel()
                    ->placeholder('Rechercher...')
                    ->prefixIcon('heroicon-o-magnifying-glass')
                    ->live(debounce: 300),
            ]);
    }

    public function mount(): void
    {
        // Récupérer les paramètres directement depuis l'URL (géré par queryString mais on force l'init)
        $this->mode = request()->query('mode', 'collections');
        $this->focus = request()->query('focus');
        $this->id = request()->query('id') ? (int) request()->query('id') : null;

        $this->form->fill([
            'searchTerm' => $this->searchTerm,
        ]);

        // Initialiser selon les paramètres URL ou par défaut
        $this->initializeFromUrlOrDefault();
    }

    /**
     * Change le mode d'exploration
     */
    public function setMode(string $mode): void
    {
        if (in_array($mode, ['collections', 'fonds'])) {
            $this->mode = $mode;
            // Reset sélection si changement de mode
            $this->selectedType = null;
            $this->selectedId = null;
            $this->selectedElement = null;
            $this->selectedItemId = null;
            $this->selectedItem = null;

            // Réinitialiser la pagination des collections
            $this->collectionsPage = 1;
            $this->collectionsStartIndex = 0;
            $this->hasMoreCollections = true;
            $this->hasMoreCollectionsBefore = false;

            // Réinitialiser l'URL
            $this->focus = null;
            $this->id = null;
        }
    }

    /**
     * Initialise l'état de l'explorateur selon les paramètres URL
     */
    protected function initializeFromUrlOrDefault(): void
    {
        if ($this->focus && $this->id) {
            try {
                switch (strtolower($this->focus)) {
                    case 'fond':
                    case 'fonds':
                        if ($this->mode !== 'fonds') $this->mode = 'fonds';
                        $this->initializeFocusOnFond($this->id);
                        break;
                    case 'corpus':
                        if ($this->mode !== 'fonds') $this->mode = 'fonds';
                        $this->initializeFocusOnCorpus($this->id);
                        break;
                    case 'collection':
                    case 'collections':
                        // Peut être dans les deux modes, on garde le mode actuel sauf si forcé
                        $this->initializeFocusOnCollection($this->id);
                        break;
                    case 'item':
                    case 'items':
                        $this->initializeFocusOnItem($this->id);
                        break;
                    default:
                        // Pas d'initialisation par défaut forcée pour ne pas perturber l'utilisateur
                }
            } catch (\Exception $e) {
                // Si erreur, on ne fait rien
            }
        }
    }

    /**
     * Initialise avec focus sur un fonds
     */
    protected function initializeFocusOnFond(int $fondId): void
    {
        $fond = Fond::find($fondId);
        if ($fond) {
            $this->selectElement('fond', $fond->id);
            $this->expandedFonds[] = $fond->id;
        }
    }

    /**
     * Initialise avec focus sur un corpus
     */
    protected function initializeFocusOnCorpus(int $corpusId): void
    {
        $corpus = Corpus::with('fonds')->find($corpusId);
        if ($corpus) {
            // En mode Fonds, on doit expandre un parent Fond
            if ($this->mode === 'fonds' && $corpus->fonds->isNotEmpty()) {
                $this->expandedFonds[] = $corpus->fonds->first()->id;
            }

            $this->expandedCorpuses[] = $corpus->id;
            $this->selectElement('corpus', $corpus->id);
        }
    }

    /**
     * Initialise avec focus sur une collection
     */
    protected function initializeFocusOnCollection(int $collectionId): void
    {
        $collection = Collection::with(['corpuses.fonds'])->find($collectionId);
        if ($collection) {
            if ($this->mode === 'fonds') {
                // Tenter de trouver un chemin pour expandre
                if ($collection->corpuses->isNotEmpty()) {
                    $corpus = $collection->corpuses->first();
                    $this->expandedCorpuses[] = $corpus->id;

                    if ($corpus->fonds->isNotEmpty()) {
                        $this->expandedFonds[] = $corpus->fonds->first()->id;
                    }
                }
            } else {
                // Mode Collections : initialiser la fenêtre de pagination centrée
                $this->initializeCollectionWindow($collectionId);

                // Expandre pour voir les mainItems
                $this->expandedCollections[] = $collection->id;
            }

            $this->selectElement('collection', $collection->id);
        }
    }

    /**
     * Initialise avec focus sur un item
     */
    protected function initializeFocusOnItem(int $itemId): void
    {
        $item = Item::with(['itemable'])->find($itemId);
        if (!$item || !$item->itemable) {
            return;
        }

        // Logique différente selon le mode et le type de parent
        // Pour simplifier, on se concentre sur l'affichage de l'item

        // Si c'est un item principal (parent = Collection), on peut le sélectionner en Col 1 (Mode Collections)
        if ($item->itemable_type === 'App\Models\Collection' && !$item->is_sub) {
            if ($this->mode === 'collections') {
                $this->initializeFocusOnCollection($item->itemable_id);
                // On sélectionne l'item comme élément principal (Col 1)
                $this->selectElement('item', $item->id);
                return;
            }
        }

        // Sinon, on essaie de remonter au parent affichable en Col 1
        $parent = $this->findVisibleParent($item);

        if ($parent) {
            if ($parent instanceof Collection) {
                $this->initializeFocusOnCollection($parent->id);
            } elseif ($parent instanceof Corpus) {
                $this->initializeFocusOnCorpus($parent->id);
            } elseif ($parent instanceof Fond) {
                $this->initializeFocusOnFond($parent->id);
            } elseif ($parent instanceof Item) {
                // Item Principal en Mode Collections
                if ($this->mode === 'collections') {
                    $this->initializeFocusOnCollection($parent->itemable_id);
                    $this->selectElement('item', $parent->id);
                }
            }

            // Sélectionner l'item en Col 2
            $this->selectItem($item->id);
        }
    }

    /**
     * Trouve le parent visible le plus proche (Collection, Corpus, Fond ou Item Principal)
     */
    protected function findVisibleParent($item)
    {
        if (!$item->itemable) return null;

        // Si le parent est une Collection/Corpus/Fond, c'est bon
        if (in_array($item->itemable_type, [
            'App\Models\Collection',
            'App\Models\Corpus',
            'App\Models\Fond'
        ])) {
            return $item->itemable;
        }

        // Si le parent est un Item
        if ($item->itemable_type === 'App\Models\Item') {
            // Si Mode Collections et parent est Item Principal, c'est bon
            if ($this->mode === 'collections' && !$item->itemable->is_sub) {
                return $item->itemable;
            }
            // Sinon récursion
            return $this->findVisibleParent($item->itemable);
        }

        return null;
    }

    // Sélection d'éléments dans la colonne 1
    public function selectElement(string $type, int $id): void
    {
        $this->selectedType = $type;
        $this->selectedId = $id;
        $this->selectedItemId = null; // Reset sélection item
        $this->selectedItem = null;

        // Mise à jour URL
        $this->focus = $type;
        $this->id = $id;

        switch ($type) {
            case 'fond':
                $element = Fond::with(['corpuses', 'items'])->find($id);
                if (!in_array($id, $this->expandedFonds)) {
                    $this->expandedFonds[] = $id;
                }
                break;
            case 'corpus':
                $element = Corpus::with(['collections', 'items', 'fonds'])->find($id);
                if (!in_array($id, $this->expandedCorpuses)) {
                    $this->expandedCorpuses[] = $id;
                }
                break;
            case 'collection':
                $element = Collection::with(['items', 'corpuses.fonds'])->find($id);
                // En mode Collections, on expand pour voir les mainItems
                if ($this->mode === 'collections' && !in_array($id, $this->expandedCollections)) {
                    $this->expandedCollections[] = $id;
                }
                break;
            case 'item': // Item Principal (Mode Collections)
                $element = Item::with(['childItems', 'itemType', 'itemable'])->find($id);
                // Expand si nécessaire
                break;
            default:
                $element = null;
        }

        $this->selectedElement = $element ? $element->toArray() : null;
    }

    // Sélection d'items dans la colonne 2
    public function selectItem(int $itemId): void
    {
        $this->selectedItemId = $itemId;
        $item = Item::with(['childItems', 'itemType', 'itemable'])->find($itemId);
        $this->selectedItem = $item ? $item->toArray() : null;
    }

    // Actions de basculement d'expansion
    public function toggleFond(int $fondId): void
    {
        if (in_array($fondId, $this->expandedFonds)) {
            $this->expandedFonds = array_diff($this->expandedFonds, [$fondId]);
        } else {
            $this->expandedFonds[] = $fondId;
        }
    }

    public function toggleCorpus(int $corpusId): void
    {
        if (in_array($corpusId, $this->expandedCorpuses)) {
            $this->expandedCorpuses = array_diff($this->expandedCorpuses, [$corpusId]);
        } else {
            $this->expandedCorpuses[] = $corpusId;
        }
    }

    public function toggleCollection(int $collectionId): void
    {
        if (in_array($collectionId, $this->expandedCollections)) {
            $this->expandedCollections = array_diff($this->expandedCollections, [$collectionId]);
        } else {
            $this->expandedCollections[] = $collectionId;
        }
    }

    public function toggleItem(int $itemId): void
    {
        if (in_array($itemId, $this->expandedItems)) {
            $this->expandedItems = array_diff($this->expandedItems, [$itemId]);
        } else {
            $this->expandedItems[] = $itemId;
        }
    }

    // Propriétés computed pour la colonne 1 - Mode Fonds
    public function getFondsProperty()
    {
        $query = Fond::withCount(['corpuses', 'items']);

        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('code', 'like', "%{$this->searchTerm}%")
                    ->orWhere('title', 'like', "%{$this->searchTerm}%")
                    ->orWhere(function($subQuery) {
                        $subQuery->whereRaw("? LIKE CONCAT(code, '%')", [$this->searchTerm]);
                    });
            });
        }

        return $query->orderBy('code')->get();
    }

    public function getCorpusesForFond(?int $fondId)
    {
        if (!$fondId) return collect();

        // Many-to-Many: passer par la relation
        $fond = Fond::find($fondId);
        if (!$fond) return collect();

        $query = $fond->corpuses()->withCount(['collections', 'items']);

        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('corpuses.code', 'like', "%{$this->searchTerm}%")
                    ->orWhere('corpuses.title', 'like', "%{$this->searchTerm}%");
            });
        }

        return $query->orderBy('corpuses.code')->get();
    }

    public function getCollectionsForCorpus(?int $corpusId)
    {
        if (!$corpusId) return collect();

        // Many-to-Many: passer par la relation
        $corpus = Corpus::find($corpusId);
        if (!$corpus) return collect();

        //$query = $corpus->collections()->withCount('items');
        $query = $corpus->collections();

        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('collections.code', 'like', "%{$this->searchTerm}%")
                    ->orWhere('collections.title', 'like', "%{$this->searchTerm}%");
            });
        }

        return $query->orderBy('collections.code')->get();
    }

    // Propriétés computed pour la colonne 1 - Mode Collections
    public function getCollectionsProperty()
    {
        $query = Collection::query();

        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('code', 'like', "%{$this->searchTerm}%")
                    ->orWhere('title', 'like', "%{$this->searchTerm}%")
                    ->orWhere(function($subQuery) {
                        $subQuery->whereRaw("? LIKE CONCAT(code, '%')", [$this->searchTerm]);
                    });
            });

            // Réinitialiser la pagination lors d'une recherche
            $this->collectionsStartIndex = 0;
            $this->collectionsPage = 1;
            $this->hasMoreCollectionsBefore = false;
        }

        $totalCount = $query->count();

        // Calculer le nombre total d'éléments à charger
        $itemsToLoad = $this->collectionsPage * $this->collectionsPerPage;
        $endIndex = $this->collectionsStartIndex + $itemsToLoad;

        $this->hasMoreCollections = $endIndex < $totalCount;
        $this->hasMoreCollectionsBefore = $this->collectionsStartIndex > 0;

        return $query
            ->orderBy('code')
            ->skip($this->collectionsStartIndex)
            ->take($itemsToLoad)
            ->get();
    }

    /**
     * Réinitialise la pagination des collections
     */
    public function resetCollectionsPagination(): void
    {
        $this->collectionsPage = 1;
        $this->collectionsStartIndex = 0;
        $this->hasMoreCollections = true;
        $this->hasMoreCollectionsBefore = false;
    }

    /**
     * Calcule le rang (position) d'une collection dans la liste triée par code
     */
    protected function calculateCollectionRank(int $collectionId): int
    {
        $collection = Collection::find($collectionId);
        if (!$collection) {
            return 0;
        }

        // Compter combien de collections ont un code inférieur (tri alphabétique)
        return Collection::where('code', '<', $collection->code)
            ->orderBy('code')
            ->count();
    }

    /**
     * Initialise la fenêtre de pagination centrée sur une collection
     */
    protected function initializeCollectionWindow(int $collectionId): void
    {
        $collection = Collection::find($collectionId);
        if (!$collection) {
            return;
        }

        $totalCount = Collection::count();

        // Compter combien de collections sont AVANT celle-ci (tri par code)
        $rank = Collection::where('code', '<', $collection->code)->count();

        // Calculer l'index de départ : on veut la collection au milieu de la fenêtre
        // Mais minimum à l'index 0
        $startIndex = max(0, $rank - 30); // 10 collections avant pour contexte

        // S'assurer qu'on ne dépasse pas la fin
        if ($startIndex + $this->collectionsPerPage > $totalCount && $totalCount > $this->collectionsPerPage) {
            $startIndex = $totalCount - $this->collectionsPerPage;
        }

        $this->collectionsStartIndex = $startIndex;
        $this->collectionsPage = 1;
        $this->hasMoreCollectionsBefore = $startIndex > 0;
        $this->hasMoreCollections = ($startIndex + $this->collectionsPerPage) < $totalCount;

    }


    /**
     * Charge plus de collections vers le bas (infinite scroll)
     */
    public function loadMoreCollections(): void
    {
        if (!$this->hasMoreCollections || $this->loadingMoreCollections) {
            return;
        }

        $this->loadingMoreCollections = true;
        $this->collectionsPage++;
        $this->loadingMoreCollections = false;
    }

    /**
     * Charge plus de collections vers le haut (scroll inverse)
     */
    public function loadMoreCollectionsBefore(): void
    {
        if (!$this->hasMoreCollectionsBefore || $this->loadingMoreCollectionsBefore) {
            return;
        }

        $this->loadingMoreCollectionsBefore = true;

        // Décaler l'index de départ de 30 positions vers le début
        $this->collectionsStartIndex = max(0, $this->collectionsStartIndex - $this->collectionsPerPage);

        // Mettre à jour le flag
        $this->hasMoreCollectionsBefore = $this->collectionsStartIndex > 0;

        $this->loadingMoreCollectionsBefore = false;
    }


    public function getMainItemsForCollection(?int $collectionId)
    {
        if (!$collectionId) return collect();

        $collection = Collection::find($collectionId);
        if (!$collection) return collect();

        $query = $collection->mainItems()->withCount('childItems');

        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('code', 'like', "%{$this->searchTerm}%")
                    ->orWhere('title', 'like', "%{$this->searchTerm}%")
                    ->orWhere('file_name', 'like', "%{$this->searchTerm}%");
            });
        }

        return $query->orderBy('code')->get();
    }

    // Propriétés computed pour la colonne 2 - Items hiérarchiques
    public function getSelectedElementItemsProperty()
    {
        if (!$this->selectedType || !$this->selectedId) {
            return collect();
        }

        // Cas spécial : Si on a sélectionné un Item Principal en Col 1 (Mode Collections)
        if ($this->selectedType === 'item') {
            $item = Item::find($this->selectedId);
            if (!$item) return collect();

            // On retourne ses enfants
            return $item->childItems()->with(['childItems', 'itemType'])->orderBy('code')->get();
        }

        $modelClass = match($this->selectedType) {
            'fond' => Fond::class,
            'corpus' => Corpus::class,
            'collection' => Collection::class,
            default => null
        };

        if (!$modelClass) {
            return collect();
        }

        // Récupérer l'instance pour utiliser les relations
        $instance = $modelClass::find($this->selectedId);
        if (!$instance) return collect();

        // Mode Collections : Si Collection sélectionnée, on montre ses secondaryItems
        if ($this->mode === 'collections' && $this->selectedType === 'collection') {
            $query = $instance->secondaryItems()->with(['childItems', 'itemType']);
        }
        // Mode Fonds (ou autre): On prend tout (items) pour pouvoir séparer Main/Secondary
        else {
            $query = $instance->items()->with(['childItems', 'itemType']);
        }

        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('code', 'like', "%{$this->searchTerm}%")
                    ->orWhere('title', 'like', "%{$this->searchTerm}%")
                    ->orWhere('file_name', 'like', "%{$this->searchTerm}%")
                    ->orWhere(function($subQuery) {
                        $subQuery->whereRaw("? LIKE CONCAT(code, '%')", [$this->searchTerm]);
                    });
            });
        }

        return $query->orderBy('code')->get();
    }

    public function getMetaItemsProperty()
    {
        return $this->selectedElementItems->filter(function ($item) {
            return $item->is_sub === true;
        });
    }

    public function getStandardItemsProperty()
    {
        return $this->selectedElementItems->filter(function ($item) {
            return $item->is_sub !== true;
        });
    }

    // Méthodes utilitaires
    public function formatFileSize(?int $bytes): string
    {
        if (!$bytes || $bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        $size = $bytes / pow(1024, $power);
        return round($size, 2) . ' ' . $units[$power];
    }

    public function formatDuration(?int $seconds): string
    {
        if (!$seconds) return '-';

        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        return sprintf('%d:%02d', $minutes, $remainingSeconds);
    }

    public function getSelectedElementTypeLabel(): string
    {
        return match($this->selectedType) {
            'fond' => 'Fonds',
            'corpus' => 'Corpus',
            'collection' => 'Collection',
            'item' => 'Item Principal',
            default => 'Élément'
        };
    }

    public function getSelectedElementTypeIcon(): Heroicon
    {
        return match($this->selectedType) {
            'fond' => Heroicon::OutlinedBuildingLibrary,
            'corpus' => Heroicon::OutlinedBookOpen,
            'collection' => Heroicon::OutlinedArchiveBoxArrowDown,
            'item' => Heroicon::OutlinedDocument,
            default => Heroicon::OutlinedDocument
        };
    }

    public function getSelectedElementResourceRoute(string $action = 'view'): ?string
    {
        if (!$this->selectedType || !$this->selectedId) {
            return null;
        }

        $resourceName = match($this->selectedType) {
            'fond' => 'fonds',
            'corpus' => 'corpuses',
            'collection' => 'collections',
            'item' => 'items',
            default => null
        };

        if (!$resourceName) {
            return null;
        }

        return route("filament.mms-admin.resources.{$resourceName}.{$action}", ['record' => $this->selectedId]);
    }

    public function getSelectedItemResourceRoute(string $action = 'view'): ?string
    {
        if (!$this->selectedItemId) {
            return null;
        }

        return route("filament.mms-admin.resources.items.{$action}", ['record' => $this->selectedItemId]);
    }

    // Actions de création contextuelles
    public function createCorpus(): void
    {
        $url = route('filament.mms-admin.resources.corpuses.create');
        if ($this->selectedType === 'fond' && $this->selectedId) {
            $url .= '?fond_id=' . $this->selectedId;
        }
        $this->redirect($url);
    }

    public function createCollection(): void
    {
        $url = route('filament.mms-admin.resources.collections.create');
        if ($this->selectedType === 'corpus' && $this->selectedId) {
            $url .= '?corpus_id=' . $this->selectedId;
        }
        $this->redirect($url);
    }

    public function createItem(): void
    {
        $url = route('filament.mms-admin.resources.items.create');
        if ($this->selectedId) {
            $paramName = match($this->selectedType) {
                'fond' => 'fond_id',
                'corpus' => 'corpus_id',
                'collection' => 'collection_id',
                'item' => 'parent_item_id', // Pour Item Principal -> Item Secondaire
                default => null
            };
            if ($paramName) {
                $url .= "?{$paramName}=" . $this->selectedId;
            }
        }
        $this->redirect($url);
    }

    public function createItemTranslation(): void
    {
        $url = route('filament.mms-admin.resources.items.create');
        if ($this->selectedItemId) {
            $url .= '?parent_item_id=' . $this->selectedItemId . '&item_type=translation';
        }
        $this->redirect($url);
    }

    public function hasChildren(string $type, $model): bool
    {
        return match($type) {
            'fond' => $model->corpuses_count > 0,
            'corpus' => $model->collections_count > 0,
            'collection' => $this->mode === 'collections' ? $model->items_count > 0 : false, // Check mainItems count ideally
            'item' => $model->childItems && $model->childItems->count() > 0,
            default => false
        };
    }

    protected function getViewData(): array
    {
        return [
            'fonds' => $this->mode === 'fonds' ? $this->fonds : collect(),
            'collections' => $this->mode === 'collections' ? $this->collections : collect(),
            'items' => $this->selectedElementItems, // Liste plate unifiée
        ];
    }
}
