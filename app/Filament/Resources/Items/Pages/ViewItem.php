<?php

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Resources\Items\ItemResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

class ViewItem extends ViewRecord
{
    protected static string $resource = ItemResource::class;


    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Télécharger l\'original')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn ($record) => Storage::disk('original_medias')->download($record->file_path, $record->code . '.' . $record->file_extension))
                ->visible(fn ($record) => filled($record->file_path) && Storage::disk('original_medias')->exists($record->file_path)),
            Action::make('viewInHierarchy')
                ->label('Hiérarchie')
                ->icon('heroicon-o-folder')
                ->color('info')
                ->url(fn ($record) => route('filament.mms-admin.pages.hierarchy-explorer', [
                    'focus' => 'item',
                    'id' => $record->id
                ]))
                ->openUrlInNewTab(),
            EditAction::make(),
        ];
    }
}
