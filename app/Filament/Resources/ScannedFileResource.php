<?php

namespace App\Filament\Resources;

use App\Enums\ItemProcessingType;
use App\Enums\ScannedFileStatus;
use App\Filament\Resources\ScannedFileResource\Pages;
use App\Jobs\RunMediaScanJob;
use App\Models\ScannedFile;
use App\Services\Admin\ScannedFileAdminService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ScannedFileResource extends Resource
{
    protected static ?string $model = ScannedFile::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-eye';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?string $navigationLabel = 'Fichiers Scannés';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Read-only generally, maybe display info
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('file_name')
                    ->label('Nom du fichier')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('file_path')
                    ->label('Chemin')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('size')
                    ->label('Taille')
                    ->formatStateUsing(fn ($state) => number_format($state / 1024 / 1024, 2).' MB')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ScannedFileStatus $state): string => match ($state) {
                        ScannedFileStatus::ORPHAN => 'danger',
                        ScannedFileStatus::ASSOCIATED => 'success',
                    })
                    ->sortable(),
                TextColumn::make('item.processingStates')
                    ->label('Statut diffusion')
                    ->formatStateUsing(function ($record) {
                        $state = $record->item?->processingStates()
                            ->where('process_type', ItemProcessingType::DIFFUSION)
                            ->first();

                        return $state?->status->value ?? '—';
                    })
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'completed' => 'success',
                        'processing' => 'warning',
                        'failed' => 'danger',
                        'pending' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('item.code')
                    ->label('Item lié')
                    ->searchable()
                    ->url(fn ($record) => $record->item_id ? route('filament.mms-admin.resources.items.view', $record->item_id) : null)
                    ->color('primary'),
                TextColumn::make('last_scanned_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ScannedFileStatus::class),
            ])
            ->recordActions([
                Action::make('rescan')
                    ->label('Rescanner')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (ScannedFile $record, ScannedFileAdminService $service) {
                        if ($service->rescan($record)) {
                            Notification::make()
                                ->title('Fichier rescanné')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Fichier introuvable')
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('try_match')
                    ->label('Associer')
                    ->icon('heroicon-o-link')
                    ->color('warning')
                    ->visible(fn (ScannedFile $record) => $record->status === ScannedFileStatus::ORPHAN)
                    ->action(function (ScannedFile $record, ScannedFileAdminService $service) {
                        $matched = $service->tryMatch($record);

                        if ($matched) {
                            Notification::make()
                                ->title('Item associé avec succès')
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title('Aucun item correspondant trouvé')
                                ->warning()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                Action::make('run_scan')
                    ->label('Lancer un scan complet')
                    ->icon('heroicon-o-magnifying-glass')
                    ->color('primary')
                    ->action(function () {
                        RunMediaScanJob::dispatch(user: auth()->user());

                        Notification::make()
                            ->title('Scan lancé en arrière-plan. Vous recevrez une notification à la fin.')
                            ->info()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListScannedFiles::route('/'),
        ];
    }
}
