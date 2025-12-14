<div x-data="fileUploadHandler()">
    {{-- Script MD5 --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/spark-md5/3.0.2/spark-md5.min.js"></script>

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

    {{-- Loading indicator for processing --}}
    <div x-show="isProcessing" class="mt-4 p-3 bg-blue-50 text-blue-700 text-sm rounded flex items-center justify-center">
        <x-heroicon-o-arrow-path class="w-4 h-4 animate-spin mr-2" />
        Traitement des fichiers et calcul des signatures...
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

    {{-- Bouton d'action principal --}}
    @if($stats['queued'] > 0)
        <div class="mt-4 text-right">
            <x-filament::button
                wire:click="startAllUploads"
                color="primary"
                size="lg"
                :disabled="$isUploading"
                x-bind:disabled="isProcessing"
            >
                @if($isUploading)
                    <x-heroicon-o-arrow-path class="w-4 h-4 animate-spin mr-2" />
                    Upload en cours...
                @else
                    <x-heroicon-o-rocket-launch class="w-4 h-4 mr-2" />
                    Lancer tous les uploads ({{ $stats['queued'] }})
                @endif
            </x-filament::button>
        </div>
    @endif

    {{-- Liste unifiée des fichiers --}}
    @if(!empty($files))
        <div class="mt-6">
            <div class="flex items-center justify-between mb-3">
                 <h3 class="text-sm font-medium text-gray-900 dark:text-white">Liste des fichiers</h3>
                 <div class="flex gap-2">
                     @if($stats['completed'] > 0)
                        <button wire:click="clearCompleted" class="text-xs text-gray-500 hover:text-gray-700">
                            Effacer terminés
                        </button>
                     @endif
                     @if($stats['failed'] > 0)
                        <button wire:click="clearFailed" class="text-xs text-red-500 hover:text-red-700">
                            Effacer échoués
                        </button>
                    @endif
                 </div>
            </div>

            <div class="space-y-2">
                @foreach($files as $fileId => $file)
                    <div class="p-3 rounded border transition-colors relative
                        @if($file['status'] === 'queued') bg-white dark:bg-gray-800 border-gray-200 dark:border-gray-700
                        @elseif($file['status'] === 'uploading') bg-blue-50 dark:bg-blue-900/20 border-blue-200
                        @elseif($file['status'] === 'completed') bg-green-50 dark:bg-green-900/20 border-green-200
                        @elseif($file['status'] === 'failed') bg-red-50 dark:bg-red-900/20 border-red-200
                        @endif
                    ">

                        <div class="flex items-center justify-between mb-2">
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                     <span class="text-sm font-medium
                                        @if($file['status'] === 'completed') text-green-900 dark:text-green-100
                                        @elseif($file['status'] === 'failed') text-red-900 dark:text-red-100
                                        @elseif($file['status'] === 'uploading') text-blue-900 dark:text-blue-100
                                        @else text-gray-900 dark:text-white
                                        @endif
                                    ">
                                        {{ $file['name'] }}
                                    </span>

                                     {{-- Indicateur de statut --}}
                                    @if($file['status'] === 'completed')
                                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-600" />
                                    @elseif($file['status'] === 'failed')
                                        <x-heroicon-o-exclamation-circle class="w-5 h-5 text-red-600" />
                                    @elseif($file['status'] === 'uploading')
                                        <x-heroicon-o-arrow-path class="w-4 h-4 animate-spin text-blue-600" />
                                    @endif
                                </div>

                                <div class="text-xs mt-1
                                     @if($file['status'] === 'completed') text-green-700 dark:text-green-300
                                     @elseif($file['status'] === 'failed') text-red-700 dark:text-red-300
                                     @elseif($file['status'] === 'uploading') text-blue-700 dark:text-blue-300
                                     @else text-gray-500
                                     @endif
                                ">
                                    @if($file['status'] === 'queued')
                                        {{ number_format($file['size'] / 1024 / 1024, 1) }} MB
                                        @if($file['suggested_code'])
                                            • Cote suggérée: <span class="font-mono text-blue-600">{{ $file['suggested_code'] }}</span>
                                        @endif
                                    @elseif($file['status'] === 'uploading')
                                         <span x-show="getProgress('{{ $fileId }}') < 100">
                                             <span x-text="getProgress('{{ $fileId }}')">0</span>%
                                         </span>
                                         <span x-show="getProgress('{{ $fileId }}') >= 100" class="font-semibold text-blue-800 animate-pulse">
                                             Finalisation...
                                         </span>
                                    @elseif($file['status'] === 'completed')
                                        ✅ Upload terminé
                                        @if($file['suggested_code'])
                                            • Cote: <span class="font-mono">{{ $file['suggested_code'] }}</span>
                                        @endif
                                    @elseif($file['status'] === 'failed')
                                         ❌ {{ $file['error'] }}
                                    @endif
                                </div>

                                {{-- Duplicate Warning --}}
                                @if(!empty($file['duplicate_warning']))
                                    <div class="mt-1 text-xs text-orange-600 font-medium flex items-center gap-1">
                                        <x-heroicon-o-exclamation-triangle class="w-3 h-3" />
                                        {{ $file['duplicate_warning'] }}
                                    </div>
                                @endif
                            </div>

                             {{-- Boutons d'action --}}
                            <div>
                                @if($file['status'] === 'queued')
                                    <button
                                        wire:click="removeFromQueue('{{ $fileId }}')"
                                        class="text-gray-400 hover:text-red-500"
                                        title="Retirer de la liste"
                                    >
                                        <x-heroicon-o-x-mark class="w-5 h-5" />
                                    </button>
                                @elseif($file['status'] === 'uploading')
                                    <button
                                        wire:click="cancelUpload('{{ $fileId }}')"
                                        class="text-red-500 hover:text-red-700"
                                        title="Annuler l'upload"
                                    >
                                        <x-heroicon-o-stop class="w-5 h-5" />
                                    </button>
                                @elseif($file['status'] === 'failed')
                                     <button
                                        wire:click="retryUpload('{{ $fileId }}')"
                                        class="text-blue-500 hover:text-blue-700 text-sm font-medium"
                                    >
                                        Réessayer
                                    </button>
                                @endif
                            </div>
                        </div>

                        {{-- Barre de progression pour upload (Frontend Driven) --}}
                        @if($file['status'] === 'uploading')
                             <div class="w-full bg-blue-200 rounded-full h-1.5 mt-2">
                                <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-300"
                                     x-bind:style="'width: ' + getProgress('{{ $fileId }}') + '%'"></div>
                            </div>
                        @endif

                        {{-- Signature Display --}}
                        @if($file['signature'])
                            <div class="absolute bottom-1 right-2 text-[10px] text-gray-400 font-mono opacity-60 hover:opacity-100 transition-opacity" title="Signature MD5">
                                {{ substr($file['signature'], 0, 8) }}...{{ substr($file['signature'], -8) }}
                            </div>
                        @endif
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
            isProcessing: false,
            selectedFiles: [],
            filesProgress: {}, // Local progress state: { fileId: percentage }

            // Initialisation
            init() {
                // Écouter les événements Livewire
                Livewire.on('start-file-upload', (data) => {
                    console.log('start-file-upload') ;
                    this.handleStartUpload(data);
                });
            },

            getProgress(fileId) {
                return this.filesProgress[fileId] || 0;
            },

            // Gestion de la sélection de fichiers
            async handleFileSelect(event) {
                const files = Array.from(event.target.files);
                if (files.length === 0) return;

                await this.processFiles(files);
                event.target.value = ''; // Reset input
            },

            // Gestion du drag & drop
            async handleDrop(event) {
                this.isDragOver = false;
                const files = Array.from(event.dataTransfer.files);
                if (files.length === 0) return;

                await this.processFiles(files);
            },

            // Traitement des fichiers sélectionnés
            async processFiles(files) {
                this.isProcessing = true;
                // Stocker les fichiers pour l'upload
                this.selectedFiles = [...this.selectedFiles, ...files];

                // Process each file one by one to avoid UI blocking
                for (const file of files) {
                    try {
                        // Yield to UI before starting heavy calc
                        await new Promise(resolve => setTimeout(resolve, 10));

                        const signature = await this.calculateMD5(file);

                        // Add singular file to queue immediately
                        await $wire.addFilesToQueue([{
                            name: file.name,
                            size: file.size,
                            type: file.type,
                            signature: signature
                        }]);

                        // Check for duplicate
                        const filesState = $wire.files;
                        const fileId = Object.keys(filesState).find(id =>
                            filesState[id].name === file.name &&
                            filesState[id].size === file.size &&
                            filesState[id].signature === signature
                        );

                        if (fileId) {
                            $wire.checkDuplicate(fileId, signature);
                        }

                    } catch (error) {
                        console.error('Erreur calcul MD5 pour ' + file.name, error);
                         $wire.addFilesToQueue([{
                            name: file.name,
                            size: file.size,
                            type: file.type,
                            signature: null
                        }]);
                    }
                }

                this.isProcessing = false;
            },

            // Calcul MD5 avec SparkMD5
            calculateMD5(file) {
                return new Promise((resolve, reject) => {
                    const blobSlice = File.prototype.slice || File.prototype.mozSlice || File.prototype.webkitSlice;
                    const chunkSize = 2097152; // Read in chunks of 2MB
                    const chunks = Math.ceil(file.size / chunkSize);
                    let currentChunk = 0;
                    const spark = new SparkMD5.ArrayBuffer();
                    const fileReader = new FileReader();

                    fileReader.onload = function (e) {
                        spark.append(e.target.result);                   // Append array buffer
                        currentChunk++;

                        if (currentChunk < chunks) {
                            loadNext();
                        } else {
                            const hash = spark.end();
                            console.log('MD5 computed:', hash);
                            resolve(hash);
                        }
                    };

                    fileReader.onerror = function () {
                        reject('Oops, something went wrong.');
                    };

                    function loadNext() {
                        const start = currentChunk * chunkSize;
                        const end = ((start + chunkSize) >= file.size) ? file.size : start + chunkSize;
                        fileReader.readAsArrayBuffer(blobSlice.call(file, start, end));
                    }

                    loadNext();
                });
            },

            // Gestion du démarrage d'upload
            handleStartUpload(data) {
                console.log(data) ;
                const { fileId, pendingFileId, totalChunks } = data[0];

                // Trouver le fichier correspondant dans selectedFiles
                const filesState = $wire.files || {};
                const fileState = filesState[fileId];

                if (!fileState) {
                    console.error('File state not found for ID:', fileId);
                    return;
                }

                const file = this.selectedFiles.find(f => f.name === fileState.name && f.size === fileState.size);

                if (file) {
                    console.log('fichier trouvé pour transfert en chunk: ' + file.name) ;
                    this.filesProgress[fileId] = 0; // Init progress
                    this.uploadFileInChunks(file, fileId, pendingFileId, totalChunks);
                } else {
                    console.error('Fichier physique non trouvé pour:', fileState.name) ;
                }
            },

            // Upload par chunks
            async uploadFileInChunks(file, fileId, pendingFileId, totalChunks) {
                for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                    // Check if cancelled
                    const currentFileState = $wire.files[fileId];
                    // If status is not uploading anymore (e.g. cancelled), stop.
                    // Note: Since backend doesn't return progress anymore, we check status.
                    if (!currentFileState || currentFileState.status !== 'uploading') {
                        console.log('Upload stopped for ' + file.name);
                        return;
                    }

                    const start = chunkIndex * this.CHUNK_SIZE;
                    const end = Math.min(start + this.CHUNK_SIZE, file.size);
                    const chunk = file.slice(start, end);

                    try {
                        // Convertir le chunk en base64
                        const chunkData = await this.fileToBase64(chunk);

                        // Envoyer via Livewire
                        await $wire.uploadChunk(fileId, chunkIndex, chunkData, pendingFileId);

                        // Update local progress
                        const progress = ((chunkIndex + 1) / totalChunks) * 100;
                        this.filesProgress[fileId] = Math.round(progress * 10) / 10;

                    } catch (error) {
                        console.error('Erreur upload chunk:', error);
                        break;
                    }
                }

                // End of loop - trigger FINALIZATION explicitly
                // This ensures we tell the server "I'm done" and it can check everything.
                try {
                     await $wire.finalizeUpload(fileId);
                } catch (error) {
                     console.error('Finalization failed', error);
                }

                // Trigger next upload
                $wire.startNextPending();
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
