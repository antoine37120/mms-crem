<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Collection extends Model
{
    use HasFactory;

    protected $fillable = [
        'corpus_id',
        'code',
        'title',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Une collection appartient à un corpus
     */
    public function corpus(): BelongsTo
    {
        return $this->belongsTo(Corpus::class);
    }

    /**
     * Une collection appartient à un utilisateur (créateur)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Une collection peut avoir des items directement associés
     */
    public function items(): MorphMany
    {
        return $this->morphMany(Item::class, 'itemable');
    }

    /**
     * Obtenir tous les items principaux de la collection
     */
    public function mainItems(): MorphMany
    {
        return $this->items()->whereNull('item_type_id');
    }

    /**
     * Obtenir tous les items secondaires de la collection
     */
    public function secondaryItems(): MorphMany
    {
        return $this->items()->whereNotNull('item_type_id');
    }

    /**
     * Obtenir le fonds parent via le corpus
     */
    public function fond(): BelongsTo
    {
        return $this->corpus()->with('fond');
    }
}
