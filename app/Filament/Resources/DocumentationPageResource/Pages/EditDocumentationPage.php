<?php

namespace App\Filament\Resources\DocumentationPageResource\Pages;

use App\Filament\Resources\DocumentationPageResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDocumentationPage extends EditRecord
{
    protected static string $resource = DocumentationPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
