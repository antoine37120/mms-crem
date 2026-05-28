<?php

namespace App\Filament\Resources\MediaClients\Pages;

use App\Filament\Resources\MediaClients\MediaClientResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMediaClients extends ListRecords
{
    protected static string $resource = MediaClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
