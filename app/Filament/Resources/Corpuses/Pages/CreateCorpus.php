<?php

namespace App\Filament\Resources\Corpuses\Pages;

use App\Filament\Resources\Corpuses\CorpusResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCorpus extends CreateRecord
{
    protected static string $resource = CorpusResource::class;



    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }

    protected function getCancelFormAction(): \Filament\Actions\Action
    {
        return \Filament\Actions\Action::make('cancel')
            ->label(__('filament-panels::resources/pages/create-record.form.actions.cancel.label'))
            ->url($this->previousUrl ?? $this->getResource()::getUrl('index'))
            ->color('gray');
    }
}
