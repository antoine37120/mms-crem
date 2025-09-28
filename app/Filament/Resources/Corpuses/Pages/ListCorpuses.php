<?php

namespace App\Filament\Resources\Corpuses\Pages;

use App\Filament\Resources\Corpuses\CorpusResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCorpuses extends ListRecords
{
    protected static string $resource = CorpusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
