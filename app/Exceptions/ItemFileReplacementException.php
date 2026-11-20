<?php

namespace App\Exceptions;

use App\Models\ItemType;
use RuntimeException;

/**
 * Erreur métier d'un remplacement de fichier d'item.
 *
 * Toutes les erreurs sont levées avant ou pendant l'opération, jamais après
 * le commit : l'item reste alors inchangé (ou compensé).
 */
class ItemFileReplacementException extends RuntimeException
{
    public static function pendingFileNotCompleted(string $status): self
    {
        return new self("Le fichier envoyé n'est pas complet (statut : {$status}).");
    }

    public static function pendingFileMissing(): self
    {
        return new self('Le fichier envoyé est introuvable sur le disque de dépôt.');
    }

    public static function pendingFileNotOwned(): self
    {
        return new self('Vous ne pouvez pas utiliser un fichier envoyé par un autre utilisateur.');
    }

    public static function pendingFileSizeMismatch(int $expected, int $actual): self
    {
        return new self("La taille du fichier envoyé est incohérente ({$actual} octets reçus, {$expected} annoncés).");
    }

    public static function pendingFileChecksumMismatch(): self
    {
        return new self("L'empreinte md5 du fichier envoyé est incohérente : le fichier est probablement incomplet.");
    }

    public static function invalidExtension(string $extension): self
    {
        return new self("L'extension « {$extension} » n'est pas valide (1 à 10 caractères alphanumériques attendus).");
    }

    public static function extensionNotAllowed(string $extension, ?ItemType $itemType = null): self
    {
        $type = $itemType?->name ?? 'ce type de média';

        return new self("L'extension « {$extension} » n'est pas autorisée pour {$type}.");
    }

    public static function missingCode(): self
    {
        return new self("L'item n'a pas de cote : impossible de calculer le chemin du fichier.");
    }

    public static function invalidCode(string $code): self
    {
        return new self("La cote « {$code} » contient des caractères interdits (/ , \\ ou ..).");
    }

    public static function inconsistentCode(string $code, string $prefix, ?string $suffix): self
    {
        $computed = $suffix ? $prefix.'_'.$suffix : $prefix;

        return new self(
            "La cote de l'item (« {$code} ») est incohérente avec son préfixe/suffixe (« {$computed} ») : "
            .'le fichier ne peut pas être nommé sans changer la cote.'
        );
    }

    public static function fileOperationFailed(string $operation): self
    {
        return new self("L'opération sur le fichier a échoué : {$operation}.");
    }
}
