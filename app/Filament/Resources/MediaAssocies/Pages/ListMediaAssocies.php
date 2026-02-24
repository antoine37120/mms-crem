<?php

namespace App\Filament\Resources\MediaAssocies\Pages;

use App\Filament\Resources\MediaAssocies\MediaAssocieResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMediaAssocies extends ListRecords
{
    protected static string $resource = MediaAssocieResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
