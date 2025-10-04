<div x-data="fileUploadHandler()">
    {{-- Zone de sélection des fichiers --}}
    <div
        class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-6 text-center bg-gray-50 dark:bg-gray-800 transition-colors"
        :class="{
            'border-primary-400 bg-primary-50 dark:bg-primary-900/20': isDragOver,
            'border-gray-300 dark:border-gray-600': !isDragOver
        }"
        @click="$refs.fileInput.click()"
        @dragover.prevent="isDragOver = true"
        @dragleave.prevent="isDragOver = false"
        @drop.prevent="handleDrop($event)"
    >
        <input
            type="file"
            multiple
            x-ref="fileInput"
            class="hidden"
            accept="audio/*,video/*,image/*,application/pdf,.txt,.doc,.docx"
            @change="handleFileSelect($event)"
        >

        <div class="cursor-pointer">
            <x-heroicon-o-cloud-arrow-up class="mx-auto h-12 w-12 text-gray-400" />
            <div class="mt-4">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    <span class="font-semibold text-primary-600">Cliquez pour sélectionner</span>
                    ou glissez-déposez vos fichiers
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                    Fichiers multiples supportés • Chunks 10MB • Tous formats
                </p>
            </div>
        </div>
    </div>

    {{-- Statistiques --}}
    @if($stats['total'] > 0)
        <div class="mt-4 grid grid-cols-4 gap-4">
            <div class="bg-gray-100 dark:bg-gray-700 p-3 rounded text-center">
                <div class="text-lg font-semibold text-gray-600 dark:text-gray-300">{{ $stats['queued'] }}</div>
                <div class="text-xs text-gray-500">En attente</div>
            </div>
            <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded text-center">
                <div class="text-lg font-semibold text-blue-600">{{ $stats['uploading'] }}</div>
                <div class="text-xs text-blue-600">En cours</div>
            </div>
            <div class="bg-green-100 dark:bg-green-900 p-3 rounded text-center">
                <div class="text-lg font-semibold text-green-600">{{ $stats['completed'] }}</div>
                <div class="text-xs text-green-600">Terminés</div>
            </div>
            <div class="bg-red-100 dark:bg-red-900 p-3 rounded text-center">
                <div class="text-lg font-semibold text-red-600">{{ $stats['failed'] }}</div>
                <div class="text-xs text-red-600">Échoués</div>
            </div>
        </div>
    @endif


    {{-- Liste des fichiers terminés --}}
    @if(!empty($completedFiles))
        <div class="mt-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">Uploads terminés</h3>
                <button wire:click="clearCompleted" class="text-xs text-gray-500 hover:text-gray-700">
                    Effacer la liste
                </button>
            </div>
            <div class="space-y-2">
                @foreach($completedFiles as $fileId => $file)
                    <div class="flex items-center justify-between p-3 bg-green-50 dark:bg-green-900/20 rounded border border-green-200">
                        <div class="flex-1">
                            <div class="text-sm font-medium text-green-900 dark:text-green-100">{{ $file['name'] }}</div>
                            <div class="text-xs text-green-700 dark:text-green-300">
                                ✅ Upload terminé
                                @if($file['suggested_code'])
                                    • Cote: <span class="font-mono">{{ $file['suggested_code'] }}</span>
                                @endif
                            </div>
                        </div>
                        <span class="text-green-600">
                            <x-heroicon-o-check-circle class="w-5 h-5" />
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Liste des fichiers en upload --}}
    @if(!empty($uploadingFiles))
        <div class="mt-6">
            <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Uploads en cours</h3>
            <div class="space-y-3">
                @foreach($uploadingFiles as $fileId => $file)
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded border border-blue-200">
                        <div class="flex items-center justify-between mb-2">
                            <div class="text-sm font-medium text-blue-900 dark:text-blue-100">{{ $file['name'] }}</div>
                            <button
                                wire:click="cancelUpload('{{ $fileId }}')"
                                class="text-red-500 hover:text-red-700"
                                title="Annuler l'upload"
                            >
                                <x-heroicon-o-stop class="w-4 h-4" />
                            </button>
                        </div>
                        <div class="w-full bg-blue-200 rounded-full h-2 mb-1">
                            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" style="width: {{ $file['progress'] }}%"></div>
                        </div>
                        <div class="text-xs text-blue-700 dark:text-blue-300">
                            {{ round($file['progress'], 1) }}% • {{ $file['chunks_uploaded'] }}/{{ $file['chunks_total'] }} chunks
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif


    {{-- Liste des fichiers en queue --}}
    @if(!empty($queuedFiles))
        <div class="mt-6">
            <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-3">Fichiers sélectionnés</h3>
            <div class="space-y-2">
                @foreach($queuedFiles as $fileId => $file)
                    <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded border">
                        <div class="flex-1">
                            <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $file['name'] }}</div>
                            <div class="text-xs text-gray-500">
                                {{ number_format($file['size'] / 1024 / 1024, 1) }} MB
                                @if($file['suggested_code'])
                                    • Cote suggérée: <span class="font-mono text-blue-600">{{ $file['suggested_code'] }}</span>
                                @endif
                            </div>
                        </div>
                        <button
                            wire:click="removeFromQueue('{{ $fileId }}')"
                            class="text-red-500 hover:text-red-700"
                            title="Retirer de la liste"
                        >
                            <x-heroicon-o-x-mark class="w-4 h-4" />
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Bouton d'action principal --}}
    @if(!empty($queuedFiles))
        <div class="mt-4 text-right">
            <x-filament::button
                wire:click="startAllUploads"
                color="primary"
                size="lg"
                :disabled="$isUploading"
            >
                @if($isUploading)
                    <x-heroicon-o-arrow-path class="w-4 h-4 animate-spin mr-2" />
                    Upload en cours...
                @else
                    <x-heroicon-o-rocket-launch class="w-4 h-4 mr-2" />
                    Lancer tous les uploads ({{ count($queuedFiles) }})
                @endif
            </x-filament::button>
        </div>
    @endif

    {{-- Liste des fichiers échoués --}}
    @if(!empty($failedFiles))
        <div class="mt-6">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-medium text-gray-900 dark:text-white">Uploads échoués</h3>
                <button wire:click="clearFailed" class="text-xs text-gray-500 hover:text-gray-700">
                    Effacer la liste
                </button>
            </div>
            <div class="space-y-2">
                @foreach($failedFiles as $fileId => $file)
                    <div class="p-3 bg-red-50 dark:bg-red-900/20 rounded border border-red-200">
                        <div class="flex items-center justify-between mb-1">
                            <div class="text-sm font-medium text-red-900 dark:text-red-100">{{ $file['name'] }}</div>
                            <button
                                wire:click="retryUpload('{{ $fileId }}')"
                                class="text-blue-500 hover:text-blue-700 text-xs"
                            >
                                Réessayer
                            </button>
                        </div>
                        <div class="text-xs text-red-700 dark:text-red-300">
                            ❌ {{ $file['error'] }}
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

