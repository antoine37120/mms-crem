<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Corpus extends Model
{
    use HasFactory;

    protected $fillable = [
        'fond_id',
        'code',
        'title',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Un corpus appartient à un fonds
     */
    public function fond(): BelongsTo
    {
        return $this->belongsTo(Fond::class);
    }

    /**
     * Un corpus appartient à un utilisateur (créateur)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Un corpus a plusieurs collections
     */
    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    /**
     * Un corpus peut avoir des items directement associés
     */
    public function items(): MorphMany
    {
        return $this->morphMany(Item::class, 'itemable');
    }

    /**
     * Obtenir tous les items principaux du corpus
     */
    public function mainItems(): MorphMany
    {
        return $this->items()->whereNull('item_type_id');
    }

    /**
     * Obtenir tous les items secondaires du corpus
     */
    public function secondaryItems(): MorphMany
    {
        return $this->items()->whereNotNull('item_type_id');
    }
}
