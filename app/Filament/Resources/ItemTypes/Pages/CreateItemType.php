<?php

namespace App\Filament\Resources\ItemTypes\Pages;

use App\Filament\Resources\ItemTypes\ItemTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateItemType extends CreateRecord
{
    protected static string $resource = ItemTypeResource::class;
}
