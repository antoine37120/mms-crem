<?php

namespace App\Filament\Resources\Corpuses\Pages;

use App\Filament\Resources\Corpuses\CorpusResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCorpus extends CreateRecord
{
    protected static string $resource = CorpusResource::class;

    protected function afterFill(): void
    {
        if (request()->has('fond_id')) {
            // Runs before the form fields are populated with their default values.
            $this->form->fill([
                'fond_id' => request()->get('fond_id'),
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}
