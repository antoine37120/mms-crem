<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

class PendingFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_name',
        'stored_name',
        'file_path',
        'file_size',
        'file_type',
        'file_extension',
        'upload_status',
        'suggested_code',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    /**
     * Statuts possibles pour l'upload
     */
    public const STATUS_UPLOADING = 'uploading';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_UPLOADING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
    ];

    /**
     * Relation avec l'utilisateur qui a uploadé le fichier
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope pour récupérer les fichiers d'un utilisateur spécifique
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope pour récupérer les fichiers par statut
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('upload_status', $status);
    }

    /**
     * Scope pour les fichiers complétés
     */
    #[Scope]
    protected function completed(Builder $query): void
    {
        $query->where('upload_status', self::STATUS_COMPLETED);
    }

    /**
     * Scope pour les fichiers en cours d'upload
     */
    public function scopeUploading($query)
    {
        return $query->where('upload_status', self::STATUS_UPLOADING);
    }

    /**
     * Scope pour les fichiers échoués
     */
    public function scopeFailed($query)
    {
        return $query->where('upload_status', self::STATUS_FAILED);
    }

    /**
     * Vérifie si le fichier a une cote suggérée
     */
    public function hasSuggestedCode(): bool
    {
        return !empty($this->suggested_code);
    }

    /**
     * Marque l'upload comme terminé
     */
    public function markAsCompleted(): void
    {
        $this->update(['upload_status' => self::STATUS_COMPLETED]);
    }

    /**
     * Marque l'upload comme échoué
     */
    public function markAsFailed(): void
    {
        $this->update(['upload_status' => self::STATUS_FAILED]);
    }

    /**
     * Récupère la taille formatée du fichier
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Récupère le chemin complet du fichier temporaire
     */
    public function getFullPathAttribute(): string
    {
        return Storage::path($this->file_path);
    }

    /**
     * Vérifie si le fichier temporaire existe sur le disque
     */
    public function fileExists(): bool
    {
        return Storage::exists($this->file_path);
    }

    /**
     * Supprime le fichier temporaire du disque
     */
    public function deleteTemporaryFile(): bool
    {
        if ($this->fileExists()) {
            return Storage::delete($this->file_path);
        }

        return true;
    }

    /**
     * Analyse le nom original pour suggérer une cote basée sur les fonds existants
     */
    public function suggestCodeFromFileName(): ?string
    {
        // Récupérer tous les codes de fonds existants
        $fondsCodes = \App\Models\Fond::pluck('code')->toArray();

        if (empty($fondsCodes)) {
            return null;
        }

        $fileName = $this->original_name;

        // Pour chaque code de fond, chercher s'il apparaît dans le nom du fichier
        foreach ($fondsCodes as $fondsCode) {
            // Échapper les caractères spéciaux pour regex
            $escapedCode = preg_quote($fondsCode, '/');

            // Pattern : le code du fonds suivi de caractères jusqu'à un espace ou tiret (ou fin de chaîne)
            $pattern = '/(' . $escapedCode . '[^\\s\\-]*)/i';

            if (preg_match($pattern, $fileName, $matches)) {
                // Nettoyer la cote trouvée (enlever les caractères indésirables à la fin)
                $suggestedCode = $matches[1];

                // Nettoyer les caractères de fin non désirés (points, parenthèses, etc.)
                $suggestedCode = rtrim($suggestedCode, '.,()[]{}');

                return strtoupper($suggestedCode);
            }
        }

        return null;
    }


    /**
     * Met à jour la cote suggérée basée sur le nom du fichier
     */
    public function updateSuggestedCode(): void
    {
        $suggestedCode = $this->suggestCodeFromFileName();

        if ($suggestedCode && $suggestedCode !== $this->suggested_code) {
            $this->update(['suggested_code' => $suggestedCode]);
        }
    }

    /**
     * Extrait et stocke les métadonnées du fichier
     */
    public function extractMetadata(): array
    {
        $metadata = [];

        if (!$this->fileExists()) {
            return $metadata;
        }

        $filePath = $this->full_path;

        // Métadonnées de base
        $metadata['mime_type'] = mime_content_type($filePath);
        $metadata['file_size'] = filesize($filePath);

        // Métadonnées spécifiques selon le type
        if (Str::startsWith($this->file_type, 'image/')) {
            $this->extractImageMetadata($filePath, $metadata);
        } elseif (Str::startsWith($this->file_type, 'audio/')) {
            $this->extractAudioMetadata($filePath, $metadata);
        } elseif (Str::startsWith($this->file_type, 'video/')) {
            $this->extractVideoMetadata($filePath, $metadata);
        }

        // Sauvegarder les métadonnées
        $this->update(['metadata' => $metadata]);

        return $metadata;
    }

    /**
     * Extrait les métadonnées d'une image
     */
    private function extractImageMetadata(string $filePath, array &$metadata): void
    {
        if (function_exists('getimagesize')) {
            $imageInfo = getimagesize($filePath);
            if ($imageInfo) {
                $metadata['width'] = $imageInfo[0];
                $metadata['height'] = $imageInfo[1];
                $metadata['image_type'] = $imageInfo[2];
            }
        }
    }

    /**
     * Extrait les métadonnées d'un fichier audio
     */
    private function extractAudioMetadata(string $filePath, array &$metadata): void
    {
        // TODO: Implémenter l'extraction des métadonnées audio
        // Nécessitera une bibliothèque comme getID3 ou FFMpeg
        $metadata['duration'] = null;
        $metadata['bitrate'] = null;
        $metadata['sample_rate'] = null;
    }

    /**
     * Extrait les métadonnées d'un fichier vidéo
     */
    private function extractVideoMetadata(string $filePath, array &$metadata): void
    {
        // TODO: Implémenter l'extraction des métadonnées vidéo
        // Nécessitera FFMpeg ou une bibliothèque similaire
        $metadata['duration'] = null;
        $metadata['width'] = null;
        $metadata['height'] = null;
        $metadata['fps'] = null;
    }

    /**
     * Boot du modèle
     */
    protected static function boot()
    {
        parent::boot();

        // Lors de la création, suggérer automatiquement une cote
        static::creating(function ($pendingFile) {
            if (!$pendingFile->suggested_code) {
                $pendingFile->suggested_code = $pendingFile->suggestCodeFromFileName();
            }
        });

        // Lors de la suppression, nettoyer le fichier temporaire
        static::deleting(function ($pendingFile) {
            $pendingFile->deleteTemporaryFile();
        });
    }
}
