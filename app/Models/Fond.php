<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fond extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'code',
        'title',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    protected $appends = ['full_code'];

    /**
     * Code complet pour le Fond (identique au code simple)
     */
    public function getFullCodeAttribute(): string
    {
        return $this->code;
    }

    /**
     * Un fonds appartient à un utilisateur (créateur)
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Un fonds a plusieurs corpus
     */
    public function corpuses(): HasMany
    {
        return $this->hasMany(Corpus::class);
    }

    /**
     * Un fonds peut avoir des items directement associés
     */
    public function items(): MorphMany
    {
        return $this->morphMany(Item::class, 'itemable');
    }

    /**
     * Obtenir tous les items principaux du fonds
     */
    public function mainItems(): MorphMany
    {
        return $this->items()->whereNull('item_type_id');
    }

    /**
     * Obtenir tous les items secondaires du fonds
     */
    public function secondaryItems(): MorphMany
    {
        return $this->items()->whereNotNull('item_type_id');
    }

    /**
     * Calculer la taille totale des fichiers du fonds
     */
    public function getTotalFileSizeAttribute(): int
    {
        $directItems = $this->items()->sum('file_size') ?? 0;

        $corpusItems = $this->corpuses()
            ->with(['items', 'collections.items'])
            ->get()
            ->sum(function ($corpus) {
                return $corpus->items->sum('file_size') +
                    $corpus->collections->sum(function ($collection) {
                        return $collection->items->sum('file_size');
                    });
            });

        return $directItems + $corpusItems;
    }

    /**
     * Scope pour charger les statistiques
     */
    public function scopeWithStatistics($query)
    {
        return $query->withCount([
            'corpuses',
            'items',
            'corpuses as collections_count' => function ($q) {
                $q->withCount('collections');
            }
        ]);
    }

}
