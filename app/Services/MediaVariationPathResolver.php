<?php

namespace App\Services;

use App\Models\Item;

class MediaVariationPathResolver
{
    /**
     * Dossier de base de l'item, reproduit depuis l'original.
     * Ex: $item->file_path = "items/2011/05/25/CODE.wav"
     *     → "items/2011/05/25/CODE"
     */
    public function itemDir(Item $item): string
    {
        $base = dirname($item->file_path);
        // Protection contre les file_path sans dossier parent (en mode saveQuietly)
        if ($base === '' || $base === '.') {
            $base = 'items';
        }

        return $base.'/'.$item->code;
    }

    /**
     * Dossier complet pour un type de variation.
     * Ex: → "items/2011/05/25/CODE/diffusion"
     */
    public function variationDir(Item $item, string $type): string
    {
        return $this->itemDir($item).'/'.$type;
    }

    /**
     * Chemin complet d'un fichier de variation.
     * Ex: → "items/2011/05/25/CODE/diffusion/CODE.m3u8"
     */
    public function variationPath(Item $item, string $type, string $filename): string
    {
        return $this->variationDir($item, $type).'/'.$filename;
    }

    /**
     * Dossier parent d'un type de variation (pour les segments HLS).
     * Ex: → "items/2011/05/25/CODE/diffusion"
     */
    public function segmentDir(Item $item): string
    {
        return $this->variationDir($item, 'diffusion');
    }
}
