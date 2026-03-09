<?php

namespace App\Filament\Resources\DocumentationPageResource\Pages;

use App\Filament\Resources\DocumentationPageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewDocumentationPage extends ViewRecord
{
    protected static string $resource = DocumentationPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
