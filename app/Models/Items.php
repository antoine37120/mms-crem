<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'itemable_type',
        'itemable_id',
        'item_type_id',
        'code',
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
    ];

    protected $casts = [
        'file_size' => 'integer',
        'duration' => 'integer',
        'upload_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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
        return $query->whereNull('item_type_id');
    }

    /**
     * Scope pour les items secondaires (avec type)
     */
    public function scopeSecondary($query)
    {
        return $query->whereNotNull('item_type_id');
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
        return is_null($this->item_type_id);
    }

    /**
     * Vérifier si c'est un item secondaire
     */
    public function isSecondary(): bool
    {
        return !is_null($this->item_type_id);
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
