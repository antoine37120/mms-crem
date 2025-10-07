<?php

namespace App\Filament\Resources\ItemTypes\Pages;

use App\Filament\Resources\ItemTypes\ItemTypeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Tapp\FilamentAuditing\RelationManagers\AuditsRelationManager;

class ViewItemType extends ViewRecord
{
    protected static string $resource = ItemTypeResource::class;

    public function getRelationManagers(): array
    {
        return [
            AuditsRelationManager::class,
        ];
    }
    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
