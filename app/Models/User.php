<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasMany;


class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'admin_access',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'admin_access' => 'boolean',


        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->admin_access;
    }

    /**
     * Les fonds créés par cet utilisateur
     */
    public function createdFonds(): HasMany
    {
        return $this->hasMany(Fond::class, 'created_by'); // Changé de Fonds à Fond
    }

    /**
     * Les corpus créés par cet utilisateur
     */
    public function createdCorpuses(): HasMany // Renommé pour la clarté
    {
        return $this->hasMany(Corpus::class, 'created_by');
    }


    /**
     * Les collections créées par cet utilisateur
     */
    public function createdCollections(): HasMany
    {
        return $this->hasMany(Collection::class, 'created_by');
    }

    /**
     * Les types d'items créés par cet utilisateur
     */
    public function createdItemTypes(): HasMany
    {
        return $this->hasMany(ItemType::class, 'created_by');
    }

    /**
     * Les items créés par cet utilisateur
     */
    public function createdItems(): HasMany
    {
        return $this->hasMany(Item::class, 'created_by');
    }

    /**
     * Les items uploadés par cet utilisateur
     */
    public function uploadedItems(): HasMany
    {
        return $this->hasMany(Item::class, 'uploaded_by');
    }

}
