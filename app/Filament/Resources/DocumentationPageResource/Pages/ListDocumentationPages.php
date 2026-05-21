<?php

namespace App\Filament\Resources\DocumentationPageResource\Pages;

use App\Filament\Resources\DocumentationPageResource;
use Filament\Actions;
use Openplain\FilamentTreeView\Resources\Pages\TreePage;

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
