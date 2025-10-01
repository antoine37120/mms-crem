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
use Illuminate\Contracts\Support\Htmlable;

class HierarchyExplorer extends Page implements HasForms
{
    use InteractsWithForms;

    // Configuration de base
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-folder';
    protected static string|null|\UnitEnum $navigationGroup = 'Explorateur';
    protected static ?string $navigationLabel = 'Vue Hiérarchique';
    protected static ?string $title = 'Explorateur Hiérarchique';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'hierarchy-explorer';

    protected string $view = 'filament.pages.hierarchy-explorer';

    // État de recherche
    public string $searchTerm = '';

    // États de sélection
    public ?string $selectedType = null; // 'fond', 'corpus', 'collection'
    public ?int $selectedId = null;
    public ?array $selectedElement = null;

    // État de sélection item (colonne 2)
    public ?int $selectedItemId = null;
    public ?array $selectedItem = null;

    // États d'expansion dans les arbres
    public array $expandedFonds = [];
    public array $expandedCorpuses = [];
    public array $expandedCollections = [];
    public array $expandedItems = [];

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
        // Initialiser avec le premier fonds disponible
        $defaultFond = Fond::orderBy('code')->first();

        $this->form->fill([
            'searchTerm' => $this->searchTerm,
        ]);

        if ($defaultFond) {
            $this->selectElement('fond', $defaultFond->id);
            $this->expandedFonds[] = $defaultFond->id;
        }
    }

    public function getTitle(): string|Htmlable
    {
        return 'Explorateur Hiérarchique';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Navigation dans l\'arborescence complète';
    }

    // Sélection d'éléments dans la colonne 1
    public function selectElement(string $type, int $id): void
    {
        $this->selectedType = $type;
        $this->selectedId = $id;
        $this->selectedItemId = null; // Reset sélection item
        $this->selectedItem = null;

        switch ($type) {
            case 'fond':
                $element = Fond::with(['corpuses', 'items'])->find($id);
                if (!in_array($id, $this->expandedFonds)) {
                    $this->expandedFonds[] = $id;
                }
                break;
            case 'corpus':
                $element = Corpus::with(['collections', 'items', 'fond'])->find($id);
                if (!in_array($id, $this->expandedCorpuses)) {
                    $this->expandedCorpuses[] = $id;
                }
                break;
            case 'collection':
                $element = Collection::with(['items', 'corpus.fond'])->find($id);
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

    // Actions de basculement d'expansion pour la colonne 1
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

    // Actions de basculement d'expansion pour la colonne 2
    public function toggleItem(int $itemId): void
    {
        if (in_array($itemId, $this->expandedItems)) {
            $this->expandedItems = array_diff($this->expandedItems, [$itemId]);
        } else {
            $this->expandedItems[] = $itemId;
        }
    }

    // Propriétés computed pour la colonne 1
    public function getFondsProperty()
    {
        $query = Fond::withCount(['corpuses', 'items']);

        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('code', 'like', "%{$this->searchTerm}%")
                    // Priorité 2 : Fonds contenant le terme dans le code (pas au début)
                    ->orWhere(function($subQuery) {
                        $subQuery->whereRaw("? LIKE CONCAT(code, '%')", [$this->searchTerm]);
                    })

                // Priorité 3 : Fonds contenant le terme dans le titre
                    ->orWhere('title', 'like', "%{$this->searchTerm}%");

            });
        }

        return $query->orderBy('code')->get();
    }

    public function getCorpusesForFond(?int $fondId)
    {
        if (!$fondId) return collect();

        $query = Corpus::where('fond_id', $fondId)->withCount(['collections', 'items']);

        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('code', 'like', "%{$this->searchTerm}%")
                    ->orWhere('title', 'like', "%{$this->searchTerm}%")
                    // Inclure les corpus dont le texte recherché commence par leur code
                    ->orWhere(function($subQuery) {
                        $subQuery->whereRaw("? LIKE CONCAT(code, '%')", [$this->searchTerm]);
                    })
                ;
            });
        }

        return $query->orderBy('code')->get();
    }

    public function getCollectionsForCorpus(?int $corpusId)
    {
        if (!$corpusId) return collect();

        $query = Collection::where('corpus_id', $corpusId)->withCount('items');

        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('code', 'like', "%{$this->searchTerm}%")
                    ->orWhere('title', 'like', "%{$this->searchTerm}%")
                    // Inclure les collections dont le texte recherché commence par leur code
                    ->orWhere(function($subQuery) {
                        $subQuery->whereRaw("? LIKE CONCAT(code, '%')", [$this->searchTerm]);
                    })
                ;
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

        $modelClass = match($this->selectedType) {
            'fond' => Fond::class,
            'corpus' => Corpus::class,
            'collection' => Collection::class,
            default => null
        };

        if (!$modelClass) {
            return collect();
        }

        $query = Item::where('itemable_type', $modelClass)
            ->where('itemable_id', $this->selectedId)
            ->with(['childItems', 'itemType']);
            //->whereNull('item_type_id'); // Seulement les items principaux

        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('code', 'like', "%{$this->searchTerm}%")
                    // Inclure les items dont le texte recherché commence par leur code
                    ->orWhere(function($subQuery) {
                        $subQuery->whereRaw("? LIKE CONCAT(code, '%')", [$this->searchTerm]);
                    })

                    ->orWhere('title', 'like', "%{$this->searchTerm}%")
                    ->orWhere('file_name', 'like', "%{$this->searchTerm}%");
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
            default => 'Élément'
        };
    }

    public function getSelectedElementTypeIcon(): string
    {
        return match($this->selectedType) {
            'fond' => '🏛️',
            'corpus' => '📚',
            'collection' => '📦',
            default => '📄'
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
            'collection' => false,
            'item' => $model->childItems && $model->childItems->count() > 0,
            default => false
        };
    }

    protected function getViewData(): array
    {
        return [
            'fonds' => $this->fonds,
            'metaItems' => $this->metaItems,
            'standardItems' => $this->standardItems,
        ];
    }
}
