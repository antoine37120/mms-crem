<x-filament-panels::page>
    <div x-data="hierarchyExplorer()" class="space-y-4">
        {{-- En-tête avec contrôles et filtres --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    {{ $this->form }}
                </div>

                <div class="flex items-center space-x-2">
                    <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Contrôle densité:</label>
                    <input
                        type="range"
                        min="0"
                        max="100"
                        wire:model.live="density"
                        class="w-24 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer dark:bg-gray-700"
                    >
                </div>
            </div>
        </div>

        {{-- Interface 2 panneaux (1/3 - 2/3) --}}
        <div class="grid grid-cols-3 gap-6 h-[700px]">

            {{-- PANNEAU GAUCHE (1/3) - Arbre hiérarchique épuré --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="overflow-y-auto h-full p-4">
                    @if($fonds->isNotEmpty())
                        @foreach($fonds as $fond)
                            <div class="mb-2" wire:key="fond-{{ $fond->id }}">
                                {{-- Ligne du fonds --}}
                                <div class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer"
                                     wire:click="selectElement('fond', {{ $fond->id }})"
                                     :class="{ 'bg-primary-50 dark:bg-primary-900/50': selectedType === 'fond' && selectedId === {{ $fond->id }} }">

                                    <button
                                        wire:click.stop="toggleFond({{ $fond->id }})"
                                        class="flex-shrink-0 w-4 h-4 mr-2 text-gray-500 hover:text-gray-700">
                                        @if(in_array($fond->id, $expandedFonds))
                                            <span class="text-xs"><x-heroicon-o-chevron-up-down /></span>
                                        @else
                                            <span class="text-xs"><x-heroicon-o-chevron-up-down /></span>
                                        @endif
                                    </button>

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
                                        <span class="text-xs text-primary-600 ml-2">◄</span>
                                    @endif
                                </div>

                                {{-- Corpus du fonds (si expanded) - SANS items directs --}}
                                @if(in_array($fond->id, $expandedFonds))
                                    <div class="ml-6 mt-1 space-y-1">
                                        @foreach($this->getCorpusesForFond($fond->id) as $corpus)
                                            <div wire:key="corpus-{{ $corpus->id }}">
                                                {{-- Ligne du corpus --}}
                                                <div class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer"
                                                     wire:click="selectElement('corpus', {{ $corpus->id }})"
                                                     :class="{ 'bg-primary-50 dark:bg-primary-900/50': selectedType === 'corpus' && selectedId === {{ $corpus->id }} }">

                                                    <button
                                                        wire:click.stop="toggleCorpus({{ $corpus->id }})"
                                                        class="flex-shrink-0 w-4 h-4 mr-2 text-gray-500 hover:text-gray-700">
                                                        @if(in_array($corpus->id, $expandedCorpuses))
                                                            <span class="text-xs"><x-heroicon-o-chevron-up-down /></span>
                                                        @else
                                                            <span class="text-xs"><x-heroicon-o-chevron-up-down /></span>
                                                        @endif
                                                    </button>

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
                                                        <span class="text-xs text-primary-600 ml-2">◄</span>
                                                    @endif
                                                </div>

                                                {{-- Collections du corpus (si expanded) - SANS items directs --}}
                                                @if(in_array($corpus->id, $expandedCorpuses))
                                                    <div class="ml-6 mt-1 space-y-1">
                                                        @foreach($this->getCollectionsForCorpus($corpus->id) as $collection)
                                                            <div wire:key="collection-{{ $collection->id }}">
                                                                {{-- Ligne de la collection --}}
                                                                <div class="flex items-center group hover:bg-gray-50 dark:hover:bg-gray-700 rounded py-1 px-2 cursor-pointer"
                                                                     wire:click="selectElement('collection', {{ $collection->id }})"
                                                                     :class="{ 'bg-primary-50 dark:bg-primary-900/50': selectedType === 'collection' && selectedId === {{ $collection->id }} }">

                                                                    <span class="flex-shrink-0 w-4 h-4 mr-2"></span>

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
                                                                        <span class="text-xs text-primary-600 ml-2">◄</span>
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

                        {{-- Actions de création --}}
                        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-600 space-y-2">
                            <button wire:click="createFond" class="block text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400">
                                Nouveau Fonds
                            </button>
                            @if($selectedType === 'fond')
                                <button wire:click="createCorpus" class="block text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400">
                                    Nouveau Corpus
                                </button>
                            @endif
                            @if($selectedType === 'corpus')
                                <button wire:click="createCollection" class="block text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400">
                                    Nouvelle Collection
                                </button>
                            @endif
                            @if(in_array($selectedType, ['fond', 'corpus', 'collection', 'item']))
                                <button wire:click="createItem" class="block text-sm text-primary-600 hover:text-primary-800 dark:text-primary-400">
                                    Nouvel Item
                                </button>
                            @endif
                        </div>
                    @else
                        <div class="text-center text-gray-500 py-8">
                            <p class="text-sm">Aucun fonds disponible</p>
                        </div>
                    @endif
                </div>
            </div>

            {{-- PANNEAU DROITE (2/3) - Contenu générique --}}
            <div class="col-span-2 space-y-4">
                {{-- En-tête contextuel générique --}}
                @if($selectedElement)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ $this->getSelectedElementTypeLabel() }}: {{ $selectedElement['code'] ?? $selectedElement['file_name'] ?? 'Sans nom' }}
                                </h3>
                                @if(isset($selectedElement['title']) && $selectedElement['title'])
                                    <p class="text-gray-600 dark:text-gray-400">{{ $selectedElement['title'] }}</p>
                                @endif
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    {{ count($this->selectedElementItems) }} items directs
                                    @if($this->selectedElementChildren->isNotEmpty())
                                        • {{ count($this->selectedElementChildren) }} éléments enfants
                                    @endif
                                </p>
                            </div>
                            <div class="flex space-x-2">
                                @if($this->getSelectedElementResourceRoute('view'))
                                    <x-filament::button size="sm" color="primary" tag="a"
                                                        href="{{ $this->getSelectedElementResourceRoute('view') }}" target="_blank">
                                        Voir
                                    </x-filament::button>
                                @endif
                                @if($this->getSelectedElementResourceRoute('edit'))
                                    <x-filament::button size="sm" color="gray" tag="a"
                                                        href="{{ $this->getSelectedElementResourceRoute('edit') }}" target="_blank">
                                        Éditer
                                    </x-filament::button>
                                @endif
                            </div>
                        </div>
                    </div>
                @else
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-8 text-center">
                        <div class="text-gray-400 dark:text-gray-500">
                            <svg class="mx-auto h-12 w-12 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Navigation Hiérarchique</h3>
                            <p class="text-gray-600 dark:text-gray-400">Sélectionnez un élément dans l'arborescence à gauche pour voir ses détails et son contenu</p>
                        </div>
                    </div>
                @endif

                {{-- Items directs (générique) --}}
                @if($this->selectedElementItems->isNotEmpty())
                    {{-- Items principaux --}}
                    @php $mainItems = $this->selectedElementItems->where('is_sub', false); @endphp
                    @if($mainItems->isNotEmpty())
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-600">
                                <h4 class="font-medium text-gray-900 dark:text-gray-100">Items principaux</h4>
                            </div>
                            <div class="p-4 space-y-2">
                                @foreach($mainItems as $item)
                                    <div class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center space-x-2">
                                                @if($item->childItems->count() > 0)
                                                    @if(in_array($item->id, $expandedItems))
                                                        <button wire:click="toggleItem({{ $item->id }})" class="text-xs text-gray-500">▼</button>
                                                    @else
                                                        <button wire:click="toggleItem({{ $item->id }})" class="text-xs text-gray-500">►</button>
                                                    @endif
                                                @else
                                                    <span class="w-4 h-4"></span>
                                                @endif
                                                <div class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate cursor-pointer"
                                                     wire:click="selectElement('item', {{ $item->id }})">
                                                    {{ $item->file_name ?? $item->code }}
                                                </div>
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                {{ $item->file_extension }} • {{ $this->formatFileSize($item->file_size) }}
                                                @if($item->childItems->count() > 0)
                                                    • {{ $item->childItems->count() }} items sub
                                                @endif
                                            </div>

                                            {{-- Items enfants (sub) --}}
                                            @if(in_array($item->id, $expandedItems) && $item->childItems->count() > 0)
                                                <div class="ml-6 mt-2 space-y-1">
                                                    @foreach($item->childItems as $childItem)
                                                        <div class="text-xs text-gray-600 dark:text-gray-400 py-1 px-2 bg-gray-50 dark:bg-gray-700 rounded cursor-pointer"
                                                             wire:click="selectElement('item', {{ $childItem->id }})">
                                                            {{ $childItem->file_name ?? $childItem->code }}
                                                            @if($childItem->itemType)
                                                                <span class="text-gray-500">({{ $childItem->itemType->name }})</span>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <div class="flex space-x-1 ml-3">
                                            <x-filament::icon-button
                                                icon="heroicon-o-eye"
                                                size="sm"
                                                color="primary"
                                                tag="a"
                                                href="{{ route('filament.mms-admin.resources.items.view', ['record' => $item->id]) }}"
                                                target="_blank"
                                            />
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Items secondaires directs --}}
                    @php $secondaryItems = $this->selectedElementItems->where('is_sub', true); @endphp
                    @if($secondaryItems->isNotEmpty())
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700">
                            <div class="px-4 py-3 border-b border-gray-200 dark:border-gray-600">
                                <h4 class="font-medium text-gray-900 dark:text-gray-100">Items secondaires directs</h4>
                            </div>
                            <div class="p-4 space-y-2">
                                @foreach($secondaryItems as $item)
                                    <div class="flex items-center justify-between p-3 border border-gray-200 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <div class="flex-1 min-w-0">
                                            <div class="font-medium text-sm text-gray-900 dark:text-gray-100 truncate cursor-pointer"
                                                 wire:click="selectElement('item', {{ $item->id }})">
                                                {{ $item->file_name ?? $item->code }}
                                            </div>
                                            <div class="text-xs text-gray-500 mt-1">
                                                {{ $item->itemType->name ?? 'Type inconnu' }} • {{ $item->file_extension }} • {{ $this->formatFileSize($item->file_size) }}
                                            </div>
                                        </div>
                                        <div class="flex space-x-1 ml-3">
                                            <x-filament::icon-button
                                                icon="heroicon-o-eye"
                                                size="sm"
                                                color="primary"
                                                tag="a"
                                                href="{{ route('filament.mms-admin.resources.items.view', ['record' => $item->id]) }}"
                                                target="_blank"
                                            />
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    </div>

    @script
    <script>
        Alpine.data('hierarchyExplorer', () => ({
            selectedType: @js($selectedType),
            selectedId: @js($selectedId),
            selectedElement: @js($selectedElement),

            init() {
                // Écouter les événements Livewire
                this.$wire.on('element-selected', (event) => {
                    this.selectedType = event.type;
                    this.selectedId = event.id;
                    this.selectedElement = this.$wire.selectedElement;
                });
            }
        }));
    </script>
    @endscript
</x-filament-panels::page>
