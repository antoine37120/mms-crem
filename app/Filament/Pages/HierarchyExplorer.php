<?php

namespace App\Filament\Pages;

use App\Models\Fond;
use App\Models\Corpus;
use App\Models\Collection;
use App\Models\Item;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Filament\Facades\Filament;

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

    // États de l'interface
    public string $searchTerm = '';
    public bool $compactMode = false;
    public int $density = 50; // 0-100, contrôle l'espacement

    // État de sélection générique
    public ?string $selectedType = null; // 'fond', 'corpus', 'collection', 'item'
    public ?int $selectedId = null;
    public ?array $selectedElement = null;

    // États d'expansion dans l'arbre
    public array $expandedFonds = [];
    public array $expandedCorpuses = [];
    public array $expandedCollections = [];
    public array $expandedItems = [];

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                TextInput::make('searchTerm')
                    ->placeholder('Rechercher...')
                    ->prefixIcon('heroicon-o-magnifying-glass')
                    ->live(debounce: 300),

                Toggle::make('compactMode')
                    ->label('Mode compact')
                    ->live(),
            ])
            ->columns(2);
    }

    public function mount(): void
    {
        $defaultFond = Fond::orderBy('code')->first();

        $this->form->fill([
            'searchTerm' => $this->searchTerm,
            'compactMode' => $this->compactMode,
        ]);

        // Initialiser avec le premier fonds si disponible
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

    // Sélection générique
    public function selectElement(string $type, int $id): void
    {
        $this->selectedType = $type;
        $this->selectedId = $id;

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
                if (!in_array($id, $this->expandedCollections)) {
                    $this->expandedCollections[] = $id;
                }
                break;
            case 'item':
                $element = Item::with(['childItems', 'itemType', 'itemable'])->find($id);
                if (!in_array($id, $this->expandedItems)) {
                    $this->expandedItems[] = $id;
                }
                break;
            default:
                $element = null;
        }

        $this->selectedElement = $element ? $element->toArray() : null;
        $this->dispatch('element-selected', type: $type, id: $id);
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

    // Méthodes pour les données de l'arbre (gauche)
    public function getFondsProperty()
    {
        $query = Fond::withCount(['corpuses', 'items']);

        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('code', 'like', "%{$this->searchTerm}%")
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
                    ->orWhere('title', 'like', "%{$this->searchTerm}%");
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
                    ->orWhere('title', 'like', "%{$this->searchTerm}%");
            });
        }

        return $query->orderBy('code')->get();
    }

    // Propriété computed générique pour les items du panneau droit
    public function getSelectedElementItemsProperty()
    {
        if (!$this->selectedType || !$this->selectedId) {
            return collect();
        }

        $modelClass = match($this->selectedType) {
            'fond' => Fond::class,
            'corpus' => Corpus::class,
            'collection' => Collection::class,
            'item' => Item::class,
            default => null
        };

        if (!$modelClass) {
            return collect();
        }

        $query = Item::where('itemable_type', $modelClass)
            ->where('itemable_id', $this->selectedId)
            ->with(['childItems', 'itemType']);

        if ($this->searchTerm) {
            $query->where(function ($q) {
                $q->where('code', 'like', "%{$this->searchTerm}%")
                    ->orWhere('title', 'like', "%{$this->searchTerm}%")
                    ->orWhere('file_name', 'like', "%{$this->searchTerm}%");
            });
        }

        return $query->orderBy('code')->get();
    }

    // Propriété computed pour les enfants hiérarchiques du panneau droit
    public function getSelectedElementChildrenProperty()
    {
        if (!$this->selectedType || !$this->selectedId) {
            return collect();
        }

        switch ($this->selectedType) {
            case 'fond':
                return $this->getCorpusesForFond($this->selectedId);
            case 'corpus':
                return $this->getCollectionsForCorpus($this->selectedId);
            case 'collection':
                // Les collections n'ont pas d'enfants hiérarchiques directs
                return collect();
            case 'item':
                // Les items n'ont pas d'enfants hiérarchiques directs
                return collect();
            default:
                return collect();
        }
    }

    // Actions de création contextuelles
    public function createFond(): void
    {
        $this->redirect(route('filament.mms-admin.resources.fonds.create'));
    }

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
                'item' => 'parent_item_id',
                default => null
            };
            if ($paramName) {
                $url .= "?{$paramName}=" . $this->selectedId;
            }
        }
        $this->redirect($url);
    }

    // Méthodes helpers
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

    public function getSelectedElementTypeLabel(): string
    {
        return match($this->selectedType) {
            'fond' => 'Fonds',
            'corpus' => 'Corpus',
            'collection' => 'Collection',
            'item' => 'Item',
            default => 'Élément'
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

    protected function getViewData(): array
    {
        return [
            'fonds' => $this->fonds,
        ];
    }
}
