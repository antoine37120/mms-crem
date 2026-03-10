<div 
    x-data="fileMd5Component({ state: $wire.$entangle('{{ $getStatePath() }}') })"
    class="flex flex-col gap-2"
>
    <!-- Label Custom -->
    <label class="text-sm font-medium leading-6 text-gray-950 dark:text-white">Fichier similaire à</label>

    <!-- UI Custom -->
    <div class="flex items-center gap-3">
        <label class="cursor-pointer bg-primary-600 text-white px-3 py-2 rounded-lg text-sm transition hover:bg-primary-500 hover:text-white"
               :class="{ 'opacity-50 cursor-not-allowed': isCalculating }">
            <span x-show="!isCalculating">Sélectionner un fichier...</span>
            <span x-show="isCalculating" class="flex items-center gap-2">
                <x-heroicon-o-arrow-path class="w-4 h-4 animate-spin" />
                Lecture... <span x-text="progress"></span>%
            </span>
            <input type="file" class="hidden" @change="processFile" :disabled="isCalculating" />
        </label>
    </div>

    <!-- Affichage du MD5 calculé -->
    <div x-show="state && !isCalculating" class="flex items-center justify-between bg-success-50 p-2 rounded border border-success-200" title="Hash MD5 Calculé">
        <div class="text-xs text-success-600 truncate">
            <span class="font-bold">Signature du fichier :</span> <br>
            <span class="font-mono text-[10px]" x-text="state"></span>
        </div>
        <x-filament::button
            color="danger"
            size="sm"
            tooltip="Effacer la sélection"
            x-on:click="state = null;"
        >
            <x-heroicon-o-x-mark class="w-4 h-4" />
        </x-filament::button>
    </div>

</div>

@script
<script>
    Alpine.data('fileMd5Component', ({ state }) => ({
        state,
        isCalculating: false,
        progress: 0,
        
        processFile(event) {
            const file = event.target.files[0];
            if (!file) return;

            this.isCalculating = true;
            this.progress = 0;
            this.state = null;

            // Paramètres de lecture incrémentale
            const blobSlice = File.prototype.slice || File.prototype.mozSlice || File.prototype.webkitSlice;
            const chunkSize = 2097152; // 2MB par chunk
            const chunks = Math.ceil(file.size / chunkSize);
            let currentChunk = 0;
            
            // SparkMD5 est chargé globalement par le MmsAdminPanelProvider
            const spark = new SparkMD5.ArrayBuffer();
            const fileReader = new FileReader();

            fileReader.onload = (e) => {
                spark.append(e.target.result); // Ajout au buffer
                currentChunk++;
                this.progress = Math.round((currentChunk / chunks) * 100);

                if (currentChunk < chunks) {
                    loadNext(); // Lire le morceau suivant
                } else {
                    // Fin, obtenir l'empreinte et terminer
                    this.state = spark.end(); // Assigne la valeur à l'état Filament (Livewire update)
                    this.isCalculating = false;
                    event.target.value = ''; // Reset l'input pour permettre un réupload
                }
            };

            fileReader.onerror = () => {
                console.warn('Erreur lors de la lecture du fichier');
                this.isCalculating = false;
                event.target.value = '';
            };

            const loadNext = () => {
                const start = currentChunk * chunkSize;
                const end = ((start + chunkSize) >= file.size) ? file.size : start + chunkSize;
                fileReader.readAsArrayBuffer(blobSlice.call(file, start, end));
            };

            loadNext(); // Démarrage
        }
    }));
</script>
@endscript
