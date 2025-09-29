<?php

namespace App\Models;

use App\Traits\HasHierarchicalItems;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Fond extends Model
{
    use HasFactory, SoftDeletes, HasHierarchicalItems;


    protected $fillable = [
        'code',
        'title',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['full_code', 'stats'];


    /**
     * Code complet pour le Fond (identique au code simple)
     */
    public function getFullCodeAttribute(): string
    {
        return $this->code;
    }

    /**
     * Implémentation requise par HasHierarchicalItems
     */
    protected function getHierarchyPrefix(): string
    {
        return $this->full_code;
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
     * Méthodes spécifiques au Fond
     */
    public function getCorpusesStatsAttribute(): array
    {
        return $this->corpuses->map(function ($corpus) {
            return [
                'corpus' => $corpus->full_code,
                'title' => $corpus->title,
                'stats' => $corpus->stats,
            ];
        })->toArray();
    }

    /**
     * Scope avec statistiques complètes
     */
    public function scopeWithCompleteStats($query)
    {
        return $query->withItemStats()
            ->withCount(['corpuses', 'corpuses as collections_count' => function ($q) {
                $q->withCount('collections');
            }]);
    }


}
