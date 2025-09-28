<?php

namespace App\Filament\Resources\Fonds\Pages;

use App\Filament\Resources\Fonds\FondResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFonds extends ListRecords
{
    protected static string $resource = FondResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
