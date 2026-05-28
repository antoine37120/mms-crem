<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaClient extends Model
{
    protected $fillable = [
        'app_id',
        'name',
        'encrypted_secret',
        'encrypted_secret_previous',
        'previous_expires_at',
        'allowed_origins',
        'token_ttl',
        'can_access_not_public',
        'is_active',
    ];

    protected $casts = [
        'allowed_origins' => 'array',
        'can_access_not_public' => 'boolean',
        'is_active' => 'boolean',
        'previous_expires_at' => 'datetime',
    ];

    protected $hidden = [
        'encrypted_secret',
        'encrypted_secret_previous',
    ];
}
