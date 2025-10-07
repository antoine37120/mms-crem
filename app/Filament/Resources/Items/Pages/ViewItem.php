<?php

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Resources\Items\ItemResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class ViewItem extends ViewRecord
{
    protected static string $resource = ItemResource::class;


    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
