<?php

namespace App\Models;

use App\Enums\ScannedFileStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScannedFile extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => ScannedFileStatus::class,
        'last_scanned_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
