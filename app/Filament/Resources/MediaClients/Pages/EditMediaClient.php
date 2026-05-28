<?php

namespace App\Filament\Resources\MediaClients\Pages;

use App\Filament\Resources\MediaClients\MediaClientResource;
use App\Models\MediaClient;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditMediaClient extends EditRecord
{
    protected static string $resource = MediaClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerate')
                ->label('Régénérer le secret')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function (MediaClient $record) {
                    $newSecret = Str::random(64);

                    $record->update([
                        'encrypted_secret_previous' => $record->encrypted_secret,
                        'previous_expires_at' => now()->addHours(24),
                        'encrypted_secret' => \encrypt_secret($newSecret),
                    ]);

                    Notification::make()
                        ->title('Nouveau secret généré')
                        ->body("Copiez-le maintenant : {$newSecret}. L'ancien secret reste valable 24h.")
                        ->danger()
                        ->persistent()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
