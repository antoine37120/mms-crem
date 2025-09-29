<?php

namespace App\Models;

use App\Traits\HasHierarchicalItems;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Corpus extends Model
{
    use HasFactory, SoftDeletes, HasHierarchicalItems;

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

    protected $appends = ['full_code', 'stats'];


    /**
     * Code complet assemblé avec le fonds parent
     */
    public function getFullCodeAttribute(): string
    {
        /*if ($this->fond && $this->fond->code) {
            return $this->fond->code . '_' . $this->code;
        }*/
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
     * Scope avec statistiques complètes
     */
    public function scopeWithCompleteStats($query)
    {
        return $query->withItemStats()
            ->withCount(['collections'])
            ->with(['fond:id,code,title']);
    }

}
