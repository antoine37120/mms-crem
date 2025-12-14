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

    // Unified file list
    public array $files = [];

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
                    $this->cleanupChunks($pendingFile);
                    $pendingFile->deleteTemporaryFile();
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
            $chunkPattern = $pendingFile->file_path . '.chunk.*';
            $directory = dirname($pendingFile->file_path);

            if (Storage::exists($directory)) {
                $allFiles = Storage::files($directory);
                $chunkFiles = array_filter($allFiles, function($file) use ($pendingFile) {
                    return str_starts_with($file, $pendingFile->file_path . '.chunk.');
                });

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

            $this->files[$fileId] = [
                'id' => $fileId,
                'name' => $fileData['name'],
                'size' => $fileData['size'],
                'type' => $fileData['type'],
                'signature' => $fileData['signature'] ?? null, // Capture client signature
                'chunks_total' => ceil($fileData['size'] / $this->CHUNK_SIZE),
                'chunks_uploaded' => 0,
                'progress' => 0,
                'status' => 'queued',
                'pending_file_id' => null,
                'error' => null,
                'suggested_code' => null,
                'added_at' => now()->timestamp, // For sorting if needed
            ];
        }

        $this->dispatch('queue-updated', count($this->files));
    }

    public function removeFromQueue(string $fileId): void
    {
        unset($this->files[$fileId]);
    }

    public function startAllUploads(): void
    {
        $queuedCount = collect($this->files)->where('status', 'queued')->count();
        if ($queuedCount === 0) {
            return;
        }

        $this->isUploading = true;

        $uploadingCount = collect($this->files)->where('status', 'uploading')->count();
        $slotsAvailable = $this->maxConcurrentUploads - $uploadingCount;

        if ($slotsAvailable > 0) {
            $filesToStart = collect($this->files)
                ->where('status', 'queued')
                ->take($slotsAvailable);

            foreach ($filesToStart as $fileId => $fileData) {
                $this->createPendingFile($fileId);
            }
        }
    }

    private function createPendingFile(string $fileId): void
    {
        if (!isset($this->files[$fileId])) {
            return;
        }

        $fileData = $this->files[$fileId];

        try {
            $storedName = Str::uuid() . '.' . pathinfo($fileData['name'], PATHINFO_EXTENSION);
            $filePath = 'pending-uploads/' . date('Y/m/d') . '/' . $storedName;

            $pendingFile = PendingFile::create([
                'user_id' => auth()->id(),
                'original_name' => $fileData['name'],
                'stored_name' => $storedName,
                'file_path' => $filePath,
                'file_size' => $fileData['size'],
                'file_type' => $fileData['type'],
                'file_extension' => strtolower(pathinfo($fileData['name'], PATHINFO_EXTENSION)),
                'upload_status' => PendingFile::STATUS_UPLOADING,
                'client_signature' => $fileData['signature'], // Store signature
            ]);

            $this->files[$fileId]['pending_file_id'] = $pendingFile->id;
            $this->files[$fileId]['suggested_code'] = $pendingFile->suggested_code;
            $this->files[$fileId]['status'] = 'uploading';

            Log::warning('Launch start-file-upload', [
                'pending_file_id' => $fileId,
            ]);

            $this->dispatch('start-file-upload', [
                'fileId' => $fileId,
                'pendingFileId' => $pendingFile->id,
                'chunkSize' => $this->CHUNK_SIZE,
                'totalChunks' => $fileData['chunks_total'],
            ]);

            $this->dispatch('pending-files-created');

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
        Log::info('=== CHUNK REQUEST START ===', [
            'fileId' => $fileId,
            'chunkIndex' => $chunkIndex,
            'session_id' => session()->getId(),
        ]);

        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            if (function_exists('gc_collect_cycles')) {
                gc_collect_cycles();
            }

            $pendingFile = PendingFile::find($pendingFileId);

            if (!$pendingFile) {
                throw new \Exception('Fichier en attente non trouvé');
            }

            $chunkData = base64_decode($chunk);
            unset($chunk);

            $directory = dirname($pendingFile->file_path);
            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory);
            }

            $chunkPath = $pendingFile->file_path . '.chunk.' . $chunkIndex;
            $stream = fopen('php://memory', 'r+');
            fwrite($stream, $chunkData);
            rewind($stream);
            unset($chunkData);

            Storage::put($chunkPath, $stream);
            fclose($stream);

            if (isset($this->files[$fileId])) {
                $this->files[$fileId]['chunks_uploaded']++;
                $totalChunks = $this->files[$fileId]['chunks_total'];
                $this->files[$fileId]['progress'] = ($this->files[$fileId]['chunks_uploaded'] / $totalChunks) * 100;

                if ($this->files[$fileId]['chunks_uploaded'] >= $totalChunks) {
                    $this->assembleFile($fileId, $pendingFileId);
                }
            }

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
            $fileData = $this->files[$fileId];
            $finalPath = $pendingFile->full_path; // Use full path for file operations
            $tempDir = dirname($finalPath);

            // Initialize hash context for streaming MD5 calculation
            $hashContext = hash_init('md5');

            // Open destination file for writing
            $outputStream = fopen($finalPath, 'w+');
            if ($outputStream === false) {
                throw new \Exception("Impossible de créer le fichier final.");
            }

            // Iterate through chunks
            for ($i = 0; $i < $fileData['chunks_total']; $i++) {
                $chunkPath = $pendingFile->file_path . '.chunk.' . $i;

                if (!Storage::exists($chunkPath)) {
                    fclose($outputStream);
                    throw new \Exception("Chunk manquant: index $i");
                }

                // Read chunk using stream to save memory
                $chunkStream = Storage::readStream($chunkPath);

                while (!feof($chunkStream)) {
                    $buffer = fread($chunkStream, 8192); // Read 8KB buffer
                    if ($buffer !== false) {
                        // Update hash
                        hash_update($hashContext, $buffer);
                        // Write to final file
                        fwrite($outputStream, $buffer);
                    }
                }

                fclose($chunkStream);
            }

            fclose($outputStream);

            // Finalize hash
            $serverSignature = hash_final($hashContext);

            // Verify Signature
            if (!empty($pendingFile->client_signature)) {
                if ($serverSignature !== $pendingFile->client_signature) {
                     // Delete file and chunks
                     for ($i = 0; $i < $fileData['chunks_total']; $i++) {
                        $chunkPath = $pendingFile->file_path . '.chunk.' . $i;
                        Storage::delete($chunkPath);
                    }
                    Storage::delete($pendingFile->file_path); // Use relative path for Storage::delete

                    throw new \Exception("Vérification de la signature a échoué. Le fichier est corrompu.");
                }
            }

            // Clean chunks
             for ($i = 0; $i < $fileData['chunks_total']; $i++) {
                $chunkPath = $pendingFile->file_path . '.chunk.' . $i;
                Storage::delete($chunkPath);
            }

            $pendingFile->markAsCompleted();

            try {
                $pendingFile->extractMetadata();
            } catch (\Exception $e) {
                Log::warning('Erreur extraction métadonnées', ['error' => $e->getMessage()]);
            }

            $this->markFileAsCompleted($fileId);

        } catch (\Exception $e) {
            Log::error('Erreur assemblage fichier', [
                'fileId' => $fileId,
                'error' => $e->getMessage()
            ]);

            $this->markFileAsFailed($fileId, $e->getMessage());
        }
    }

    private function markFileAsCompleted(string $fileId): void
    {
        if (isset($this->files[$fileId])) {
            $this->files[$fileId]['status'] = 'completed';
            $this->files[$fileId]['progress'] = 100;
            $this->dispatch('file-completed', $fileId);
        }

        $this->startNextQueuedUpload();
        $this->checkAllUploadsCompleted();
    }

    private function startNextQueuedUpload(): void
    {
        $uploadingCount = collect($this->files)->where('status', 'uploading')->count();
        if ($uploadingCount < $this->maxConcurrentUploads) {
             $nextFile = collect($this->files)
                ->where('status', 'queued')
                ->first();

            if ($nextFile) {
                $this->createPendingFile($nextFile['id']);
            }
        }
    }

    private function markFileAsFailed(string $fileId, string $error): void
    {
        if (isset($this->files[$fileId])) {
            $this->files[$fileId]['status'] = 'failed';
            $this->files[$fileId]['error'] = $error;

            if ($this->files[$fileId]['pending_file_id']) {
                $pendingFile = PendingFile::find($this->files[$fileId]['pending_file_id']);
                if ($pendingFile) {
                    $pendingFile->markAsFailed();
                }
            }

            $this->dispatch('file-failed', ['fileId' => $fileId, 'error' => $error]);
        }

        $this->startNextQueuedUpload(); // Try to start next one even if this one failed
        $this->checkAllUploadsCompleted();
    }

    public function cancelUpload(string $fileId): void
    {
        if (isset($this->files[$fileId])) {
            $fileData = $this->files[$fileId];

            // Only can cancel queued or uploading
            if (!in_array($fileData['status'], ['queued', 'uploading'])) {
                return;
            }

             if ($fileData['status'] === 'uploading' && $fileData['pending_file_id']) {
                $pendingFile = PendingFile::find($fileData['pending_file_id']);
                if ($pendingFile) {
                    for ($i = 0; $i < $fileData['chunks_total']; $i++) {
                        $chunkPath = $pendingFile->file_path . '.chunk.' . $i;
                        Storage::delete($chunkPath);
                    }
                    $pendingFile->delete();
                }
            }

            unset($this->files[$fileId]);
            $this->dispatch('file-cancelled', $fileId);
            $this->startNextQueuedUpload(); // A slot might have opened up
        }
    }

    public function retryUpload(string $fileId): void
    {
        if (isset($this->files[$fileId]) && $this->files[$fileId]['status'] === 'failed') {
            $this->files[$fileId]['status'] = 'queued';
            $this->files[$fileId]['error'] = null;
            $this->files[$fileId]['chunks_uploaded'] = 0;
            $this->files[$fileId]['progress'] = 0;
            $this->files[$fileId]['pending_file_id'] = null;

            // Trigger start if we have slots
            $this->startNextQueuedUpload();
        }
    }

    private function checkAllUploadsCompleted(): void
    {
        $queued = collect($this->files)->where('status', 'queued')->count();
        $uploading = collect($this->files)->where('status', 'uploading')->count();

        if ($queued === 0 && $uploading === 0) {
            $this->isUploading = false;
            $this->dispatch('all-uploads-completed', [
                'completed' => collect($this->files)->where('status', 'completed')->count(),
                'failed' => collect($this->files)->where('status', 'failed')->count(),
            ]);
        }
    }

    public function clearCompleted(): void
    {
        $this->files = collect($this->files)->reject(function ($file) {
            return $file['status'] === 'completed';
        })->toArray();
    }

    public function clearFailed(): void
    {
         $this->files = collect($this->files)->reject(function ($file) {
            return $file['status'] === 'failed';
        })->toArray();
    }

    public function getUploadStats(): array
    {
        $files = collect($this->files);
        return [
            'queued' => $files->where('status', 'queued')->count(),
            'uploading' => $files->where('status', 'uploading')->count(),
            'completed' => $files->where('status', 'completed')->count(),
            'failed' => $files->where('status', 'failed')->count(),
            'total' => $files->count(),
        ];
    }

    public function render()
    {
        return view('livewire.upload-files', [
            'stats' => $this->getUploadStats(),
        ]);
    }
}
