<?php

namespace App\Filament\Resources\ScannedFileResource\Pages;

use App\Filament\Resources\ScannedFileResource;
use Filament\Resources\Pages\ListRecords;

class ListScannedFiles extends ListRecords
{
    protected static string $resource = ScannedFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Maybe an action to trigger the scan command?
            // The user didn't explicitly ask for it in the Resource UI, but mentioned "Cmd media:scan".
            // I'll keep it simple for now as requested.
        ];
    }
}
