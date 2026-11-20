<?php

namespace App\Services;

use App\Models\Item;

/**
 * Résultat immuable d'un remplacement de fichier d'item.
 */
final readonly class ItemFileReplacementResult
{
    /**
     * @param  list<string>  $warnings  Avertissements non bloquants (ex. md5 déjà présent sur un autre item)
     */
    public function __construct(
        public Item $item,
        public ?string $oldPath,
        public string $newPath,
        public ?string $archivePath,
        public bool $oldDeleted,
        public array $warnings = [],
    ) {}
}
