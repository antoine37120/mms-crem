<?php

namespace App\Filament\Resources\ScannedFileResource\Pages;

use App\Filament\Resources\ScannedFileResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListScannedFiles extends ListRecords
{
    protected static string $resource = ScannedFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('process_pending')
                ->label('Traiter les items associés')
                ->icon('heroicon-o-play')
                ->color('success')
                ->action(function () {
                    Artisan::queue('items:process-pending-media');

                    Notification::make()
                        ->title('Traitement lancé en arrière-plan. Les jobs de diffusion et waveform sont en cours de dispatch.')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('force_reprocess')
                ->label('Forcer le retraitement')
                ->icon('heroicon-o-arrow-path')
                ->color('danger')
                ->requiresConfirmation()
                ->action(function () {
                    Artisan::queue('items:process-pending-media', ['--force' => true]);

                    Notification::make()
                        ->title('Retraitement forcé lancé. Tous les items seront ré-encodés.')
                        ->danger()
                        ->send();
                }),
        ];
    }
}