{{-- Composant Alpine.js --}}
@script
<script>
        Alpine.data('fileUploadHandler', () => ({
            // Configuration
            CHUNK_SIZE: {{ $CHUNK_SIZE }},

            // État
            isDragOver: false,
            selectedFiles: [],

            // Initialisation
            init() {
                // Écouter les événements Livewire
                Livewire.on('start-file-upload', (data) => {
                    console.log('start-file-upload') ;
                    this.handleStartUpload(data);
                });
            },

            // Gestion de la sélection de fichiers
            handleFileSelect(event) {
                const files = Array.from(event.target.files);
                if (files.length === 0) return;

                this.processFiles(files);
                event.target.value = ''; // Reset input
            },

            // Gestion du drag & drop
            handleDrop(event) {
                this.isDragOver = false;
                const files = Array.from(event.dataTransfer.files);
                if (files.length === 0) return;

                this.processFiles(files);
            },

            // Traitement des fichiers sélectionnés
            processFiles(files) {
                // Stocker les fichiers pour l'upload
                this.selectedFiles = files;

                // Préparer les données pour Livewire
                const fileData = files.map(file => ({
                    name: file.name,
                    size: file.size,
                    type: file.type
                }));

                // Envoyer à Livewire
                $wire.addFilesToQueue(fileData);
            },

            // Gestion du démarrage d'upload
            handleStartUpload(data) {
                console.log(data) ;
                const { fileId, pendingFileId, totalChunks } = data[0];
                // Trouver le fichier correspondant
                const file = this.selectedFiles.find(f => {
                    const uploadingFiles = $wire.uploadingFiles || {};
                    return uploadingFiles[fileId] && f.name === uploadingFiles[fileId].name;
                });

                if (file) {
                    console.log('fichier trouvé pour transfert en chunk') ;
                    this.uploadFileInChunks(file, fileId, pendingFileId, totalChunks);
                } else {
                    console.log('fichier non trouvé pour transfert en chunk') ;
                }
            },

            // Upload par chunks
            async uploadFileInChunks(file, fileId, pendingFileId, totalChunks) {
                for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                    const start = chunkIndex * this.CHUNK_SIZE;
                    const end = Math.min(start + this.CHUNK_SIZE, file.size);
                    const chunk = file.slice(start, end);

                    console.log('file:'+file+', chunkIndex: '+chunkIndex) ;
                    try {
                        // Convertir le chunk en base64
                        const chunkData = await this.fileToBase64(chunk);

                        // Envoyer via Livewire
                        await $wire.uploadChunk(fileId, chunkIndex, chunkData, pendingFileId);

                    } catch (error) {
                        console.error('Erreur upload chunk:', error);
                        break;
                    }
                }
            },

            // Convertir fichier en base64
            fileToBase64(file) {
                return new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result.split(',')[1]);
                    reader.onerror = reject;
                    reader.readAsDataURL(file);
                });
            }
        }));
</script>
@endscript
</div>
