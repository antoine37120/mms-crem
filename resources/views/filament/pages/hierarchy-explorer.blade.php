<x-filament-panels::page>
    <div x-data="hierarchyExplorer()" class="space-y-4">
        {{-- Interface 3 colonnes égales (33% - 33% - 33%) --}}
        <div class="grid grid-cols-3 gap-6 h-[700px]">

            {{-- COLONNE 1 (33%) - Arbre hiérarchique principal --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col">
                {{-- En-tête avec Sélecteur de Mode et Recherche --}}
                <div class="p-4 border-b border-gray-200 dark:border-gray-600 space-y-3 bg-gray-50 dark:bg-gray-900/50">

                    {{-- Sélecteur de Mode --}}
                    <div class="flex rounded-md shadow-sm" role="group">
                        <button type="button"
                            wire:click="setMode('collections')"
                            class="flex-1 px-4 py-2 text-sm font-medium border rounded-l-lg focus:z-10 focus:ring-2 focus:ring-primary-500 focus:text-primary-700 dark:focus:ring-primary-500 dark:focus:text-white
                            {{ $this->mode === 'collections'
                                ? 'bg-primary-600 text-white border-primary-600 hover:bg-primary-700'
                                : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:hover:bg-gray-600'
                            }}">
                            Collections
                        </button>
                        <button type="button"
                            wire:click="setMode('fonds')"
                            class="flex-1 px-4 py-2 text-sm font-medium border rounded-r-lg focus:z-10 focus:ring-2 focus:ring-primary-500 focus:text-primary-700 dark:focus:ring-primary-500 dark:focus:text-white
                            {{ $this->mode === 'fonds'
                                ? 'bg-primary-600 text-white border-primary-600 hover:bg-primary-700'
                                : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50 dark:bg-gray-700 dark:border-gray-600 dark:text-white dark:hover:bg-gray-600'
                            }}">
                            Fonds
                        </button>
                    </div>

                    {{-- Champ recherche --}}
                    <div>
                        {{ $this->form }}
                    </div>
                </div>

                {{-- Contenu Arbre --}}
                <div class="overflow-y-auto flex-1 p-2"
                     x-ref="column1Scroll"
                     x-data="infiniteScrollCollections()"
                     x-on:scroll.throttle.150ms="checkScroll()">

                    {{-- MODE COLLECTIONS --}}
                    @if($this->mode === 'collections')
                        {{-- Indicateur de chargement en HAUT --}}
                        @if($hasMoreCollectionsBefore)
                            <div wire:loading.flex wire:target="loadMoreCollectionsBefore"
                                 class="flex items-center justify-center py-4 gap-2">
                                <svg class="animate-spin h-5 w-5 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-sm text-gray-500 dark:text-gray-400">Chargement des collections précédentes...</span>
                            </div>
                            <div wire:loading.remove wire:target="loadMoreCollectionsBefore"
                                 class="text-center text-xs text-gray-400 py-2">
                                ↑ Défiler vers le haut pour charger plus
                            </div>
                        @endif
                        @if($collections->isNotEmpty())
                            @foreach($collections as $collection)
                                <div class="mb-1" wire:key="col-mode-{{ $collection->id }}">
                                    <div class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer {{ $selectedType === 'collection' && $selectedId == $collection->id ? 'bg-primary-50 dark:bg-primary-900/50 border border-primary-200' : '' }}"
                                         wire:click="selectElement('collection', {{ $collection->id }})"
                                         @if($selectedType === 'collection' && $selectedId == $collection->id) x-ref="selectedElement" @endif>

                                        {{-- Toggle pour mainItems --}}
                                        <button
                                            wire:click.stop="toggleCollection({{ $collection->id }})"
                                            class="flex-shrink-0 w-4 h-4 mr-2 text-gray-500 hover:text-gray-700 transition-transform duration-200 {{ in_array($collection->id, $expandedCollections) ? 'rotate-180' : '' }}">
                                            <x-heroicon-o-chevron-up-down class="w-4 h-4" />
                                        </button>

                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">
                                                {{ $collection->code }}
                                            </div>
                                            @if($collection->title)
                                                <div class="text-xs text-gray-500 truncate">{{ $collection->title }}</div>
                                            @endif
                                        </div>

                                        @if($selectedType === 'collection' && $selectedId == $collection->id)
                                            <span class="text-sm text-primary-600 ml-2">◄</span>
                                        @endif
                                    </div>

                                    {{-- Main Items (si expanded) --}}
                                    @if(in_array($collection->id, $expandedCollections))
                                        <div class="ml-6 border-l-2 border-gray-100 dark:border-gray-700 pl-1 mt-1">
                                            @foreach($this->getMainItemsForCollection($collection->id) as $mainItem)
                                                <div wire:key="main-item-{{ $mainItem->id }}">
                                                    <div class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer {{ $selectedType === 'item' && $selectedId == $mainItem->id ? 'bg-primary-50 dark:bg-primary-900/50 border border-primary-200' : '' }}"
                                                         wire:click="selectElement('item', {{ $mainItem->id }})"
                                                         @if($selectedType === 'item' && $selectedId == $mainItem->id) x-ref="selectedElement" @endif>

                                                        <span class="flex-shrink-0 w-4 h-4 mr-2 flex items-center justify-center">
                                                            <x-heroicon-o-document class="w-3 h-3 text-gray-400" />
                                                        </span>

                                                        <div class="flex-1 min-w-0">
                                                            <div class="text-sm text-gray-700 dark:text-gray-300 truncate">
                                                                {{ $mainItem->code }}
                                                            </div>
                                                        </div>

                                                        @if($selectedType === 'item' && $selectedId == $mainItem->id)
                                                            <span class="text-sm text-primary-600 ml-2">◄</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach

                            {{-- Indicateur statique quand il y a plus à charger (visible quand pas en chargement) --}}
                            @if($this->hasMoreCollections)
                                    <div wire:loading.flex wire:target="loadMoreCollections"
                                         class="flex items-center justify-center py-4 gap-2">
                                        <svg class="animate-spin h-5 w-5 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Chargement de 30 collections...</span>
                                    </div>

                                    {{-- Indicateur quand pas en chargement --}}
                                    <div wire:loading.remove wire:target="loadMoreCollections"
                                         class="flex items-center justify-center py-2 text-xs text-gray-400">
                                        <span>Défiler pour charger plus...</span>
                                    </div>
                            @endif
                        @else
                            <div class="text-center text-gray-500 py-8">
                                <p class="text-sm">Aucune collection trouvée</p>
                            </div>
                        @endif

                    {{-- MODE FONDS --}}
                    @elseif($this->mode === 'fonds')
                        @if($fonds->isNotEmpty())
                            @foreach($fonds as $fond)
                                <div class="mb-1" wire:key="fond-{{ $fond->id }}">
                                    {{-- Ligne du fonds --}}
                                    <div class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer {{ $selectedType === 'fond' && $selectedId == $fond->id ? 'bg-primary-50 dark:bg-primary-900/50 border border-primary-200' : '' }}"
                                         wire:click="selectElement('fond', {{ $fond->id }})"
                                         @if($selectedType === 'fond' && $selectedId == $fond->id) x-ref="selectedElement" @endif>

                                        {{-- Icône de dépliant --}}
                                        <button
                                            wire:click.stop="toggleFond({{ $fond->id }})"
                                            class="flex-shrink-0 w-4 h-4 mr-2 text-gray-500 hover:text-gray-700 transition-transform duration-200 {{ in_array($fond->id, $expandedFonds) ? 'rotate-180' : '' }}">
                                            <x-heroicon-o-chevron-up-down class="w-4 h-4" />
                                        </button>

                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">
                                                {{ $fond->code }}
                                            </div>
                                            @if($fond->title)
                                                <div class="text-xs text-gray-500 truncate">{{ $fond->title }}</div>
                                            @endif
                                        </div>

                                        @if($selectedType === 'fond' && $selectedId == $fond->id)
                                            <span class="text-sm text-primary-600 ml-2">◄</span>
                                        @endif
                                    </div>

                                    {{-- Corpus du fonds (si expanded) --}}
                                    @if(in_array($fond->id, $expandedFonds))
                                        <div class="ml-6 border-l-2 border-gray-100 dark:border-gray-700 pl-1">
                                            @foreach($this->getCorpusesForFond($fond->id) as $corpus)
                                                <div wire:key="corpus-{{ $corpus->id }}">
                                                    <div class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer {{ $selectedType === 'corpus' && $selectedId == $corpus->id ? 'bg-primary-50 dark:bg-primary-900/50 border border-primary-200' : '' }}"
                                                         wire:click="selectElement('corpus', {{ $corpus->id }})"
                                                         @if($selectedType === 'corpus' && $selectedId == $corpus->id) x-ref="selectedElement" @endif>

                                                        <button
                                                            wire:click.stop="toggleCorpus({{ $corpus->id }})"
                                                            class="flex-shrink-0 w-4 h-4 mr-2 text-gray-500 hover:text-gray-700 transition-transform duration-200 {{ in_array($corpus->id, $expandedCorpuses) ? 'rotate-180' : '' }}">
                                                            <x-heroicon-o-chevron-up-down class="w-4 h-4" />
                                                        </button>

                                                        <div class="flex-1 min-w-0">
                                                            <div class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">
                                                                {{ $corpus->code }}
                                                            </div>
                                                            @if($corpus->title)
                                                                <div class="text-xs text-gray-500 truncate">{{ $corpus->title }}</div>
                                                            @endif
                                                        </div>

                                                        @if($selectedType === 'corpus' && $selectedId == $corpus->id)
                                                            <span class="text-sm text-primary-600 ml-2">◄</span>
                                                        @endif
                                                    </div>

                                                    {{-- Collections du corpus (si expanded) --}}
                                                    @if(in_array($corpus->id, $expandedCorpuses))
                                                        <div class="ml-6 border-l-2 border-gray-100 dark:border-gray-700 pl-1">
                                                            @foreach($this->getCollectionsForCorpus($corpus->id) as $collection)
                                                                <div wire:key="collection-{{ $collection->id }}">
                                                                    <div class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer {{ $selectedType === 'collection' && $selectedId == $collection->id ? 'bg-primary-50 dark:bg-primary-900/50 border border-primary-200' : '' }}"
                                                                         wire:click="selectElement('collection', {{ $collection->id }})"
                                                                         @if($selectedType === 'collection' && $selectedId == $collection->id) x-ref="selectedElement" @endif>

                                                                        <span class="flex-shrink-0 w-4 h-4 mr-2 flex items-center justify-center">
                                                                            <span class="w-1 h-1 bg-gray-400 rounded-full"></span>
                                                                        </span>

                                                                        <div class="flex-1 min-w-0">
                                                                            <div class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">
                                                                                {{ $collection->code }}
                                                                            </div>
                                                                            @if($collection->title)
                                                                                <div class="text-xs text-gray-500 truncate">{{ $collection->title }}</div>
                                                                            @endif
                                                                        </div>

                                                                        @if($selectedType === 'collection' && $selectedId == $collection->id)
                                                                            <span class="text-sm text-primary-600 ml-2">◄</span>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        @else
                            <div class="text-center text-gray-500 py-8">
                                <p class="text-sm">Aucun fonds disponible</p>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            {{-- COLONNE 2 (33%) - Liste des Items (Secondaires ou Enfants) --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col">
                {{-- <div class="p-3 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50">
                    <h3 class="font-medium text-sm text-gray-700 dark:text-gray-200">
                        @if($selectedType === 'collection' && $this->mode === 'collections')
                            Items Secondaires
                        @elseif($selectedType === 'item')
                            Items Enfants
                        @else
                            Items
                        @endif
                    </h3>
                </div> --}}

                <div class="overflow-y-auto flex-1 " x-ref="column2Scroll">
                    @if($selectedType && $selectedId)
                        @php
                            $items = $this->selectedElementItems;
                            $isCollectionInFondsMode = $this->mode === 'fonds' && $selectedType === 'collection';
                            $metaItems = $this->metaItems;
                            $standardItems = $this->standardItems;
                        @endphp

                        @if($metaItems->isNotEmpty() || $standardItems->isNotEmpty())

                            {{-- Cas Spécial: Mode Fonds + Collection (Séparation Secondary / Main) --}}


                                {{-- Items Secondaires --}}
                                @if($metaItems->isNotEmpty())
                                    <div class="mb-4">
                                        <div class="p-2 mb-2 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50 rounded">
                                            <h3 class="font-medium text-sm text-gray-700 dark:text-gray-200">
                                                Items Secondaires
                                            </h3>
                                        </div>
                                        <div class="space-y-1">
                                            @foreach($metaItems as $item)
                                                @include('filament.pages.partials.hierarchy-item-row', ['item' => $item])
                                            @endforeach
                                        </div>
                                    </div>
                                @endif

                                {{-- Items Principaux --}}
                                @if($standardItems->isNotEmpty())
                                    <div>
                                        <div class="p-2 mb-2 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50 rounded">
                                            <h3 class="font-medium text-sm text-gray-700 dark:text-gray-200">
                                                Items
                                            </h3>
                                        </div>
                                        <div class="space-y-1">
                                            @foreach($standardItems as $item)
                                                @include('filament.pages.partials.hierarchy-item-row', ['item' => $item])
                                            @endforeach
                                        </div>
                                    </div>
                                @endif


                                {{-- Cas Standard (Liste plate)
                                <div class="p-3 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50">
                                    <h3 class="font-medium text-sm text-gray-700 dark:text-gray-200">
                                        Items Secondaires
                                    </h3>
                                </div>
                                <div class="space-y-1">
                                    @foreach($items as $item)
                                        @include('filament.pages.partials.hierarchy-item-row', ['item' => $item])
                                    @endforeach
                                </div> --}}


                        @else
                            <div class="text-center text-gray-500 py-8">
                                <p class="text-sm">Aucun item disponible</p>
                            </div>
                        @endif
                    @else
                        <div class="h-full flex items-center justify-center text-center text-gray-500">
                            <div>
                                <x-heroicon-o-arrow-left class="mx-auto h-8 w-8 mb-2 text-gray-300" />
                                <p class="text-sm text-gray-600 dark:text-gray-400">Sélectionnez un élément</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- COLONNE 3 (33%) - Informations et Actions --}}
            <div class="overflow-hidden flex flex-col h-full">
                <div class="overflow-y-auto flex-1 space-y-4">

                    {{-- Section 1: Informations sélection Colonne 1 --}}
                    @if($selectedElement)
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg p-4 shadow-sm">
                            <div class="space-y-3">
                                <div>
                                    <div class="flex items-center font-medium text-gray-900 dark:text-gray-100">
                                        <span class="flex-shrink-0 w-4 mr-2">{{ \Filament\Support\generate_icon_html($this->getSelectedElementTypeIcon()) }}</span>
                                        <span class="truncate">{{ $selectedElement['code'] ?? 'Sans nom' }}</span>
                                    </div>
                                    @if(isset($selectedElement['title']) && $selectedElement['title'])
                                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $selectedElement['title'] }}</div>
                                    @endif
                                </div>

                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    @if($selectedType === 'fond')
                                        {{ $selectedElement['corpuses_count'] ?? 0 }} corpus • {{ $selectedElement['items_count'] ?? 0 }} items
                                    @elseif($selectedType === 'corpus')
                                        {{ $selectedElement['collections_count'] ?? 0 }} collections • {{ $selectedElement['items_count'] ?? 0 }} items
                                    @else
                                        {{ $selectedElement['main_items_count'] ?? 0 }} items, {{ $selectedElement['secondary_items_count'] ?? 0 }} items associés
                                    @endif
                                </div>

                                <div class="flex flex-wrap gap-2 pt-2 justify-end border-t border-gray-100 dark:border-gray-700 mt-2">
                                    @if($this->getSelectedElementResourceRoute('view'))
                                        <x-filament::button size="xs" color="gray" tag="a"
                                                            href="{{ $this->getSelectedElementResourceRoute('view') }}" target="_blank">
                                            Voir
                                        </x-filament::button>
                                    @endif
                                    @if($this->getSelectedElementResourceRoute('edit'))
                                        <x-filament::button size="xs" color="primary" tag="a"
                                                            href="{{ $this->getSelectedElementResourceRoute('edit') }}" target="_blank">
                                            Éditer
                                        </x-filament::button>
                                    @endif
                                </div>

                                {{-- Actions de création --}}
                                <div class="flex flex-wrap justify-end gap-2 pt-2">
                                    @if($selectedType === 'fond')
                                        <x-filament::button size="xs" color="success" wire:click="createCorpus">
                                            + Corpus
                                        </x-filament::button>
                                    @elseif($selectedType === 'corpus')
                                        <x-filament::button size="xs" color="success" wire:click="createCollection">
                                            + Collection
                                        </x-filament::button>
                                    @endif
                                    <x-filament::button size="xs" color="success" wire:click="createItem">
                                        + Item
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Section 2: Informations sélection Colonne 2 --}}
                    @if($selectedItem)
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg p-4 shadow-sm">

                            <div class="space-y-3">
                                <div>
                                    <div class="flex items-center font-medium text-gray-900 dark:text-gray-100">
                                        <span class="flex-shrink-0 w-4 mr-2"><x-heroicon-o-document /></span>
                                        <span class="truncate">{{ $selectedItem['code'] ?? 'Sans nom' }}</span>
                                    </div>
                                    @if(isset($selectedItem['title']) && $selectedItem['title'])
                                        <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $selectedItem['title'] }}</div>
                                    @endif
                                </div>

                                <div class="text-sm space-y-1">
                                    <div class="text-gray-600 dark:text-gray-400">
                                        {{ strtoupper($selectedItem['file_extension'] ?? 'N/A') }} • {{ $this->formatFileSize($selectedItem['file_size'] ?? 0) }}
                                    </div>
                                    @if(isset($selectedItem['duration']) && $selectedItem['duration'])
                                        <div class="text-gray-600 dark:text-gray-400">
                                            Durée: {{ $this->formatDuration($selectedItem['duration']) }}
                                        </div>
                                    @endif
                                </div>

                                <div class="flex flex-wrap gap-2 pt-2 justify-end border-t border-gray-100 dark:border-gray-700 mt-2">
                                    @if($this->getSelectedItemResourceRoute('view'))
                                        <x-filament::button size="xs" color="gray" tag="a"
                                                            href="{{ $this->getSelectedItemResourceRoute('view') }}" target="_blank">
                                            Voir
                                        </x-filament::button>
                                    @endif
                                    @if($selectedItem['file_path'] ?? false)
                                        <x-filament::button size="xs" color="primary" tag="a"
                                                            href="{{ asset('storage/' . $selectedItem['file_path']) }}" target="_blank">
                                            Télécharger
                                        </x-filament::button>
                                    @endif
                                </div>

                                <div class="flex flex-wrap gap-2 pt-2 justify-end">
                                    <x-filament::button size="xs" color="success" wire:click="createItemTranslation">
                                        + Traduction
                                    </x-filament::button>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- État vide --}}
                    @if(!$selectedElement && !$selectedItem)
                        <div class="h-full flex items-center justify-center text-center text-gray-500">
                            <div>
                                <x-heroicon-o-information-circle class="mx-auto h-12 w-12 mb-4 text-gray-300" />
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Détails</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Sélectionnez un élément pour voir les détails</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @script
    <script>
        Alpine.data('hierarchyExplorer', () => ({
            selectedType: @js($selectedType),
            selectedId: @js($selectedId),
            selectedItemId: @js($selectedItemId),

            init() {
                // Scroll initial vers les éléments sélectionnés
                this.$nextTick(() => {
                    this.scrollToSelectedElements();
                });

                // Écouter les changements de state
                this.$watch('$wire.selectedType', (value) => {
                    this.selectedType = value;
                });

                this.$watch('$wire.selectedId', (value) => {
                    this.selectedId = value;
                });

                this.$watch('$wire.selectedItemId', (value) => {
                    this.selectedItemId = value;
                });
            },
            scrollToSelectedElements() {
                // Scroll vers l'élément sélectionné dans la colonne 1
                if (this.$refs.selectedElement && this.$refs.column1Scroll) {
                    this.$refs.selectedElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center',
                        inline: 'nearest'
                    });
                }

                // Scroll vers l'item sélectionné dans la colonne 2
                if (this.$refs.selectedItem && this.$refs.column2Scroll) {
                    this.$refs.selectedItem.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center',
                        inline: 'nearest'
                    });
                }
            }
        }));


        Alpine.data('infiniteScrollCollections', () => ({
            checkScroll() {
                if (@js($this->mode) !== 'collections') return;

                const el = this.$el;
                const threshold = 100;

                // Scroll vers le bas
                const bottomDistance = el.scrollHeight - el.scrollTop - el.clientHeight;
                if (bottomDistance < threshold) {
                    const hasMore = @js($this->hasMoreCollections);
                    const isLoading = @js($this->loadingMoreCollections);

                    if (hasMore && !isLoading) {
                        this.$wire.loadMoreCollections();
                    }
                }

                // Scroll vers le haut
                if (el.scrollTop < threshold) {
                    const hasMoreBefore = @js($this->hasMoreCollectionsBefore);
                    const isLoadingBefore = @js($this->loadingMoreCollectionsBefore);

                    if (hasMoreBefore && !isLoadingBefore) {
                        // Sauvegarder la hauteur avant chargement pour maintenir la position
                        const previousScrollHeight = el.scrollHeight;

                        this.$wire.loadMoreCollectionsBefore().then(() => {
                            this.$nextTick(() => {
                                // Restaurer la position de scroll après ajout d'éléments en haut
                                const newScrollHeight = el.scrollHeight;
                                el.scrollTop = newScrollHeight - previousScrollHeight + el.scrollTop;
                            });
                        });
                    }
                }
            }
        }));
    </script>
    @endscript
</x-filament-panels::page>
