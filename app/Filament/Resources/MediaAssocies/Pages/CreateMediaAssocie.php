<?php

namespace App\Filament\Resources\MediaAssocies\Pages;

use App\Filament\Resources\MediaAssocies\MediaAssocieResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMediaAssocie extends CreateRecord
{
    protected static string $resource = MediaAssocieResource::class;

    protected function afterFill(): void
    {

        if (request()->has('collection_id')) {

            $itemableType = 'App\Models\Collection';
            $itemableId = request()->get('collection_id');
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
}
