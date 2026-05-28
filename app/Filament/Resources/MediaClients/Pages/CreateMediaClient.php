<?php

namespace App\Filament\Resources\MediaClients\Pages;

use App\Filament\Resources\MediaClients\MediaClientResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMediaClient extends CreateRecord
{
    protected static string $resource = MediaClientResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['secret'])) {
            $data['encrypted_secret'] = \encrypt_secret($data['secret']);
            unset($data['secret']);
        }

        return $data;
    }
}
