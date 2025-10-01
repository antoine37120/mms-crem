<?php

namespace App\Filament\Resources\Collections\Pages;

use App\Filament\Resources\Collections\CollectionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCollection extends CreateRecord
{
    protected static string $resource = CollectionResource::class;

    protected function afterFill(): void
    {
        if (request()->has('corpus_id')) {
            // Runs before the form fields are populated with their default values.
            $this->form->fill([
                'corpus_id' => request()->get('corpus_id'),
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}
