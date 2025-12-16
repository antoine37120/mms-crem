<?php

namespace App\Models;

use App\Enums\MediaVariationStatus;
use App\Enums\MediaVariationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaVariation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'type' => MediaVariationType::class,
        'status' => MediaVariationStatus::class,
        'is_streaming' => 'boolean',
        'generation_params' => 'array',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
