<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'suffix',
        'description',
        'requires_language',
        'allowed_extensions',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'requires_language' => 'boolean',
        'is_active' => 'boolean',
        'allowed_extensions' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Un type d'item appartient à un utilisateur (créateur)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Un type d'item est utilisé par plusieurs items
     */
    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'item_type_id');
    }

    /**
     * Scope pour les types actifs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Vérifier si une extension est autorisée pour ce type
     */
    public function isExtensionAllowed(string $extension): bool
    {
        if (!$this->allowed_extensions) {
            return true; // Si aucune restriction, tout est autorisé
        }

        return in_array(strtolower($extension), array_map('strtolower', $this->allowed_extensions));
    }
}
