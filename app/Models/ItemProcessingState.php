<?php

namespace App\Models;

use App\Enums\ItemProcessingStatus;
use App\Enums\ItemProcessingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemProcessingState extends Model
{
    protected $guarded = [];

    protected $casts = [
        'process_type' => ItemProcessingType::class,
        'status' => ItemProcessingStatus::class,
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
