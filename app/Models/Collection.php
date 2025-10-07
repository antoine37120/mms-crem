<?php

namespace App\Models;

use App\Traits\HasHierarchicalItems;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Collection extends Model implements Auditable
{
    use \OwenIt\Auditing\Auditable;
    use HasFactory, SoftDeletes, HasHierarchicalItems;


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

    protected $appends = ['full_code', 'stats'];


    /**
     * Code complet assemblé avec corpus et fonds
     */
    public function getFullCodeAttribute(): string
    {
        return $this->code ;
    }

    /**
     * Implémentation requise par HasHierarchicalItems
     */
    protected function getHierarchyPrefix(): string
    {
        return $this->full_code;
    }

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
     * Obtenir le fonds parent via le corpus
     */
    public function fond(): BelongsTo
    {
        return $this->corpus()->with('fond');
    }

    /**
     * Scope avec informations hiérarchiques
     */
    public function scopeWithHierarchy($query)
    {
        return $query->with(['corpus.fond:id,code,title']);
    }

}
