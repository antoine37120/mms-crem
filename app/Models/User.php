<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use OwenIt\Auditing\Contracts\Auditable;

class User extends Authenticatable implements Auditable, FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    use \OwenIt\Auditing\Auditable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
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
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => UserRole::class,
        'admin_access' => 'boolean',
    ];

    /**
     * Attributes to exclude from the Audit.
     *
     * @var array
     */
    protected $auditExclude = [
        /* 'code_prefix', */
        'password',
    ];

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

    /**
     * Vérifie si l'utilisateur a un ou plusieurs rôles
     */
    public function hasRole(string|array|UserRole $roles): bool
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }

        if ($roles instanceof UserRole) {
            return $this->role === $roles;
        }

        $userRole = $this->role?->value ?? UserRole::CHERCHEUR->value;

        // Convertir les enums en string si nécessaire
        $rolesToCheck = array_map(function ($role) {
            return $role instanceof UserRole ? $role->value : $role;
        }, (array) $roles);

        return in_array($userRole, $rolesToCheck);
    }

    /**
     * Vérifier si l'utilisateur est administrateur
     */
    public function isAdmin(): bool
    {
        return $this->role?->isAdmin() ?? false;
    }

    /**
     * Vérifier si l'utilisateur est super administrateur
     */
    public function isSuperAdmin(): bool
    {
        return $this->role?->isSuperAdmin() ?? false;
    }

    /**
     * Vérifier si l'utilisateur peut gérer les items
     */
    public function canManageItems(): bool
    {
        return $this->hasRole([UserRole::DOCUMENTALISTE, UserRole::ADMINISTRATEUR]);
    }

    /**
     * Vérifier si l'utilisateur peut accéder à l'admin
     */
    public function canAccessAdmin(): bool
    {
        return $this->admin_access && $this->isAdmin();
    }

    public function scopedFonds()
    {
        return $this->morphedByMany(Fond::class, 'scopeable', 'user_documentary_scopes');
    }

    public function scopedCorpuses()
    {
        return $this->morphedByMany(Corpus::class, 'scopeable', 'user_documentary_scopes');
    }

    public function scopedCollections()
    {
        return $this->morphedByMany(Collection::class, 'scopeable', 'user_documentary_scopes');
    }

    public function hasAccessToModel($model): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($this->hasRole(UserRole::DOCUMENTALISTE)) {
            if ($model instanceof Fond) {
                return $this->scopedFonds()->where('fonds.id', $model->id)->exists();
            }

            if ($model instanceof Corpus) {
                $hasDirect = $this->scopedCorpuses()->where('corpuses.id', $model->id)->exists();
                if ($hasDirect) {
                    return true;
                }

                // Un corpus appartient à plusieurs fonds
                $fondsIds = $model->fonds()->pluck('fonds.id');
                if ($fondsIds->isNotEmpty()) {
                    return $this->scopedFonds()->whereIn('fonds.id', $fondsIds)->exists();
                }

                return false;
            }

            if ($model instanceof Collection) {
                $hasDirect = $this->scopedCollections()->where('collections.id', $model->id)->exists();
                if ($hasDirect) {
                    return true;
                }

                // Une collection appartient à plusieurs corpus
                $corpusesIds = $model->corpuses()->pluck('corpuses.id');
                if ($corpusesIds->isNotEmpty()) {
                    $hasCorpus = $this->scopedCorpuses()->whereIn('corpuses.id', $corpusesIds)->exists();
                    if ($hasCorpus) {
                        return true;
                    }

                    // On vérifie les fonds de ces corpus
                    $fondsIds = \App\Models\Corpus::whereIn('id', $corpusesIds)
                        ->with('fonds')
                        ->get()
                        ->flatMap->fonds
                        ->pluck('id')
                        ->unique();

                    if ($fondsIds->isNotEmpty()) {
                        return $this->scopedFonds()->whereIn('fonds.id', $fondsIds)->exists();
                    }
                }

                return false;
            }

            if ($model instanceof Item) {
                return $this->hasAccessToItemable($model->itemable_type, $model->itemable_id);
            }
        }

        if ($this->hasRole(UserRole::CHERCHEUR)) {
            return $model->created_by === $this->id;
        }

        return false;
    }

    public function hasAccessToItemable(?string $type, ?int $id): bool
    {
        if (! $type || ! $id) {
            return false;
        }

        $modelClass = $type;
        if (! class_exists($modelClass)) {
            return false;
        }

        $model = $modelClass::find($id);
        if (! $model) {
            return false;
        }

        if ($model instanceof Item) {
            return $this->hasAccessToItemable($model->itemable_type, $model->itemable_id);
        }

        return $this->hasAccessToModel($model);
    }

    /**
     * Get the audits where this user is the actor.
     */
    public function auditsAsUser(): HasMany
    {
        return $this->hasMany(\OwenIt\Auditing\Models\Audit::class, 'user_id')
            ->where('user_type', $this->getMorphClass());
    }
}
