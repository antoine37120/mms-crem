<?php

namespace App\Models;

use App\Traits\HasHierarchicalItems;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Corpus extends Model implements Auditable
{
    use HasFactory, HasHierarchicalItems, SoftDeletes;
    use \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'code',
        'title',
        'created_by',
        'public_access',
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
     * Un corpus peut appartenir à plusieurs fonds
     */
    public function fonds(): BelongsToMany
    {
        return $this->belongsToMany(Fond::class);
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
    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(Collection::class);
    }

    /**
     * Scope avec statistiques complètes
     */
    public function scopeWithCompleteStats($query)
    {
        return $query->withItemStats()
            ->withCount(['collections'])
            ->with(['fonds:id,code,title']);
    }
}
