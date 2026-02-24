<?php

namespace App\Filament\Resources\MediaAssocies\Pages;

use App\Filament\Resources\MediaAssocies\MediaAssocieResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class ViewMediaAssocie extends ViewRecord
{
    protected static string $resource = MediaAssocieResource::class;


    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
