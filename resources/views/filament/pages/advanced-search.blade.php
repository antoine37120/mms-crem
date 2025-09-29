
<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Section d'aide rapide --}}
        <x-filament::section
            :heading="'Guide de recherche'"
            :description="'Conseils pour optimiser vos recherches'"
            collapsible
            collapsed
        >
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                <div class="space-y-2">
                    <h4 class="font-semibold text-gray-900">Recherche globale</h4>
                    <ul class="list-disc list-inside text-gray-600 space-y-1">
                        <li>Code complet ou partiel</li>
                        <li>Titre ou nom de fichier</li>
                        <li>Utilisez % comme joker</li>
                    </ul>
                </div>

                <div class="space-y-2">
                    <h4 class="font-semibold text-gray-900">Hiérarchie</h4>
                    <ul class="list-disc list-inside text-gray-600 space-y-1">
                        <li>Filtrez par Fonds, Corpus, Collection</li>
                        <li>Les sélections sont cumulatives</li>
                        <li>Utilisez le bouton Hiérarchie pour naviguer</li>
                    </ul>
                </div>

                <div class="space-y-2">
                    <h4 class="font-semibold text-gray-900">Raccourcis</h4>
                    <ul class="list-disc list-inside text-gray-600 space-y-1">
                        <li><kbd>Ctrl+K</kbd> : Recherche rapide</li>
                        <li><kbd>Échap</kbd> : Effacer filtres</li>
                        <li>Clic droit : Menu contextuel</li>
                    </ul>
                </div>
            </div>
        </x-filament::section>

        {{-- Table principale avec tous les filtres --}}
        {{ $this->table }}
    </div>

    {{-- Scripts personnalisés --}}
    @push('scripts')
        <script>
            // Raccourci clavier pour la recherche
            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'k') {
                    e.preventDefault();
                    document.querySelector('input[name="tableFilters.global_search.search"]')?.focus();
                }

                if (e.key === 'Escape') {
                    // Logique pour effacer les filtres
                    if (confirm('Effacer tous les filtres de recherche ?')) {
                        @this.call('resetTableFilters');
                    }
                }
            });

            // Tooltip personnalisé pour les actions
            window.addEventListener('DOMContentLoaded', function() {
                // Initialisation des tooltips si nécessaire
            });
        </script>
    @endpush
</x-filament-panels::page>
