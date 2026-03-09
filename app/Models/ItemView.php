<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemView extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'user_id',
        'is_authenticated',
        'ip_address',
        'country',
        'user_agent',
        'referer',
    ];

    protected $casts = [
        'is_authenticated' => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
