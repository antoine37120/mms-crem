<?php

namespace App\Filament\Resources\DocumentationPageResource\Pages;

use App\Filament\Resources\DocumentationPageResource;
use Openplain\FilamentTreeView\Resources\Pages\TreePage;
use Filament\Actions;

class ListDocumentationPages extends TreePage
{
    protected static string $resource = DocumentationPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
