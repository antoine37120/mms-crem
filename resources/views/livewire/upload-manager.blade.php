<div>
    {{-- Bouton flottant Upload Manager --}}
    <div class="fixed bottom-6 right-6 z-50  space-y-3">
        {{-- Bouton Fichiers en Attente --}}
        <button
            wire:click="openModalPendingFiles"
            class="w-12 h-12 bg-orange-600 hover:bg-orange-700 text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-200 flex items-center justify-center group"
            title="Voir Fichiers en Attente"
            type="button"
        >
            <x-heroicon-o-queue-list class="h-6 w-6 group-hover:scale-110 transition-transform duration-200" />

            {{-- Badge de notification (factice) --}}
            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-bold">
                4
            </span>
        </button>

        <button
            wire:click="openModal"
            class="w-12 h-12 bg-primary-600 hover:bg-primary-700 text-white rounded-full shadow-lg hover:shadow-xl transition-all duration-200 flex items-center justify-center group"
            title="Ouvrir Upload Manager"
            type="button"
        >
            <x-heroicon-o-arrow-up-tray class="h-6 w-6 group-hover:scale-110 transition-transform duration-200" />
        </button>
    </div>
    {{-- Modal Upload Manager --}}
    <x-filament::modal
        width="4xl"
        :close-by-clicking-away="true"
        id="upload-manager-modal"
    >
        <x-slot name="header">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Upload Manager
                </h2>
            </div>
        </x-slot>

        <div class="space-y-6">
            {{-- Contenu temporaire simple --}}
            <div class="text-center py-12" wire:ignore>
                @livewire('upload-files')

            </div>

            {{-- Boutons d'action temporaires --}}
            <div class="flex justify-between">
                <div class="flex gap-3">
                    <x-filament::button
                        color="gray"
                        wire:click="closeModal"
                    >
                        Fermer
                    </x-filament::button>
                </div>
            </div>
        </div>
    </x-filament::modal>

    {{-- Modal Fichiers en Attente --}}
    <x-filament::modal
        width="6xl"
        :close-by-clicking-away="true"
        id="pending-files-modal"
    >
        <x-slot name="header">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                    Fichiers en Attente de Rangement
                </h2>
            </div>
        </x-slot>

        <div class="space-y-6">
            <div>
                @livewire('uploaded-files-table')
            </div>

            {{-- Actions globales --}}
            <div class="flex justify-between">
                <div class="flex gap-3">
                    <x-filament::button
                        color="success"
                        disabled
                    >
                        Ranger Tous
                    </x-filament::button>
                </div>
            </div>
        </div>
    </x-filament::modal>
    {{-- Script--}}

</div>
