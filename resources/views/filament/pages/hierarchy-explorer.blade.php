<x-filament-panels::page>
    <div x-data="hierarchyExplorer()" class="space-y-4">
        {{-- Interface 3 colonnes égales (33% - 33% - 33%) --}}
        <div class="grid grid-cols-3 gap-6 h-[700px]">

            {{-- COLONNE 1 (33%) - Arbre hiérarchique principal --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                {{-- Champ recherche intégré --}}
                <div class="p-4 border-b border-gray-200 dark:border-gray-600">
                    {{ $this->form }}
                </div>

                <div class="overflow-y-auto p-4" style="height: calc(100% - 80px);" x-ref="column1Scroll">
                @if($fonds->isNotEmpty())
                        @foreach($fonds as $fond)
                            <div class="mb-1" wire:key="fond-{{ $fond->id }}">
                                {{-- Ligne du fonds --}}
                                <div class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer {{ $selectedType === 'fond' && $selectedId == $fond->id ? 'bg-primary-50 dark:bg-primary-900/50 border border-primary-200' : '' }}"
                                     wire:click="selectElement('fond', {{ $fond->id }})"
                                     @if($selectedType === 'fond' && $selectedId == $fond->id) x-ref="selectedElement" @endif>

                                {{-- Icône de dépliant ou point --}}
                                    @if($fond->corpuses_count > 0)
                                        <button
                                            wire:click.stop="toggleFond({{ $fond->id }})"
                                            class="flex-shrink-0 w-4 h-4 mr-2 text-gray-500 hover:text-gray-700 transition-transform duration-200 {{ in_array($fond->id, $expandedFonds) ? 'rotate-180' : '' }}">
                                            <x-heroicon-o-chevron-up-down class="w-4 h-4" />
                                        </button>
                                    @else
                                        <span class="flex-shrink-0 w-4 h-4 mr-2 flex items-center justify-center">
                                            <span class="w-1 h-1 bg-gray-400 rounded-full"></span>
                                        </span>
                                    @endif

                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">
                                            {{ $fond->code }}
                                        </div>
                                        @if($fond->title)
                                            <div class="text-xs text-gray-500 truncate">{{ $fond->title }}</div>
                                        @endif
                                        <div class="text-xs text-gray-400">
                                            {{ $fond->corpuses_count }} corpus • {{ $fond->items_count }} items
                                        </div>
                                    </div>

                                    @if($selectedType === 'fond' && $selectedId == $fond->id)
                                        <span class="text-sm text-primary-600 ml-2">◄</span>
                                    @endif
                                </div>

                                {{-- Corpus du fonds (si expanded) --}}
                                @if(in_array($fond->id, $expandedFonds))
                                    <div class="ml-6">
                                        @foreach($this->getCorpusesForFond($fond->id) as $corpus)
                                            <div wire:key="corpus-{{ $corpus->id }}">
                                                <div class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer {{ $selectedType === 'corpus' && $selectedId == $corpus->id ? 'bg-primary-50 dark:bg-primary-900/50 border border-primary-200' : '' }}"
                                                     wire:click="selectElement('corpus', {{ $corpus->id }})"
                                                     @if($selectedType === 'corpus' && $selectedId == $corpus->id) x-ref="selectedElement" @endif>

                                                {{-- Icône de dépliant ou point --}}
                                                    @if($corpus->collections_count > 0)
                                                        <button
                                                            wire:click.stop="toggleCorpus({{ $corpus->id }})"
                                                            class="flex-shrink-0 w-4 h-4 mr-2 text-gray-500 hover:text-gray-700 transition-transform duration-200 {{ in_array($corpus->id, $expandedCorpuses) ? 'rotate-180' : '' }}">
                                                            <x-heroicon-o-chevron-up-down class="w-4 h-4" />
                                                        </button>
                                                    @else
                                                        <span class="flex-shrink-0 w-4 h-4 mr-2 flex items-center justify-center">
                                                            <span class="w-1 h-1 bg-gray-400 rounded-full"></span>
                                                        </span>
                                                    @endif

                                                    <div class="flex-1 min-w-0">
                                                        <div class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">
                                                            {{ $corpus->code }}
                                                        </div>
                                                        @if($corpus->title)
                                                            <div class="text-xs text-gray-500 truncate">{{ $corpus->title }}</div>
                                                        @endif
                                                        <div class="text-xs text-gray-400">
                                                            {{ $corpus->collections_count }} collections • {{ $corpus->items_count }} items
                                                        </div>
                                                    </div>

                                                    @if($selectedType === 'corpus' && $selectedId == $corpus->id)
                                                        <span class="text-sm text-primary-600 ml-2">◄</span>
                                                    @endif
                                                </div>

                                                {{-- Collections du corpus (si expanded) --}}
                                                @if(in_array($corpus->id, $expandedCorpuses))
                                                    <div class="ml-6">
                                                        @foreach($this->getCollectionsForCorpus($corpus->id) as $collection)
                                                            <div wire:key="collection-{{ $collection->id }}">
                                                                <div class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer {{ $selectedType === 'collection' && $selectedId == $collection->id ? 'bg-primary-50 dark:bg-primary-900/50 border border-primary-200' : '' }}"
                                                                     wire:click="selectElement('collection', {{ $collection->id }})"
                                                                     @if($selectedType === 'collection' && $selectedId == $collection->id) x-ref="selectedElement" @endif>


                                                                {{-- Point simple (pas d'enfants hiérarchiques) --}}
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
                                                                        <div class="text-xs text-gray-400">
                                                                            {{ $collection->items_count }} items
                                                                        </div>
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
                </div>
            </div>

            {{-- COLONNE 2 (33%) - Arbre Items hiérarchique --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-y-auto h-full" x-ref="column2Scroll">
                @if($selectedType && $selectedId)
                        <div class="p-4">
                            {{-- Titre contextuel --}}
                            <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                                {{ $this->getSelectedElementTypeLabel() }}: {{ $selectedElement['code'] ?? 'Sans nom' }}
                                @if($selectedItemId)
                                    <span class="text-primary-600">◄</span>
                                @endif
                            </div>

                            @php
                                $items = $this->selectedElementItems;
                                $metaItems = $items->filter(fn($item) => $item->is_sub === true);
                                $standardItems = $items->filter(fn($item) => $item->is_sub !== true);
                            @endphp

                            {{-- Section Meta Items --}}
                            @if($metaItems->isNotEmpty())
                                <div class="mb-2">
                                    <h4 class="font-medium text-sm text-gray-700 dark:text-gray-300 mb-1 border-b border-gray-200 pb-1">
                                        Meta Items
                                    </h4>
                                    <div class="">
                                        @foreach($metaItems as $item)
                                            @php $hasChildren = $item->childItems && $item->childItems->count() > 0; @endphp
                                            <div wire:key="meta-item-{{ $item->id }}" class="ml-2">
                                                <div class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer {{ $selectedItemId == $item->id ? 'bg-primary-50 dark:bg-primary-900/50 border border-primary-200' : '' }}"
                                                     wire:click="selectItem({{ $item->id }})"
                                                     @if($selectedItemId == $item->id) x-ref="selectedItem" @endif>

                                                {{-- Icône de dépliant ou point --}}
                                                    @if($hasChildren)
                                                        <button
                                                            wire:click.stop="toggleItem({{ $item->id }})"
                                                            class="flex-shrink-0 w-4 h-4 mr-2 text-gray-500 hover:text-gray-700 transition-transform duration-200 {{ in_array($item->id, $expandedItems) ? 'rotate-180' : '' }}">
                                                            <x-heroicon-o-chevron-up-down class="w-4 h-4" />
                                                        </button>
                                                    @else
                                                        <span class="flex-shrink-0 w-4 h-4 mr-2 flex items-center justify-center">
                                                            <span class="w-1 h-1 bg-gray-400 rounded-full"></span>
                                                        </span>
                                                    @endif

                                                    <div class="flex-1 min-w-0">
                                                        <div class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">
                                                            {{ $item->code }}
                                                        </div>
                                                        <div class="text-xs text-gray-500">
                                                            {{ $item->file_extension }} • {{ $this->formatFileSize($item->file_size) }}
                                                            @if($hasChildren)
                                                                • {{ $item->childItems->count() }} enfants
                                                            @endif
                                                        </div>
                                                    </div>

                                                    @if($selectedItemId == $item->id)
                                                        <span class="text-sm text-primary-600 ml-2">◄</span>
                                                    @endif
                                                </div>

                                                {{-- Items enfants (si expanded) --}}
                                                @if($hasChildren && in_array($item->id, $expandedItems))
                                                    <div class="ml-6">
                                                        @foreach($item->childItems as $childItem)
                                                            <div wire:key="child-item-{{ $childItem->id }}"
                                                                 class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer {{ $selectedItemId == $childItem->id ? 'bg-primary-50 dark:bg-primary-900/50' : '' }}"
                                                                 wire:click="selectItem({{ $childItem->id }})"
                                                                 @if($selectedItemId == $childItem->id) x-ref="selectedItem" @endif>
                                                            <div class="text-xs text-gray-600 dark:text-gray-400 truncate">
                                                                    {{ $childItem->code }}
                                                                    @if($childItem->itemType)
                                                                        <span class="text-gray-500">({{ $childItem->itemType->name }})</span>
                                                                    @endif
                                                                    @if($selectedItemId == $childItem->id)
                                                                        <span class="text-primary-600 ml-2">◄</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Section Items Standards --}}
                            @if($standardItems->isNotEmpty())
                                <div>
                                    <h4 class="font-medium text-sm text-gray-700 dark:text-gray-300 mb-1 border-b border-gray-200 pb-1">
                                        Items
                                    </h4>
                                    <div class="">
                                        @foreach($standardItems as $item)
                                            @php $hasChildren = $item->childItems && $item->childItems->count() > 0; @endphp
                                            <div wire:key="standard-item-{{ $item->id }}" class="ml-2">
                                                <div class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer {{ $selectedItemId == $item->id ? 'bg-primary-50 dark:bg-primary-900/50 border border-primary-200' : '' }}"
                                                     wire:click="selectItem({{ $item->id }})"
                                                     @if($selectedItemId == $item->id) x-ref="selectedItem" @endif>

                                                    {{-- Icône de dépliant ou point --}}
                                                    @if($hasChildren)
                                                        <button
                                                            wire:click.stop="toggleItem({{ $item->id }})"
                                                            class="flex-shrink-0 w-4 h-4 mr-2 text-gray-500 hover:text-gray-700 transition-transform duration-200 {{ in_array($item->id, $expandedItems) ? 'rotate-180' : '' }}">
                                                            <x-heroicon-o-chevron-up-down class="w-4 h-4" />
                                                        </button>
                                                    @else
                                                        <span class="flex-shrink-0 w-4 h-4 mr-2 flex items-center justify-center">
                                                            <span class="w-1 h-1 bg-gray-400 rounded-full"></span>
                                                        </span>
                                                    @endif

                                                    <div class="flex-1 min-w-0">
                                                        <div class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate">
                                                            {{ $item->code }}
                                                        </div>
                                                        <div class="text-xs text-gray-500">
                                                            {{ $item->file_extension }} • {{ $this->formatFileSize($item->file_size) }}
                                                            @if($hasChildren)
                                                                • {{ $item->childItems->count() }} enfants
                                                            @endif
                                                        </div>
                                                    </div>

                                                    @if($selectedItemId == $item->id)
                                                        <span class="text-sm text-primary-600 ml-2">◄</span>
                                                    @endif
                                                </div>

                                                {{-- Items enfants (si expanded) --}}
                                                @if($hasChildren && in_array($item->id, $expandedItems))
                                                    <div class="ml-6">
                                                        @foreach($item->childItems as $childItem)
                                                            <div wire:key="child-item-{{ $childItem->id }}"
                                                                 class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer {{ $selectedItemId == $childItem->id ? 'bg-primary-50 dark:bg-primary-900/50' : '' }}"
                                                                 wire:click="selectItem({{ $childItem->id }})"
                                                                 @if($selectedItemId == $item->id) x-ref="selectedItem" @endif>
                                                                <div class="text-xs text-gray-600 dark:text-gray-400 truncate">
                                                                    {{ $childItem->code }}
                                                                    @if($childItem->itemType)
                                                                        <span class="text-gray-500">({{ $childItem->itemType->name }})</span>
                                                                    @endif
                                                                    @if($selectedItemId == $childItem->id)
                                                                        <span class="text-primary-600 ml-2">◄</span>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- État vide pour les items --}}
                            @if($metaItems->isEmpty() && $standardItems->isEmpty())
                                <div class="text-center text-gray-500 py-8">
                                    <p class="text-sm">Aucun item dans cet élément</p>
                                </div>
                            @endif
                        </div>
                    @else
                        {{-- État vide --}}
                        <div class="h-full flex items-center justify-center text-center text-gray-500">
                            <div>
                                <svg class="mx-auto h-12 w-12 mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                                </svg>
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Items Hiérarchiques</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Sélectionnez un élément à gauche pour voir ses items</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            {{-- COLONNE 3 (33%) - Informations et Actions --}}
            <div class="overflow-hidden">
                <div class="overflow-y-auto h-full space-y-4">

                    {{-- Section 1: Informations sélection Colonne 1 --}}
                    @if($selectedElement)
                        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-600 rounded-lg p-4 shadow-sm">
                            <div class="space-y-3">
                                <div>
                                    <div class="flex items-center font-medium text-gray-900 dark:text-gray-100">
                                        <span class="flex-shrink-0 w-4 mr-2">{{ \Filament\Support\generate_icon_html($this->getSelectedElementTypeIcon()) }}</span> {{ $selectedElement['code'] ?? 'Sans nom' }}
                                    </div>
                                    @if(isset($selectedElement['title']) && $selectedElement['title'])
                                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ $selectedElement['title'] }}</div>
                                    @endif
                                </div>

                                <div class="text-sm text-gray-600 dark:text-gray-400">
                                    @if($selectedType === 'fond')
                                        {{ $selectedElement['corpuses_count'] ?? 0 }} corpus • {{ $selectedElement['items_count'] ?? 0 }} items
                                    @elseif($selectedType === 'corpus')
                                        {{ $selectedElement['collections_count'] ?? 0 }} collections • {{ $selectedElement['items_count'] ?? 0 }} items
                                    @else
                                        {{ $selectedElement['items_count'] ?? 0 }} items
                                    @endif
                                </div>

                                <div class="flex flex-wrap gap-2 pt-2 justify-end">
                                    @if($this->getSelectedElementResourceRoute('view'))
                                        <x-filament::button size="xs" color="primary" tag="a"
                                                            href="{{ $this->getSelectedElementResourceRoute('view') }}" target="_blank">
                                            Voir
                                        </x-filament::button>
                                    @endif
                                    @if($this->getSelectedElementResourceRoute('edit'))
                                        <x-filament::button size="xs" color="gray" tag="a"
                                                            href="{{ $this->getSelectedElementResourceRoute('edit') }}" target="_blank">
                                            Éditer
                                        </x-filament::button>
                                    @endif
                                </div>

                                <div class="flex flex-wrap justify-end gap-2 pt-2 border-t border-gray-200">
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
                                        <span class="flex-shrink-0 w-4 mr-2"><x-heroicon-o-document /></span> {{ $selectedItem['code'] ?? 'Sans nom' }}
                                    </div>
                                    @if(isset($selectedItem['title']) && $selectedItem['title'])
                                        <div class="text-sm text-gray-600 dark:text-gray-400">{{ $selectedItem['title'] }}</div>
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

                                <div class="flex flex-wrap gap-2 pt-2 justify-end">
                                    @if($this->getSelectedItemResourceRoute('view'))
                                        <x-filament::button size="xs" color="primary" tag="a"
                                                            href="{{ $this->getSelectedItemResourceRoute('view') }}" target="_blank">
                                            Voir
                                        </x-filament::button>
                                    @endif
                                    @if($selectedItem['file_path'] ?? false)
                                        <x-filament::button size="xs" color="success" tag="a"
                                                            href="{{ asset('storage/' . $selectedItem['file_path']) }}" target="_blank">
                                            Télécharger
                                        </x-filament::button>
                                    @endif
                                </div>

                                <div class="flex flex-wrap gap-2 pt-2 border-t border-gray-200 justify-end">
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
                                <svg class="mx-auto h-12 w-12 mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">Informations & Actions</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Sélectionnez des éléments dans les colonnes de navigation pour voir les détails et actions disponibles</p>
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
    </script>
    @endscript
</x-filament-panels::page>
