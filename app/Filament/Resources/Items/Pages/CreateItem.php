<?php

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Resources\Items\ItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateItem extends CreateRecord
{
    protected static string $resource = ItemResource::class;


    protected function afterFill(): void
    {

        if (request()->has('collection_id')) {

            $itemableType = 'App\Models\Collection' ;
            $itemableId = request()->get('collection_id') ;
            $model = app($itemableType)->find($itemableId);

            // Runs before the form fields are populated with their default values.
            $this->form->fill([
                'itemable_type' => $itemableType,
                'itemable_id' => $itemableId,
                'code_prefix' => $model->code,
                'is_sub' => false,
            ]);
        }
    }

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
