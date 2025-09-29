
<x-filament-panels::page>
    <div x-data="hierarchyExplorer()" class="space-y-6">
        {{-- Barre d'actions globales --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center space-x-3">
                    {{-- Select Fonds --}}
                    <div class="flex items-center space-x-2">
                        {{-- Select Fonds avec composant Filament --}}
                        <div class="flex items-center space-x-2 min-w-[250px]">
                            {{ $this->form }}
                        </div>

                    </div>


                    <button @click="refresh()"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <x-heroicon-o-arrow-path class="w-4 h-4 mr-2"/>
                        Actualiser
                    </button>

                    <button @click="toggleStats()"
                            class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <x-heroicon-o-chart-bar class="w-4 h-4 mr-2"/>
                        Statistiques
                    </button>
                </div>

                <div class="flex items-center">
                    <div class="relative">
                        <x-heroicon-o-magnifying-glass class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400"/>
                        <input x-model="searchTerm"
                               @input.debounce.300ms="search()"
                               type="text"
                               placeholder="Rechercher..."
                               class="pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-gray-700 dark:text-gray-200">
                    </div>
                </div>
            </div>
        </div>

        {{-- Interface '4' colonnes --}}
        <div class="grid grid-cols-3 gap-4 h-[600px]">
            {{-- Colonne 1: Corpus --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 border-b border-gray-200 dark:border-gray-600">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <x-heroicon-o-book-open class="w-5 h-5 mr-2 text-gray-500"/>
                            <h3 class="font-medium text-gray-900 dark:text-gray-100">CORPUS</h3>
                        </div>
                        <button x-show="selectedFond" @click="createCorpus()" class="text-primary-600 hover:text-primary-800">
                            <x-heroicon-o-plus class="w-4 h-4"/>
                        </button>
                    </div>
                </div>

                <div class="p-4 overflow-y-auto h-full">
                    {{-- Indicateur de chargement --}}
                    <div x-show="loadingCorpuses" class="flex items-center justify-center py-8">
                        <div class="flex items-center space-x-2 text-gray-500">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm">Chargement des corpus...</span>
                        </div>
                    </div>
                    {{-- État vide --}}
                    <div x-show="!selectedFond && !loadingCorpuses" class="text-center text-gray-500 py-8">
                        <x-heroicon-o-building-library class="w-12 h-12 mx-auto mb-2 text-gray-300"/>
                        <p class="text-sm">Sélectionnez un fonds pour voir les corpus</p>
                    </div>
                    {{-- Liste des corpus --}}
                    <div x-show="!loadingCorpuses && selectedFond">
                        <template x-for="corpus in corpuses" :key="corpus.id">
                            <div @click="selectCorpus(corpus)"
                                 :class="{'bg-primary-50 dark:bg-primary-900/50 border-primary-200 dark:border-primary-700': selectedCorpus?.id === corpus.id}"
                                 class="group p-4 mb-3 border border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-900 dark:text-gray-100" x-text="corpus.code"></div>
                                        <div class="text-sm text-gray-500 mt-1" x-text="`${corpus.collections_count} collections, ${corpus.items_count || 0} items`"></div>
                                    </div>
                                    <div class="flex items-center space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button @click.stop="openModal('corpus', corpus)"
                                                title="Voir détails"
                                                class="p-1 text-gray-400 hover:text-primary-600">
                                            <x-heroicon-o-information-circle class="w-4 h-4"/>
                                        </button>
                                        <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-400"/>
                                    </div>
                                </div>
                                <div x-show="corpus.title" class="text-sm text-gray-600 dark:text-gray-400 mt-2 truncate" x-text="corpus.title"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Colonne 2: Collections --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 border-b border-gray-200 dark:border-gray-600">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <x-heroicon-o-archive-box class="w-5 h-5 mr-2 text-gray-500"/>
                            <h3 class="font-medium text-gray-900 dark:text-gray-100">COLLECTIONS</h3>
                        </div>
                        <button x-show="selectedCorpus" @click="createCollection()" class="text-primary-600 hover:text-primary-800">
                            <x-heroicon-o-plus class="w-4 h-4"/>
                        </button>
                    </div>
                </div>

                <div class="p-4 overflow-y-auto h-full">
                    {{-- Indicateur de chargement --}}
                    <div x-show="loadingCollections" class="flex items-center justify-center py-8">
                        <div class="flex items-center space-x-2 text-gray-500">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm">Chargement des collections...</span>
                        </div>
                    </div>
                    {{-- État vide --}}
                    <div x-show="!selectedCorpus && !loadingCollections" class="text-center text-gray-500 py-8">
                        <x-heroicon-o-book-open class="w-12 h-12 mx-auto mb-2 text-gray-300"/>
                        <p class="text-sm">Sélectionnez un corpus pour voir les collections</p>
                    </div>
                    {{-- Liste des collections --}}
                    <div x-show="!loadingCollections && selectedCorpus">
                        <template x-for="collection in collections" :key="collection.id">
                            <div @click="selectCollection(collection)"
                                 :class="{'bg-primary-50 dark:bg-primary-900/50 border-primary-200 dark:border-primary-700': selectedCollection?.id === collection.id}"
                                 class="group p-4 mb-3 border border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="font-medium text-gray-900 dark:text-gray-100" x-text="collection.code"></div>
                                        <div class="text-sm text-gray-500 mt-1" x-text="`${collection.items_count} items`"></div>
                                    </div>
                                    <div class="flex items-center space-x-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button @click.stop="openModal('collection', collection)"
                                                title="Voir détails"
                                                class="p-1 text-gray-400 hover:text-primary-600">
                                            <x-heroicon-o-information-circle class="w-4 h-4"/>
                                        </button>
                                        <x-heroicon-o-chevron-right class="w-4 h-4 text-gray-400"/>
                                    </div>
                                </div>
                                <div x-show="collection.title" class="text-sm text-gray-600 dark:text-gray-400 mt-2 truncate" x-text="collection.title"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Colonne 3: Items --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 border-b border-gray-200 dark:border-gray-600">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <x-heroicon-o-musical-note class="w-5 h-5 mr-2 text-gray-500"/>
                            <h3 class="font-medium text-gray-900 dark:text-gray-100">ITEMS</h3>
                        </div>
                        <button x-show="selectedCollection" @click="createItem()" class="text-primary-600 hover:text-primary-800">
                            <x-heroicon-o-plus class="w-4 h-4"/>
                        </button>
                    </div>
                </div>

                <div class="p-4 overflow-y-auto h-full">
                    {{-- Indicateur de chargement --}}
                    <div x-show="loadingItems" class="flex items-center justify-center py-8">
                        <div class="flex items-center space-x-2 text-gray-500">
                            <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span class="text-sm">Chargement des items...</span>
                        </div>
                    </div>
                    {{-- État vide --}}
                    <div x-show="!selectedCollection && !loadingItems" class="text-center text-gray-500 py-8">
                        <x-heroicon-o-archive-box class="w-12 h-12 mx-auto mb-2 text-gray-300"/>
                        <p class="text-sm">Sélectionnez une collection pour voir les items</p>
                    </div>
                    {{-- Liste des items --}}
                    <div x-show="!loadingItems && selectedCollection">
                        <template x-for="item in items" :key="item.id">
                            <div @click="selectItem(item)"
                                 :class="{'bg-primary-50 dark:bg-primary-900/50 border-primary-200 dark:border-primary-700': selectedItem?.id === item.id}"
                                 class="group p-4 mb-3 border border-gray-200 dark:border-gray-600 rounded-lg cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1 min-w-0">
                                        <div class="font-medium text-gray-900 dark:text-gray-100 truncate" x-text="item.file_name"></div>
                                        <div class="text-sm text-gray-500 flex items-center space-x-2 mt-1">
                                            <span x-text="item.formatted_file_size"></span>
                                            <span x-show="item.duration" x-text="item.formatted_duration"></span>
                                        </div>
                                    </div>
                                    <div class="flex space-x-1 ml-2 opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button @click.stop="openModal('item', item)"
                                                title="Voir détails"
                                                class="p-1 text-gray-400 hover:text-primary-600">
                                            <x-heroicon-o-information-circle class="w-4 h-4"/>
                                        </button>
                                        <button @click.stop="viewItem(item)"
                                                title="Voir"
                                                class="p-1 text-gray-400 hover:text-primary-600">
                                            <x-heroicon-o-eye class="w-4 h-4"/>
                                        </button>
                                        <button @click.stop="editItem(item)"
                                                title="Éditer"
                                                class="p-1 text-gray-400 hover:text-yellow-600">
                                            <x-heroicon-o-pencil class="w-4 h-4"/>
                                        </button>
                                        <button @click.stop="downloadItem(item)"
                                                title="Télécharger"
                                                class="p-1 text-gray-400 hover:text-green-600">
                                            <x-heroicon-o-arrow-down-tray class="w-4 h-4"/>
                                        </button>
                                    </div>
                                </div>
                                <div x-show="item.title" class="text-sm text-gray-600 dark:text-gray-400 mt-2 truncate" x-text="item.title"></div>
                            </div>
                        </template>

                    </div>
            </div>
        </div>

        {{-- Modals Filament --}}
        <x-filament::modal id="hierarchy-details-modal" slide-over width="2xl">
            <x-slot name="heading">
                <div class="flex items-center space-x-3">
                    <div x-show="modalType === 'fond'" class="flex items-center">
                        <x-heroicon-o-building-library class="w-6 h-6 mr-2 text-primary-600"/>
                        <span x-text="modalData?.code"></span>
                    </div>
                    <div x-show="modalType === 'corpus'" class="flex items-center">
                        <x-heroicon-o-book-open class="w-6 h-6 mr-2 text-primary-600"/>
                        <span x-text="modalData?.code"></span>
                    </div>
                    <div x-show="modalType === 'collection'" class="flex items-center">
                        <x-heroicon-o-archive-box class="w-6 h-6 mr-2 text-primary-600"/>
                        <span x-text="modalData?.code"></span>
                    </div>
                    <div x-show="modalType === 'item'" class="flex items-center">
                        <x-heroicon-o-musical-note class="w-6 h-6 mr-2 text-primary-600"/>
                        <span x-text="modalData?.file_name"></span>
                    </div>
                </div>
            </x-slot>

            <x-slot name="description">
                <span x-show="modalData?.title" x-text="modalData?.title"></span>
            </x-slot>

            <div class="space-y-6">
                {{-- Informations principales --}}
                <x-filament::section>
                    <x-slot name="heading">
                        Informations
                    </x-slot>

                    <dl class="grid grid-cols-1 gap-3 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">Code</dt>
                            <dd class="text-gray-900 dark:text-gray-100 font-mono" x-text="modalData?.code"></dd>
                        </div>
                        <div x-show="modalData?.title">
                            <dt class="text-gray-500 dark:text-gray-400">Titre</dt>
                            <dd class="text-gray-900 dark:text-gray-100" x-text="modalData?.title"></dd>
                        </div>
                        <div x-show="modalData?.created_by">
                            <dt class="text-gray-500 dark:text-gray-400">Créé par</dt>
                            <dd class="text-gray-900 dark:text-gray-100" x-text="modalData?.created_by"></dd>
                        </div>
                        <div x-show="modalData?.created_at">
                            <dt class="text-gray-500 dark:text-gray-400">Créé le</dt>
                            <dd class="text-gray-900 dark:text-gray-100" x-text="modalData?.created_at"></dd>
                        </div>

                        {{-- Métadonnées spécifiques aux items --}}
                        <template x-if="modalType === 'item'">
                            <div class="space-y-3 border-t border-gray-200 dark:border-gray-600 pt-3">
                                <div x-show="modalData?.file_type">
                                    <dt class="text-gray-500 dark:text-gray-400">Type de fichier</dt>
                                    <dd class="text-gray-900 dark:text-gray-100" x-text="modalData?.file_type"></dd>
                                </div>
                                <div x-show="modalData?.formatted_file_size">
                                    <dt class="text-gray-500 dark:text-gray-400">Taille</dt>
                                    <dd class="text-gray-900 dark:text-gray-100" x-text="modalData?.formatted_file_size"></dd>
                                </div>
                                <div x-show="modalData?.formatted_duration">
                                    <dt class="text-gray-500 dark:text-gray-400">Durée</dt>
                                    <dd class="text-gray-900 dark:text-gray-100" x-text="modalData?.formatted_duration"></dd>
                                </div>
                            </div>
                        </template>
                    </dl>
                </x-filament::section>

                {{-- Items directs --}}
                <x-filament::section x-show="directItems.length > 0">
                    <x-slot name="heading">
                        <div class="flex items-center justify-between w-full">
                            <span>Items directs</span>
                            <x-filament::badge x-text="`${directItems.length} item(s)`" color="gray" size="sm" />
                        </div>
                    </x-slot>

                    <div class="space-y-2">
                        <template x-for="item in directItems" :key="item.id">
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate" x-text="item.file_name"></div>
                                    <div class="text-xs text-gray-500 flex items-center space-x-2 mt-1">
                                        <x-filament::badge x-text="item.file_type" color="gray" size="xs" />
                                        <span x-show="item.formatted_file_size" x-text="item.formatted_file_size"></span>
                                        <span x-show="item.formatted_duration" x-text="item.formatted_duration"></span>
                                    </div>
                                </div>
                                <div class="flex space-x-1 ml-3">
                                    <x-filament::icon-button
                                        icon="heroicon-o-eye"
                                        color="primary"
                                        size="sm"
                                        tooltip="Voir"
                                        @click="viewItem(item)" />
                                    <x-filament::icon-button
                                        icon="heroicon-o-pencil"
                                        color="warning"
                                        size="sm"
                                        tooltip="Éditer"
                                        @click="editItem(item)" />
                                    <x-filament::icon-button
                                        icon="heroicon-o-arrow-down-tray"
                                        color="success"
                                        size="sm"
                                        tooltip="Télécharger"
                                        @click="downloadItem(item)" />
                                </div>
                            </div>
                        </template>
                    </div>
                </x-filament::section>
            </div>

            <x-slot name="footerActions">
                <x-filament::button
                    @click="viewDetails(modalType, modalData)"
                    color="primary">
                    Voir Détails
                </x-filament::button>

                <x-filament::button
                    @click="editElement(modalType, modalData)"
                    color="gray"
                    outlined>
                    Éditer
                </x-filament::button>

                <x-filament::button
                    @click="exportElement(modalType, modalData)"
                    color="gray"
                    outlined>
                    Exporter
                </x-filament::button>
            </x-slot>
        </x-filament::modal>
    </div>

    @script
    <script>
        Alpine.data('hierarchyExplorer', () => ({
            // État des données
            fonds: [],
            corpuses: [],
            collections: [],
            items: [],
            directItems: [],

            // Sélections actuelles
            selectedFondId: {{$selectedFondId}},
            selectedFond: null,
            selectedCorpus: null,
            selectedCollection: null,
            selectedItem: null,

            // Modal
            modalType: null,
            modalData: null,


            // Interface
            searchTerm: '',
            showStats: false,
            loading: false,
            loadingCorpuses: false,
            loadingCollections: false,
            loadingItems: false,


            panelUr: '{{$panelUrl}}',

            async init() {
                await this.loadFonds();
                this.handleUrlParams();
                if (this.selectedFondId) {
                    this.selectedFond = this.fonds.find(f => f.id == this.selectedFondId);
                    await this.loadCorpuses(this.selectedFondId);
                }

                // Écouter les changements du select Filament
                this.$wire.on('fond-changed', (event) => {
                    this.selectedFondId = event.fondId;
                    this.onFondChange();
                });

            },

            async loadFonds() {
                this.loading = true;
                try {
                    const response = await fetch('/api/hierarchy/fonds');
                    this.fonds = await response.json();
                } catch (error) {
                    console.error('Erreur chargement fonds:', error);
                } finally {
                    this.loading = false;
                }
            },

            // Mise à jour de la méthode onFondChange
            async onFondChange() {
                if (this.selectedFondId) {
                    this.selectedFond = this.fonds.find(f => f.id == this.selectedFondId);
                    this.selectedCorpus = null;
                    this.selectedCollection = null;
                    this.selectedItem = null;

                    this.collections = [];
                    this.items = [];

                    await this.loadCorpuses(this.selectedFondId);
                } else {
                    this.resetSelection();
                }
            },



            async loadCorpuses(fondId) {
                this.loadingCorpuses = true;
                try {
                    const response = await fetch(`/api/hierarchy/fonds/${fondId}/corpuses`);
                    this.corpuses = await response.json();
                } catch (error) {
                    console.error('Erreur chargement corpus:', error);
                } finally {
                    this.loadingCorpuses = false;
                }
            },


            async selectCorpus(corpus) {
                this.selectedCorpus = corpus;
                this.selectedCollection = null;
                this.selectedItem = null;

                // Réinitialiser les items pendant le chargement des collections
                this.items = [];
                this.loadingItems = false; // Réinitialiser l'état de chargement des items

                await this.loadCollections(corpus.id);

            },

            async loadCollections(corpusId) {
                this.loadingCollections = true;
                try {
                    const response = await fetch(`/api/hierarchy/corpuses/${corpusId}/collections`);
                    this.collections = await response.json();
                } catch (error) {
                    console.error('Erreur chargement collections:', error);
                } finally {
                    this.loadingCollections = false;
                }
            },


            async selectCollection(collection) {
                this.selectedCollection = collection;
                this.selectedItem = null;

                await this.loadItems(collection.id);

            },

            async loadItems(collectionId) {
                this.loadingItems = true;
                try {
                    const response = await fetch(`/api/hierarchy/collections/${collectionId}/items`);
                    this.items = await response.json();
                } catch (error) {
                    console.error('Erreur chargement items:', error);
                } finally {
                    this.loadingItems = false;
                }
            },


            selectItem(item) {
                this.selectedItem = item;
            },

            // Gestion du modal
            async openModal(type, data) {
                this.modalType = type;
                this.modalData = data;

                // Charger les items directs
                await this.loadDirectItems(type, data.id);

                // Ouvrir le modal Filament
                this.$dispatch('open-modal', { id: 'hierarchy-details-modal' });
            },

            closeModal() {
                this.modalType = null;
                this.modalData = null;
                this.directItems = [];

                // Fermer le modal Filament
                this.$dispatch('close-modal', { id: 'hierarchy-details-modal' });
            },


            async loadDirectItems(type, id) {
                try {
                    const response = await fetch(`/api/hierarchy/${type}/${id}/direct-items`);
                    this.directItems = await response.json();
                } catch (error) {
                    console.error('Erreur chargement items directs:', error);
                    this.directItems = [];
                }
            },

            // Actions sur les éléments
            viewDetails(type, data) {
                const baseUrl = this.panelUr;
                let url = '';

                switch (type) {
                    case 'fond':
                        url = `${baseUrl}/fonds/${data.id}`;
                        break;
                    case 'corpus':
                        url = `${baseUrl}/corpuses/${data.id}`;
                        break;
                    case 'collection':
                        url = `${baseUrl}/collections/${data.id}`;
                        break;
                    case 'item':
                        url = `${baseUrl}/items/${data.id}`;
                        break;
                }

                if (url) {
                    window.open(url, '_blank');
                }
            },

            editElement(type, data) {
                const baseUrl = this.panelUr;
                let url = '';

                switch (type) {
                    case 'fond':
                        url = `${baseUrl}/fonds/${data.id}/edit`;
                        break;
                    case 'corpus':
                        url = `${baseUrl}/corpuses/${data.id}/edit`;
                        break;
                    case 'collection':
                        url = `${baseUrl}/collections/${data.id}/edit`;
                        break;
                    case 'item':
                        url = `${baseUrl}/items/${data.id}/edit`;
                        break;
                }

                if (url) {
                    window.open(url, '_blank');
                }
            },

            exportElement(type, data) {
                // Logique d'export selon le type
                console.log('Export:', type, data);
            },

            // Actions sur les items
            viewItem(item) {
                window.open(this.panelUr+`/items/${item.id}`, '_blank');
            },

            editItem(item) {
                window.open(this.panelUr+`/items/${item.id}/edit`, '_blank');
            },

            downloadItem(item) {
                window.open(`/api/items/${item.id}/download`, '_blank');
            },

            // Actions de création
            createFond() {
                window.open(this.panelUr+'/fonds/create', '_blank');
            },

            createCorpus() {
                if (this.selectedFond) {
                    window.open(this.panelUr+`/corpuses/create?fond_id=${this.selectedFond.id}`, '_blank');
                }
            },

            createCollection() {
                if (this.selectedCorpus) {
                    window.open(this.panelUr+`/collections/create?corpus_id=${this.selectedCorpus.id}`, '_blank');
                }
            },

            createItem() {
                if (this.selectedCollection) {
                    window.open(this.panelUr+`/items/create?collection_id=${this.selectedCollection.id}`, '_blank');
                }
            },

            async refresh() {
                await this.loadFonds();
                if (this.selectedFondId) {
                    await this.onFondChange();
                }
            },



            toggleStats() {
                this.showStats = !this.showStats;
            },

            search() {
                console.log('Recherche:', this.searchTerm);
            },

            handleUrlParams() {
                const urlParams = new URLSearchParams(window.location.search);
                const focus = urlParams.get('focus');
                const id = urlParams.get('id');

                if (focus && id) {
                    console.log('Focus sur:', focus, id);
                }
            }
        }));
    </script>
    @endscript
</x-filament-panels::page>
