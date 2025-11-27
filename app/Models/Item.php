<?php

namespace App\Models;

use App\Traits\HasHierarchicalItems;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use OwenIt\Auditing\Contracts\Auditable;
use App\Observers\ItemObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;

#[ObservedBy([ItemObserver::class])]
class Item extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory, SoftDeletes, HasHierarchicalItems;


    protected $fillable = [
        'itemable_type',
        'itemable_id',
        'item_type_id',
        'is_sub',
        'code',
        'code_prefix',
        'code_suffix',
        'title',
        'language_code',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'file_extension',
        'duration',
        'upload_date',
        'uploaded_by',
        'created_by',
        'main_items_count',
        'secondary_items_count',
    ];

    protected $casts = [
        'is_sub' => 'boolean',
        'file_size' => 'integer',
        'duration' => 'integer',
        'upload_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['full_code', 'stats'];

    /**
     * Attributes to exclude from the Audit.
     *
     * @var array
     */
    protected $auditExclude = [
       /* 'code_prefix',
        'code_suffix',*/
    ];



    /**
     * Code complet assemblé selon l'entité parente
     */
    public function getFullCodeAttribute(): string
    {
        return $this->code;
    }

    /**
     * Implémentation pour les items (enfants)
     */
    protected function getHierarchyPrefix(): string
    {
        return $this->full_code;
    }


    /**
     * Boot du modèle - événements automatiques
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($item) {
            $item->code = $item->code_prefix ;
            if (isset($item->code_suffix) && $item->code_suffix != '') {
                $item->code = $item->code.'_'.$item->code_suffix ;
            }
            $item->processFileUpload();
            //$item->generateCodeIfEmpty();
            $item->setDefaultUploadDate();
            $item->setDefaultUsers();

        });

        static::updating(function ($item) {
            $item->code = $item->code_prefix ;
            if (isset($item->code_suffix) && $item->code_suffix != '') {
                $item->code = $item->code.'_'.$item->code_suffix ;
            }
            // Si le fichier a changé, retraiter les métadonnées
            if ($item->isDirty('file_path')) {
                $item->processFileUpload();
            } else {
                if($item->isDirty('code_suffix')) {
                    $old_code =  $item->getOriginal('code') ;
                    $actual_file_path = $item->file_path ;
                    $item->file_path = str_replace($old_code, $item->code, $item->file_path);

                    \Log::info("Search file path: " . $old_code);
                    \Log::info("New file code: " . $item->code);
                    \Log::info("New file path: " . $item->file_path);
                    Storage::disk('original_medias')->move($actual_file_path, $item->file_path);
                }
            }

        });

        static::created(function ($item) {
            $item->invalidateParentsCache();
        });

        static::updated(function ($item) {
            $item->invalidateParentsCache();
        });

        static::deleted(function ($item) {
            $item->invalidateParentsCache();
        });



    }

    /**
     * Invalider le cache des entités parentes
     */
    protected function invalidateParentsCache(): void
    {
        if ($this->itemable) {
            if (method_exists($this->itemable, 'clearStatsCache')) {
                $this->itemable->clearStatsCache();
            }
        }
    }

    /**
     * Traiter l'upload de fichier et extraire les métadonnées
     */
    public function processFileUpload(): void
    {
        if (!$this->file_path) {
            return;
        }



        // Déterminer le chemin complet du fichier
        $fullPath = Storage::disk('original_medias')->path($this->file_path);

        if (!file_exists($fullPath)) {
            return;
        }

        // Extraire les métadonnées de base
        $this->file_size = filesize($fullPath);
        $this->file_type = mime_content_type($fullPath);

        // Extraire le nom et l'extension
        $pathInfo = pathinfo($this->file_path);
        /*$this->file_name = $pathInfo['basename'];
        $this->file_name = "nnnnnn";*/
        $this->file_extension = strtolower($pathInfo['extension'] ?? '');


        $createdAt = now();
        $datePath = 'items/' . $createdAt->format('Y/m/d') . '';
        $fileName = $this->code  . '.' . $this->file_extension ;
        $newFilePath = $datePath .'/'. $fileName;
        // Créer le répertoire de destination s'il n'existe pas
        Storage::disk('original_medias')->makeDirectory($datePath);
        Storage::disk('original_medias')->putFileAs($datePath, new File($fullPath), $fileName);

        $old_file = $this->getOriginal('file_path');
        if($old_file !== null) {
            $old_file_path = Storage::disk('original_medias')->path($old_file);
            if (file_exists($old_file_path)) {
                Storage::disk('original_medias')->delete($old_file);
            }
        }

        //Possible que le fichier soit déjà au bon endroit mais qu'il ai été réécrit
        if($this->file_path != $newFilePath) {
            Storage::disk('original_medias')->delete($this->file_path);
        }
        // Mettre à jour le chemin du fichier dans les données
        $this->file_path = $newFilePath;

        // Traitement spécifique selon le type de fichier
        $this->processByFileType($fullPath);
    }

    /**
     * Traitement spécifique selon le type de fichier
     */
    private function processByFileType(string $fullPath): void
    {
        if ($this->isAudio() || $this->isVideo()) {
            $this->extractMediaDuration($fullPath);
        }
    }

    /**
     * Définir la date d'upload par défaut
     */
    public function setDefaultUploadDate(): void
    {
        if (!$this->upload_date) {
            $this->upload_date = now()->toDateString();
        }
    }

    /**
     * Définir les utilisateurs par défaut
     */
    public function setDefaultUsers(): void
    {
        $currentUserId = auth()->id();

        if (!$this->created_by && $currentUserId) {
            $this->created_by = $currentUserId;
        }

        if (!$this->uploaded_by && $currentUserId) {
            $this->uploaded_by = $currentUserId;
        }
    }


    /**
     * Extraire la durée des fichiers audio/vidéo
     */
    private function extractMediaDuration(string $fullPath): void
    {
        try {

            // Méthode alternative avec FFMpeg si disponible
            if (function_exists('exec')) {
                $ffprobePath = env('FFPROBE_BINARIES', 'ffprobe');

                $command = '"' . $ffprobePath . '" -v quiet -show_entries format=duration -hide_banner -of csv="p=0" "' . $fullPath . '"';
                // log de la commande
                \Log::info("Executing command: " . $command);
                exec($command, $output, $returnCode);
                $result = Process::run($command);
                $output = $result->output();
                $returnCode = $result->exitCode();
                // log de la sortie
                \Log::info("FFProbe Output: " .  $output);
                \Log::info("FFProbe Return Code: " . $returnCode);
                if ($result->successful() && !empty($output)) {
                    $this->duration = (int) round((float) $output);
                    return;
                }
                if($result->errorOutput()) {
                    \Log::error("FFProbe Error: " . $result->errorOutput());
                }
            }

            // Méthode de fallback avec exif pour certains formats
            if (function_exists('exif_read_data') && in_array($this->file_extension, ['mp4', 'mov'])) {
                $exif = @exif_read_data($fullPath);
                if ($exif && isset($exif['Duration'])) {
                    // Parser le format de durée d'EXIF si nécessaire
                    $this->duration = $this->parseExifDuration($exif['Duration']);
                    return;
                }
            }

        } catch (\Exception $e) {
            // Log l'erreur mais ne bloque pas la création
            \Log::warning("Impossible d'extraire la durée du fichier {$fullPath}: " . $e->getMessage());
        }
    }

    /**
     * Parser la durée depuis les données EXIF
     */
    private function parseExifDuration($duration): ?int
    {
        // Implémenter selon le format retourné par EXIF
        // Format typique: "00:03:25" ou similaire
        if (preg_match('/(\d+):(\d+):(\d+)/', $duration, $matches)) {
            return ($matches[1] * 3600) + ($matches[2] * 60) + $matches[3];
        }

        return null;
    }

    /**
     * Générer automatiquement le code si vide
     */
    public function generateCodeIfEmpty(): void
    {
        if ($this->code) {
            return; // Code déjà défini
        }

        $baseCode = $this->generateBaseCode();
        if ($baseCode) {
            $this->code = $this->ensureUniqueCode($baseCode);
        }
    }

    /**
     * Relation polymorphique vers l'entité parente
     */
    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Un item appartient à un type (optionnel)
     */
    public function itemType(): BelongsTo
    {
        return $this->belongsTo(ItemType::class, 'item_type_id');
    }

    /**
     * Un item appartient à un utilisateur (créateur)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Un item appartient à un utilisateur (uploadeur)
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Un item peut avoir des items enfants (secondaires)
     */
    public function childItems(): MorphMany
    {
        return $this->morphMany(Item::class, 'itemable');
    }

    /**
     * Scope pour les items principaux (sans type)
     */
    public function scopeMain($query)
    {
        return $query->where('is_sub', false);
    }

    /**
     * Scope pour les items secondaires (avec type)
     */
    public function scopeSecondary($query)
    {
        return $query->where('is_sub', true);
    }

    /**
     * Scope pour filtrer par type de fichier
     */
    public function scopeByFileType($query, string $fileType)
    {
        return $query->where('file_type', 'like', $fileType . '%');
    }

    /**
     * Vérifier si c'est un item principal
     */
    public function isMain(): bool
    {
        return $this->is_sub == false;
    }

    /**
     * Vérifier si c'est un item secondaire
     */
    public function isSecondary(): bool
    {
        return $this->is_sub == true;
    }

    /**
     * Vérifier si c'est un fichier audio
     */
    public function isAudio(): bool
    {
        return str_starts_with($this->file_type ?? '', 'audio/');
    }

    /**
     * Vérifier si c'est un fichier vidéo
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->file_type ?? '', 'video/');
    }

    /**
     * Vérifier si c'est un fichier image
     */
    public function isImage(): bool
    {
        return str_starts_with($this->file_type ?? '', 'image/');
    }

    /**
     * Obtenir la taille du fichier formatée
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = $this->file_size;

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Obtenir la durée formatée (pour audio/vidéo)
     */
    public function getFormattedDurationAttribute(): ?string
    {
        if (!$this->duration) {
            return null;
        }

        $hours = floor($this->duration / 3600);
        $minutes = floor(($this->duration % 3600) / 60);
        $seconds = $this->duration % 60;

        if ($hours > 0) {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
        }

        return sprintf('%02d:%02d', $minutes, $seconds);
    }
}
