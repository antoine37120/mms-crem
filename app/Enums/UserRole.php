<?php

namespace App\Enums;

enum UserRole: string
{
    case CHERCHEUR = 'chercheur';
    case DOCUMENTALISTE = 'documentaliste';
    case ADMINISTRATEUR = 'administrateur';

    /**
     * Obtenir tous les rôles sous forme de tableau
     */
    public static function toArray(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Obtenir les options pour les formulaires
     */
    public static function options(): array
    {
        return [
            self::CHERCHEUR->value => 'Chercheur',
            self::DOCUMENTALISTE->value => 'Documentaliste',
            self::ADMINISTRATEUR->value => 'Administrateur',
        ];
    }

    /**
     * Obtenir le label lisible
     */
    public function label(): string
    {
        return match($this) {
            self::CHERCHEUR => 'Chercheur',
            self::DOCUMENTALISTE => 'Documentaliste',
            self::ADMINISTRATEUR => 'Administrateur',
        };
    }

    /**
     * Vérifier si c'est un rôle administratif
     */
    public function isAdmin(): bool
    {
        return in_array($this, [self::DOCUMENTALISTE, self::ADMINISTRATEUR]);
    }

    /**
     * Vérifier si c'est le rôle le plus élevé
     */
    public function isSuperAdmin(): bool
    {
        return $this === self::ADMINISTRATEUR;
    }
}
