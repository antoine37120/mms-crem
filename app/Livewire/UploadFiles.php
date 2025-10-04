<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\PendingFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;


class UploadFiles extends Component
{
    use WithFileUploads;
    public int $maxConcurrentUploads = 2;

    // Configuration
    public int $CHUNK_SIZE = 10 * 1024 * 1024; // 10MB

    // État des fichiers
    public array $queuedFiles = [];
    public array $uploadingFiles = [];
    public array $completedFiles = [];
    public array $failedFiles = [];

    // Contrôle d'upload
    public bool $isUploading = false;

    // Listeners pour communication avec le parent
    protected $listeners = [
        'filesSelected' => 'addFilesToQueue',
        'startUploads' => 'startAllUploads',
        'cancelUpload' => 'cancelUpload',
        'retryUpload' => 'retryUpload',
    ];
    /**
     * Méthode appelée au montage du composant
     * Nettoie automatiquement les anciens fichiers orphelins
     */
    public function mount(): void
    {
        $this->cleanupOldPendingFiles();
    }

    /**
     * Nettoie les anciens PendingFiles de l'utilisateur
     * Supprime ceux avec statut 'uploading' ou 'failed' de plus de 24h
     */
    private function cleanupOldPendingFiles(): void
    {
        try {
            $cutoffTime = Carbon::now()->subDay(); // 24h

            $oldPendingFiles = PendingFile::byUser(auth()->id())
                ->whereIn('upload_status', [PendingFile::STATUS_UPLOADING, PendingFile::STATUS_FAILED])
                ->where('created_at', '<', $cutoffTime)
                ->get();

            if ($oldPendingFiles->count() > 0) {
                Log::info('Nettoyage PendingFiles orphelins', [
                    'user_id' => auth()->id(),
                    'count' => $oldPendingFiles->count(),
                    'cutoff_time' => $cutoffTime,
                ]);

                foreach ($oldPendingFiles as $pendingFile) {
                    // Supprimer les chunks temporaires s'ils existent
                    $this->cleanupChunks($pendingFile);

                    // Supprimer le fichier principal s'il existe
                    $pendingFile->deleteTemporaryFile();

                    // Supprimer l'enregistrement
                    $pendingFile->delete();
                }

                Log::info('Nettoyage PendingFiles terminé', [
                    'user_id' => auth()->id(),
                    'cleaned_count' => $oldPendingFiles->count(),
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Erreur lors du nettoyage des PendingFiles', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Nettoie les chunks temporaires d'un PendingFile
     */
    private function cleanupChunks(PendingFile $pendingFile): void
    {
        try {
            // Pattern pour trouver tous les chunks
            $chunkPattern = $pendingFile->file_path . '.chunk.*';

            // Récupérer le répertoire parent
            $directory = dirname($pendingFile->file_path);

            if (Storage::exists($directory)) {
                $allFiles = Storage::files($directory);

                // Filtrer les fichiers qui correspondent au pattern de chunks
                $chunkFiles = array_filter($allFiles, function($file) use ($pendingFile) {
                    return str_starts_with($file, $pendingFile->file_path . '.chunk.');
                });

                // Supprimer tous les chunks trouvés
                if (!empty($chunkFiles)) {
                    Storage::delete($chunkFiles);
                    Log::debug('Chunks supprimés', [
                        'pending_file_id' => $pendingFile->id,
                        'chunks_count' => count($chunkFiles),
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::warning('Erreur suppression chunks', [
                'pending_file_id' => $pendingFile->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function addFilesToQueue($files): void
    {
        foreach ($files as $fileData) {
            $fileId = (string) Str::uuid();

            //$fileId = Str::uuid();

            $this->queuedFiles[$fileId] = [
                'id' => $fileId,
                'name' => $fileData['name'],
                'size' => $fileData['size'],
                'type' => $fileData['type'],
                'chunks_total' => ceil($fileData['size'] / $this->CHUNK_SIZE),
                'chunks_uploaded' => 0,
                'progress' => 0,
                'status' => 'queued',
                'pending_file_id' => null,
                'error' => null,
                'suggested_code' => null,
            ];
        }

        // Rafraîchir la vue
        $this->dispatch('queue-updated', count($this->queuedFiles));
    }
    public function removeFromQueue(string $fileId): void
    {
        unset($this->queuedFiles[$fileId]);
    }

    public function startAllUploads(): void
    {
        if (empty($this->queuedFiles)) {
            return;
        }

        $this->isUploading = true;

        // Créer les PendingFile pour tous les fichiers en queue
        // Limiter le nombre d'uploads simultanés
        $filesToStart = array_slice($this->queuedFiles, 0, $this->maxConcurrentUploads, true);

        foreach ($filesToStart as $fileId => $fileData) {
            $this->createPendingFile($fileId);
        }

    }

    private function createPendingFile(string $fileId): void
    {
        $fileData = $this->queuedFiles[$fileId];

        try {
            // Générer un nom de stockage unique
            $storedName = Str::uuid() . '.' . pathinfo($fileData['name'], PATHINFO_EXTENSION);
            $filePath = 'pending-uploads/' . date('Y/m/d') . '/' . $storedName;

            // Créer le PendingFile
            $pendingFile = PendingFile::create([
                'user_id' => auth()->id(),
                'original_name' => $fileData['name'],
                'stored_name' => $storedName,
                'file_path' => $filePath,
                'file_size' => $fileData['size'],
                'file_type' => $fileData['type'],
                'file_extension' => strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION)),
                'upload_status' => PendingFile::STATUS_UPLOADING,
            ]);

            // Mettre à jour les données du fichier
            $this->queuedFiles[$fileId]['pending_file_id'] = $pendingFile->id;
            $this->queuedFiles[$fileId]['suggested_code'] = $pendingFile->suggested_code;
            $this->queuedFiles[$fileId]['status'] = 'uploading';

            // Déplacer vers uploadingFiles
            $this->uploadingFiles[$fileId] = $this->queuedFiles[$fileId];
            unset($this->queuedFiles[$fileId]);

            Log::warning('Launch start-file-upload', [
                'pending_file_id' => $fileId,
            ]);

            // Déclencher l'upload côté JavaScript
            $this->dispatch('start-file-upload', [
                'fileId' => $fileId,
                'pendingFileId' => $pendingFile->id,
                'chunkSize' => $this->CHUNK_SIZE,
                'totalChunks' => $fileData['chunks_total'],
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur création PendingFile', [
                'fileId' => $fileId,
                'error' => $e->getMessage()
            ]);

            $this->markFileAsFailed($fileId, 'Erreur de création: ' . $e->getMessage());
        }
    }

    public function uploadChunk($fileId, $chunkIndex, $chunk, $pendingFileId): void
    {
        // Ajouter au tout début de la méthode
        Log::info('=== CHUNK REQUEST START ===', [
            'fileId' => $fileId,
            'chunkIndex' => $chunkIndex,
            'session_id' => session()->getId(),
            'request_time' => now()->format('H:i:s.u'),
            'memory_before' => memory_get_usage(true),
            'concurrent_uploads' => count($this->uploadingFiles),
            'php_session_status' => session_status(),
        ]);

        try {
            // Libérer la session le plus tôt possible
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();

                Log::info('Session libérée pour chunk', [
                    'fileId' => $fileId,
                    'chunkIndex' => $chunkIndex,
                ]);

            }

            // Forcer un garbage collect avant traitement
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            $pendingFile = PendingFile::find($pendingFileId);

            if (!$pendingFile) {
                throw new \Exception('Fichier en attente non trouvé');
            }
            // Décoder le base64 et libérer immédiatement la variable originale
            $chunkData = base64_decode($chunk);
            unset($chunk); // Libérer immédiatement la mémoire du base64

            // Créer le dossier de destination si nécessaire
            $directory = dirname($pendingFile->file_path);
            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory);
            }

            // Nom du chunk temporaire
            $chunkPath = $pendingFile->file_path . '.chunk.' . $chunkIndex;

            // Utiliser un stream pour économiser la mémoire
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, $chunkData);
            rewind($stream);

            // Libérer la variable chunkData
            unset($chunkData);

            // Sauvegarder via stream
            Storage::put($chunkPath, $stream);
            fclose($stream);


            // Mettre à jour le progrès
            if (isset($this->uploadingFiles[$fileId])) {
                $this->uploadingFiles[$fileId]['chunks_uploaded']++;
                $totalChunks = $this->uploadingFiles[$fileId]['chunks_total'];
                $this->uploadingFiles[$fileId]['progress'] =
                    ($this->uploadingFiles[$fileId]['chunks_uploaded'] / $totalChunks) * 100;

                // Vérifier si tous les chunks sont uploadés
                if ($this->uploadingFiles[$fileId]['chunks_uploaded'] >= $totalChunks) {
                    $this->assembleFile($fileId, $pendingFileId);
                }
            }

            // Forcer un nouveau garbage collect
            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }


        } catch (\Exception $e) {
            Log::error('Erreur upload chunk', [
                'fileId' => $fileId,
                'chunkIndex' => $chunkIndex,
                'error' => $e->getMessage()
            ]);

            $this->markFileAsFailed($fileId, 'Erreur upload chunk: ' . $e->getMessage());
        }
    }

    private function assembleFile(string $fileId, int $pendingFileId): void
    {
        try {
            $pendingFile = PendingFile::find($pendingFileId);
            $fileData = $this->uploadingFiles[$fileId];

            // Assembler tous les chunks
            $finalPath = $pendingFile->file_path;
            $chunks = [];

            // Récupérer tous les chunks
            for ($i = 0; $i < $fileData['chunks_total']; $i++) {
                $chunkPath = $pendingFile->file_path . '.chunk.' . $i;
                if (Storage::exists($chunkPath)) {
                    $chunks[] = Storage::get($chunkPath);
                }
            }

            // Assembler le fichier final
            $finalContent = implode('', $chunks);
            Storage::put($finalPath, $finalContent);

            // Nettoyer les chunks temporaires
            for ($i = 0; $i < $fileData['chunks_total']; $i++) {
                $chunkPath = $pendingFile->file_path . '.chunk.' . $i;
                Storage::delete($chunkPath);
            }

            // Mettre à jour le PendingFile
            $pendingFile->markAsCompleted();

            // Extraire les métadonnées si possible
            try {
                $pendingFile->extractMetadata();
            } catch (\Exception $e) {
                Log::warning('Erreur extraction métadonnées', ['error' => $e->getMessage()]);
            }

            // Marquer comme terminé
            $this->markFileAsCompleted($fileId);

        } catch (\Exception $e) {
            Log::error('Erreur assemblage fichier', [
                'fileId' => $fileId,
                'error' => $e->getMessage()
            ]);

            $this->markFileAsFailed($fileId, 'Erreur assemblage: ' . $e->getMessage());
        }
    }

    private function markFileAsCompleted(string $fileId): void
    {
        if (isset($this->uploadingFiles[$fileId])) {
            $this->uploadingFiles[$fileId]['status'] = 'completed';
            $this->uploadingFiles[$fileId]['progress'] = 100;

            $this->completedFiles[$fileId] = $this->uploadingFiles[$fileId];
            unset($this->uploadingFiles[$fileId]);

            $this->dispatch('file-completed', $fileId);
        }

        // Démarrer le prochain upload s'il y en a un en attente
        $this->startNextQueuedUpload();

        // Vérifier si tous les uploads sont terminés
        $this->checkAllUploadsCompleted();
    }
    private function startNextQueuedUpload(): void
    {
        if (!empty($this->queuedFiles) && count($this->uploadingFiles) < $this->maxConcurrentUploads) {
            $nextFileId = array_key_first($this->queuedFiles);
            $this->createPendingFile($nextFileId);
        }
    }

    private function markFileAsFailed(string $fileId, string $error): void
    {
        // Chercher dans queuedFiles ou uploadingFiles
        $fileData = $this->queuedFiles[$fileId] ?? $this->uploadingFiles[$fileId] ?? null;

        if ($fileData) {
            $fileData['status'] = 'failed';
            $fileData['error'] = $error;

            // Mettre à jour le PendingFile si il existe
            if ($fileData['pending_file_id']) {
                $pendingFile = PendingFile::find($fileData['pending_file_id']);
                if ($pendingFile) {
                    $pendingFile->markAsFailed();
                }
            }

            $this->failedFiles[$fileId] = $fileData;
            unset($this->queuedFiles[$fileId]);
            unset($this->uploadingFiles[$fileId]);

            $this->dispatch('file-failed', ['fileId' => $fileId, 'error' => $error]);
        }

        $this->checkAllUploadsCompleted();
    }

    public function cancelUpload(string $fileId): void
    {
        if (isset($this->uploadingFiles[$fileId])) {
            $fileData = $this->uploadingFiles[$fileId];

            // Supprimer le PendingFile et ses chunks
            if ($fileData['pending_file_id']) {
                $pendingFile = PendingFile::find($fileData['pending_file_id']);
                if ($pendingFile) {
                    // Nettoyer les chunks temporaires
                    for ($i = 0; $i < $fileData['chunks_total']; $i++) {
                        $chunkPath = $pendingFile->file_path . '.chunk.' . $i;
                        Storage::delete($chunkPath);
                    }

                    $pendingFile->delete();
                }
            }

            unset($this->uploadingFiles[$fileId]);
            $this->dispatch('file-cancelled', $fileId);
        }
    }

    public function retryUpload(string $fileId): void
    {
        if (isset($this->failedFiles[$fileId])) {
            // Remettre en queue
            $fileData = $this->failedFiles[$fileId];
            $fileData['status'] = 'queued';
            $fileData['error'] = null;
            $fileData['chunks_uploaded'] = 0;
            $fileData['progress'] = 0;
            $fileData['pending_file_id'] = null;

            $this->queuedFiles[$fileId] = $fileData;
            unset($this->failedFiles[$fileId]);
        }
    }

    private function checkAllUploadsCompleted(): void
    {
        if (empty($this->queuedFiles) && empty($this->uploadingFiles)) {
            $this->isUploading = false;
            $this->dispatch('all-uploads-completed', [
                'completed' => count($this->completedFiles),
                'failed' => count($this->failedFiles),
            ]);
        }
    }

    public function clearCompleted(): void
    {
        $this->completedFiles = [];
    }

    public function clearFailed(): void
    {
        $this->failedFiles = [];
    }

    public function getUploadStats(): array
    {
        return [
            'queued' => count($this->queuedFiles),
            'uploading' => count($this->uploadingFiles),
            'completed' => count($this->completedFiles),
            'failed' => count($this->failedFiles),
            'total' => count($this->queuedFiles) + count($this->uploadingFiles) +
                count($this->completedFiles) + count($this->failedFiles),
        ];
    }

    public function render()
    {
        return view('livewire.upload-files', [
            'stats' => $this->getUploadStats(),
        ]);
    }
}
