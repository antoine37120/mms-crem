<x-filament-panels::page>
    <div x-data="hierarchyExplorer()" class="space-y-4">
        {{-- Interface 3 colonnes égales (33% - 33% - 33%) --}}
        <div class="grid grid-cols-3 gap-6 h-[90vh]">

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
                     x-on:scroll.throttle.150ms="checkScrollCollections($event.target)">

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

                                    {{-- Pour les collections en mode Collections --}}
                                    <div class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer {{ $selectedType === 'collection' && $selectedId == $collection->id ? 'bg-primary-50 dark:bg-primary-900/50 border border-primary-200' : '' }}"
                                         wire:click="selectElement('collection', {{ $collection->id }})"
                                         @if($selectedType === 'collection' && $selectedId == $collection->id) data-selected-element="true" @endif>

                                        {{-- Toggle pour mainItems (Conditionnel) --}}
                                        @if($this->hasChildren('collection', $collection))
                                            <button
                                                wire:click.stop="toggleCollection({{ $collection->id }})"
                                                class="flex-shrink-0 w-4 h-4 mr-2 text-gray-500 hover:text-gray-700 transition-transform duration-200 {{ in_array($collection->id, $expandedCollections) ? 'rotate-180' : '' }}">
                                                <x-heroicon-o-chevron-up-down class="w-4 h-4" />
                                            </button>
                                        @else
                                            <span class="flex-shrink-0 w-4 h-4 mr-2 flex items-center justify-center">
                                                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>
                                            </span>
                                        @endif

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
                                                    {{-- Pour les main items --}}
                                                    <div class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer {{ $selectedType === 'item' && $selectedId == $mainItem->id ? 'bg-primary-50 dark:bg-primary-900/50 border border-primary-200' : '' }}"
                                                         wire:click="selectElement('item', {{ $mainItem->id }})"
                                                         @if($selectedType === 'item' && $selectedId == $mainItem->id) data-selected-element="true" @endif>

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
                                         @if($selectedType === 'fond' && $selectedId == $fond->id) data-selected-element="true" @endif>

                                        {{-- Icône de dépliant (Conditionnel) --}}
                                        @if($this->hasChildren('fond', $fond))
                                            <button
                                                wire:click.stop="toggleFond({{ $fond->id }})"
                                                class="flex-shrink-0 w-4 h-4 mr-2 text-gray-500 hover:text-gray-700 transition-transform duration-200 {{ in_array($fond->id, $expandedFonds) ? 'rotate-180' : '' }}">
                                                <x-heroicon-o-chevron-up-down class="w-4 h-4" />
                                            </button>
                                        @else
                                            <span class="flex-shrink-0 w-4 h-4 mr-2 flex items-center justify-center">
                                                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>
                                            </span>
                                        @endif

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
                                                         @if($selectedType === 'corpus' && $selectedId == $corpus->id) data-selected-element="true" @endif>

                                                        {{-- Toggle Corpus (Conditionnel) --}}
                                                        @if($this->hasChildren('corpus', $corpus))
                                                            <button
                                                                wire:click.stop="toggleCorpus({{ $corpus->id }})"
                                                                class="flex-shrink-0 w-4 h-4 mr-2 text-gray-500 hover:text-gray-700 transition-transform duration-200 {{ in_array($corpus->id, $expandedCorpuses) ? 'rotate-180' : '' }}">
                                                                <x-heroicon-o-chevron-up-down class="w-4 h-4" />
                                                            </button>
                                                        @else
                                                            <span class="flex-shrink-0 w-4 h-4 mr-2 flex items-center justify-center">
                                                                <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>
                                                            </span>
                                                        @endif

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
                                                                         @if($selectedType === 'collection' && $selectedId == $collection->id) data-selected-element="true" @endif>

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

            {{-- COLONNE 2 (33%) - Détails Élément & Médias Associés --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden flex flex-col">
                <div class="overflow-y-auto flex-1 " x-ref="column2Scroll">
                    @if($selectedType && $selectedId)
                        {{-- EN TÊTE: Informations de l'élément sélectionné en Col 1 --}}
                        <div class="p-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/30">
                            @if($selectedType === 'item')
                                <div class="space-y-4">
                                    <div>
                                        <div class="flex items-center font-medium text-lg text-gray-900 dark:text-gray-100">
                                            <span class="flex-shrink-0 w-5 mr-2 text-primary-600"><x-heroicon-o-document /></span>
                                            <span class="truncate">{{ $selectedElement['code'] ?? 'Sans nom' }}</span>
                                        </div>
                                        @if(isset($selectedElement['title']) && $selectedElement['title'])
                                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $selectedElement['title'] }}</div>
                                        @endif
<div class="text-gray-600 dark:text-gray-400">
                                        <x-filament::badge size="xs" color="primary" class="font-medium px-2 py-0.5" circular>
                                            Item
                                        </x-filament::badge> 
                                        {{ strtoupper($selectedElement['file_extension'] ?? 'N/A') }} • {{ $this->formatFileSize($selectedElement['file_size'] ?? 0) }}
                                        </div>
                                        @if(isset($selectedElement['duration']) && $selectedElement['duration'])
                                            <div class="text-gray-600 dark:text-gray-400">
                                                Durée: {{ $this->formatDuration($selectedElement['duration']) }}
                                            </div>
                                        @endif

                                    </div>

                                    <div class="pt-0">
                                        {{ $this->mediaInfolist }}
                                    </div>

                                    <div class="flex flex-wrap gap-2 pt-4 justify-end border-t border-gray-100 dark:border-gray-700 mt-4">
                                        @if($this->getSelectedElementResourceRoute('view'))
                                            <x-filament::button size="sm" color="gray" icon="heroicon-m-eye" tag="a"
                                                                href="{{ $this->getSelectedElementResourceRoute('view') }}" target="_blank">
                                                Voir
                                            </x-filament::button>
                                        @endif
                                        @if(!empty($selectedElement['file_path']) && \Illuminate\Support\Facades\Storage::disk('original_medias')->exists($selectedElement['file_path']))
                                            <x-filament::button size="sm" color="primary" tag="a"
                                                                href="{{ asset('storage/' . $selectedElement['file_path']) }}" target="_blank">
                                                Télécharger
                                            </x-filament::button>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="space-y-3">
                                    <div>
                                        <div class="flex items-center font-medium text-gray-900 dark:text-gray-100">
                                            <span class="flex-shrink-0 w-4 mr-2 text-primary-600">{{ \Filament\Support\generate_icon_html($this->getSelectedElementTypeIcon()) }}</span>
                                            <span class="truncate">{{ $selectedElement['code'] ?? 'Sans nom' }}</span>
                                        </div>
                                        @if(isset($selectedElement['title']) && $selectedElement['title'])
                                            <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $selectedElement['title'] }}</div>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <x-filament::badge size="xs" color="primary" class="font-medium px-2 py-0.5" circular>
                                            {{ $this->getSelectedElementTypeLabel() }}
                                        </x-filament::badge>
                                        <div class="text-xs text-gray-500 dark:text-gray-400">
                                            @if($selectedType === 'fond')
                                                {{ $selectedElement['corpuses_count'] ?? 0 }} corpus • {{ $selectedElement['secondary_items_count'] ?? 0 }} medias associés
                                            @elseif($selectedType === 'corpus')
                                                {{ $selectedElement['collections_count'] ?? 0 }} collections • {{ $selectedElement['secondary_items_count'] ?? 0 }} medias associés
                                            @elseif($selectedType === 'collection')
                                                {{ $selectedElement['main_items_count'] ?? 0 }} items, {{ $selectedElement['secondary_items_count'] ?? 0 }} medias associés
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap gap-2 pt-2 justify-end">
                                        @php
                                            $createRoute = null;
                                            $createLabel = null;
                                            if ($selectedType === 'fond') {
                                                $createRoute = route('filament.mms-admin.resources.corpuses.create', ['fond_id' => $selectedId]);
                                                $createLabel = 'Créer un corpus';
                                            } elseif ($selectedType === 'corpus') {
                                                $createRoute = route('filament.mms-admin.resources.collections.create', ['corpus_id' => $selectedId]);
                                                $createLabel = 'Créer une collection';
                                            } elseif ($selectedType === 'collection') {
                                                $createRoute = route('filament.mms-admin.resources.items.create', ['collection_id' => $selectedId]);
                                                $createLabel = 'Créer un item';
                                            }
                                        @endphp
                                        @if($createRoute)
                                            <x-filament::button size="sm" color="primary" icon="heroicon-m-plus" tag="a"
                                                                href="{{ $createRoute }}">
                                                {{ $createLabel }}
                                            </x-filament::button>
                                        @endif
                                        @if($this->getSelectedElementResourceRoute('view'))
                                            <x-filament::button size="sm" color="gray" icon="heroicon-m-eye" tag="a"
                                                                href="{{ $this->getSelectedElementResourceRoute('view') }}" target="_blank">
                                                Voir
                                            </x-filament::button>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- LISTE: Médias Associés directs --}}
                        @if($directMedia->isNotEmpty())
                            <div class="mb-4">
                                <div class="p-2 mb-2 border-b border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50 rounded-b">
                                    <h3 class="font-medium text-xs text-gray-500 uppercase tracking-wider">
                                        Médias associés
                                    </h3>
                                </div>
                                <div class="space-y-1 px-2">
                                    @foreach($directMedia as $item)
                                        @include('filament.pages.partials.hierarchy-item-row', ['item' => $item])
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="text-center text-gray-500 py-4">
                                <p class="text-xs">Aucun média associé direct</p>
                            </div>
                        @endif

                        {{-- LISTE ARBORESCENTE: Items (Uniquement Mode Fonds > Collection) --}}
                        @if($mode === 'fonds' && $selectedType === 'collection')
                            @if($collectionItems->isNotEmpty())
                                <div>
                                    <div class="p-2 mb-2 border-y border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-900/50">
                                        <h3 class="font-medium text-xs text-gray-500 uppercase tracking-wider">
                                            Items
                                        </h3>
                                    </div>
                                    <div class="space-y-1 px-2 pb-4">
                                        @foreach($collectionItems as $item)
                                            <div class="mb-1" wire:key="col2-item-{{ $item->id }}">
                                                <div class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer {{ $selectedItemId == $item->id ? 'bg-primary-50 dark:bg-primary-900/50 border border-primary-200' : '' }}"
                                                     wire:click="selectItem({{ $item->id }})"
                                                     @if($selectedItemId == $item->id) data-selected-item="true" @endif>
                                                    
                                                    {{-- Chevron (Conditionnel) --}}
                                                    @if($this->hasChildren('item', $item))
                                                        <button
                                                            wire:click.stop="toggleColumn2Item({{ $item->id }})"
                                                            class="flex-shrink-0 w-4 h-4 mr-2 text-gray-500 hover:text-gray-700 transition-transform duration-200 {{ in_array($item->id, $expandedColumn2Items) ? 'rotate-180' : '' }}">
                                                            <x-heroicon-o-chevron-up-down class="w-4 h-4" />
                                                        </button>
                                                    @else
                                                        <span class="flex-shrink-0 w-4 h-4 mr-2 flex items-center justify-center">
                                                            <span class="w-1.5 h-1.5 bg-gray-400 rounded-full"></span>
                                                        </span>
                                                    @endif
                                                    
                                                    <div class="flex-1 min-w-0">
                                                        <div class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">
                                                            {{ $item->code }}
                                                        </div>
                                                        @if($item->title)
                                                            <div class="text-xs text-gray-500 truncate">{{ $item->title }}</div>
                                                        @endif
                                                    </div>
                                                </div>

                                                {{-- Médias de cet item --}}
                                                @if(in_array($item->id, $expandedColumn2Items))
                                                    <div class="ml-6 border-l-2 border-gray-100 dark:border-gray-700 pl-1 mt-1 space-y-1">
                                                        @foreach($item->childItems as $media)
                                                            @if($media->is_sub)
                                                                @include('filament.pages.partials.hierarchy-item-row', ['item' => $media])
                                                            @endif
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="text-center text-gray-500 py-4 border-t border-gray-100 dark:border-gray-700">
                                    <p class="text-xs">Aucun item</p>
                                </div>
                            @endif
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
            <div class="overflow-hidden flex flex-col h-full bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                <div class="overflow-y-auto flex-1 p-4 space-y-4">
                    @if($selectedItem)
                        <div class="space-y-4">
                            <div>
                                <div class="flex items-center font-medium text-lg text-gray-900 dark:text-gray-100">
                                    <span class="flex-shrink-0 w-5 mr-2"><x-heroicon-o-document /></span>
                                    <span class="truncate">{{ $selectedItem['code'] ?? 'Sans nom' }}</span>
                                </div>
                                @if(isset($selectedItem['title']) && $selectedItem['title'])
                                    <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $selectedItem['title'] }}</div>
                                @endif
                            </div>

                            <div class="text-sm space-y-1 bg-gray-50 dark:bg-gray-900/50 p-3 rounded border border-gray-100 dark:border-gray-700">
                                @if($selectedItem['is_sub'])
                                    <div class="font-medium text-xs text-primary-600 mb-2 uppercase tracking-wide">Média Associé</div>
                                @else
                                    <div class="font-medium text-xs text-gray-500 mb-2 uppercase tracking-wide">Item</div>
                                @endif
                                <div class="text-gray-600 dark:text-gray-400">
                                    {{ strtoupper($selectedItem['file_extension'] ?? 'N/A') }} • {{ $this->formatFileSize($selectedItem['file_size'] ?? 0) }}
                                </div>
                                @if(isset($selectedItem['duration']) && $selectedItem['duration'])
                                    <div class="text-gray-600 dark:text-gray-400">
                                        Durée: {{ $this->formatDuration($selectedItem['duration']) }}
                                    </div>
                                @endif
                            </div>

                            {{-- Infolist pour les détails techniques et lecteur --}}
                            <div class="pt-2">
                                {{ $this->mediaInfolist }}
                            </div>

                            <div class="flex flex-wrap gap-2 pt-4 justify-end border-t border-gray-100 dark:border-gray-700 mt-4">
                                @if($this->getSelectedItemResourceRoute('view'))
                                    <x-filament::button size="sm" color="gray" icon="heroicon-m-eye" tag="a"
                                                        href="{{ $this->getSelectedItemResourceRoute('view') }}" target="_blank">
                                        Voir
                                    </x-filament::button>
                                @endif
                                @if(!empty($selectedItem['file_path']) && \Illuminate\Support\Facades\Storage::disk('original_medias')->exists($selectedItem['file_path']))
                                    <x-filament::button size="sm" color="primary" tag="a"
                                                        href="{{ asset('storage/' . $selectedItem['file_path']) }}" target="_blank">
                                        Télécharger
                                    </x-filament::button>
                                @endif
                            </div>
                        </div>
                    @else
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
            mode: @js($this->mode),
            hasMoreCollections: @js($this->hasMoreCollections),
            hasMoreCollectionsBefore: @js($this->hasMoreCollectionsBefore),
            loadingMoreCollections: false,
            loadingMoreCollectionsBefore: false,
            lastScrollTop: 0,
            scrollCheckEnabled: false,

            init() {
                // Attendre que Livewire ait fini de rendre le DOM
                this.$nextTick(() => {
                    setTimeout(() => {
                        this.scrollToSelectedElements();
                        // Activer la détection du scroll après le scroll initial
                        setTimeout(() => {
                            this.scrollCheckEnabled = true;
                            // Mémoriser la position initiale du scroll
                            if (this.$refs.column1Scroll) {
                                this.lastScrollTop = this.$refs.column1Scroll.scrollTop;
                            }
                        }, 300);
                    }, 150);
                });

                // Écouter les changements de state
                this.$watch('$wire.selectedType', (value) => {
                    this.selectedType = value;
                    this.$nextTick(() => this.scrollToSelectedElements());
                });

                this.$watch('$wire.selectedId', (value) => {
                    console.log('hohoohohoh');
                    this.selectedId = value;
                    this.$nextTick(() => this.scrollToSelectedElements());
                });

                this.$watch('$wire.selectedItemId', (value) => {
                    this.selectedItemId = value;
                    this.$nextTick(() => this.scrollToSelectedElements());
                });

                this.$watch('$wire.mode', (value) => {
                    this.mode = value;
                    // Réinitialiser l'état du scroll lors du changement de mode
                    this.scrollCheckEnabled = false;
                    this.lastScrollTop = 0;
                    this.$nextTick(() => {
                        setTimeout(() => {
                            this.scrollCheckEnabled = true;
                            if (this.$refs.column1Scroll) {
                                this.lastScrollTop = this.$refs.column1Scroll.scrollTop;
                            }
                        }, 300);
                    });
                });

                this.$watch('$wire.hasMoreCollections', (value) => {
                    this.hasMoreCollections = value;
                });

                this.$watch('$wire.hasMoreCollectionsBefore', (value) => {
                    this.hasMoreCollectionsBefore = value;
                });
            },

            scrollToSelectedElements() {
                // Scroll vers l'élément sélectionné dans la colonne 1
                const selectedElement = document.querySelector('[data-selected-element="true"]');
                console.log(selectedElement);
                const column1Scroll = this.$refs.column1Scroll;

                if (selectedElement && column1Scroll) {
                    const elementRect = selectedElement.getBoundingClientRect();
                    const containerRect = column1Scroll.getBoundingClientRect();

                    const isVisible = (
                        elementRect.top >= containerRect.top &&
                        elementRect.bottom <= containerRect.bottom
                    );

                    if (!isVisible) {
                        selectedElement.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center',
                            inline: 'nearest'
                        });
                    }
                }

                // Scroll vers l'item sélectionné dans la colonne 2
                const selectedItem = document.querySelector('[data-selected-item="true"]');
                const column2Scroll = this.$refs.column2Scroll;

                if (selectedItem && column2Scroll) {
                    selectedItem.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center',
                        inline: 'nearest'
                    });
                }
            },

            checkScrollCollections(el) {
                // L'infinite scroll ne fonctionne qu'en mode collections
                if (this.mode !== 'collections') return;

                // Ne pas vérifier tant que l'initialisation n'est pas terminée
                if (!this.scrollCheckEnabled) return;

                const threshold = 50;
                const currentScrollTop = el.scrollTop;
                const scrollDirection = currentScrollTop > this.lastScrollTop ? 'down' : 'up';

                // Mémoriser la position pour la prochaine comparaison
                this.lastScrollTop = currentScrollTop;

                // Scroll vers le bas - charger les suivants
                const bottomDistance = el.scrollHeight - el.scrollTop - el.clientHeight;
                if (scrollDirection === 'down' && bottomDistance < threshold && this.hasMoreCollections && !this.loadingMoreCollections) {
                    this.loadingMoreCollections = true;
                    this.$wire.loadMoreCollections().then(() => {
                        this.loadingMoreCollections = false;
                    });
                }

                // Scroll vers le haut - charger les précédents
                // Seulement si on scrolle vers le haut ET qu'on est proche du sommet
                if (scrollDirection === 'up' && currentScrollTop < threshold && this.hasMoreCollectionsBefore && !this.loadingMoreCollectionsBefore) {
                    this.loadingMoreCollectionsBefore = true;
                    const previousScrollHeight = el.scrollHeight;

                    this.$wire.loadMoreCollectionsBefore().then(() => {
                        this.$nextTick(() => {
                            const newScrollHeight = el.scrollHeight;
                            el.scrollTop = newScrollHeight - previousScrollHeight + currentScrollTop;
                            this.loadingMoreCollectionsBefore = false;
                        });
                    });
                }
            }
        }));
    </script>
    @endscript
</x-filament-panels::page>
