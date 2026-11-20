<?php

namespace App\Livewire;

use App\Exceptions\ItemFileReplacementException;
use App\Models\Item;
use App\Models\PendingFile;
use App\Services\ItemFileReplacer;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;
use Livewire\Attributes\On;
use Livewire\Component; //  Number::fileSize(1024);

class UploadedFilesTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => PendingFile::completed()->byUser(auth()->id()))
            ->columns([
                /*TextColumn::make('user.name')
                        ->searchable(),*/
                TextColumn::make('original_name')
                    ->label('Nom du fichier')
                    ->sortable()
                    ->searchable(),
                /*TextColumn::make('stored_name')
                        ->searchable(),*/
                /*TextColumn::make('file_path')
                        ->searchable(),*/
                TextColumn::make('file_size')
                    ->label('Taille du fichier')
                    ->state(fn (PendingFile $record): string => Number::fileSize($record->file_size))
                    ->sortable(),
                TextColumn::make('file_type')
                    ->label('Type de fichier')
                    ->sortable()
                    ->searchable(),
                /*TextColumn::make('file_extension')
                        ->searchable(),
                    TextColumn::make('upload_status')
                        ->badge(),
                    TextColumn::make('suggested_code')
                        ->searchable(),*/
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Action::make('import_csv')
                    ->label('Importer depuis CSV')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('success')
                    ->modalHeading('Import en masse via CSV')
                    ->modalWidth('7xl')
                    ->modalContent(fn () => view('livewire.import-items-csv-modal'))
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->visible(fn () => auth()->user()->canManageItems()),

                Action::make('download_template')
                    ->label('Télécharger le modèle CSV')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('gray')
                    ->action(fn () => response()->download(public_path('import_items_template.csv')))
                    ->visible(fn () => auth()->user()->canManageItems()),
            ])
            ->recordActions([

                Action::make('convertToItem')
                    ->label('Item')
                    ->icon('heroicon-m-arrow-turn-down-right')
                    ->action(function (PendingFile $record) {
                        $this->dispatch('actionPendingFileToItem', pendingFileId: $record->id, isSub: false);
                    })
                    ->color('primary'),
                Action::make('convertToMedia')
                    ->label('Média associé')
                    ->icon('heroicon-m-arrow-turn-down-right')
                    ->action(function (PendingFile $record) {
                        $this->dispatch('actionPendingFileToItem', pendingFileId: $record->id, isSub: true);
                    })
                    ->color('primary'),
                Action::make('replaceItemFile')
                    ->label("Remplacer le fichier d'un item")
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->modalHeading('Remplacer le fichier d’un item existant')
                    ->modalDescription(
                        'Le fichier envoyé remplace l’original de l’item choisi. '
                        .'L’identifiant, la cote et les métadonnées descriptives sont conservés.'
                    )
                    ->modalSubmitActionLabel('Remplacer')
                    ->schema([
                        Select::make('item_id')
                            ->label('Item (cote)')
                            ->placeholder('Rechercher une cote…')
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => $this->itemOptions($search))
                            ->getOptionLabelUsing(fn ($value): ?string => $this->itemLabel($value)),
                        Checkbox::make('delete_old')
                            ->label("Supprimer l'ancien original")
                            ->helperText(
                                'Décoché, l’ancien fichier est archivé dans items/_remplaces. '
                                .'Cochée, il est définitivement supprimé.'
                            )
                            ->default(false),
                    ])
                    ->action(function (PendingFile $record, array $data): void {
                        $item = Item::find($data['item_id'] ?? null);

                        if (! $item instanceof Item) {
                            Notification::make()
                                ->title('Item introuvable')
                                ->body("L'item sélectionné n'existe plus.")
                                ->danger()
                                ->send();

                            return;
                        }

                        try {
                            $result = app(ItemFileReplacer::class)->replace(
                                $item,
                                $record,
                                (bool) ($data['delete_old'] ?? false),
                            );
                        } catch (ItemFileReplacementException $exception) {
                            Notification::make()
                                ->title('Remplacement impossible')
                                ->body($exception->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        $body = $result->oldPath !== null
                            ? "Ancien fichier : {$result->oldPath} → nouveau : {$result->newPath}."
                            : "Nouveau fichier : {$result->newPath}.";

                        if ($result->archivePath !== null) {
                            $body .= " Ancien original archivé dans {$result->archivePath}.";
                        }

                        if ($result->oldDeleted) {
                            $body .= ' Ancien original supprimé.';
                        }

                        Notification::make()
                            ->title("Fichier de l'item {$result->item->code} remplacé")
                            ->body($body)
                            ->success()
                            ->send();

                        foreach ($result->warnings as $warning) {
                            Notification::make()
                                ->title('Avertissement')
                                ->body($warning)
                                ->warning()
                                ->send();
                        }

                        $this->dispatch('pending-file-deleted');
                    }),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }

    #[On('item-created')]
    #[On('pending-file-deleted')]
    #[On('import-csv-completed')]
    public function refreshTable()
    {
        // Cette méthode sera appelée quand l'événement 'pending-file-deleted' est émis
        // Le tableau se rafraîchira automatiquement
    }

    /**
     * Items modifiables par l'utilisateur courant, recherchés par cote.
     *
     * @return array<int, string>
     */
    public function itemOptions(string $search): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [];
        }

        $candidates = Item::query()
            ->when(filled($search), fn (Builder $query) => $query->where('code', 'like', "%{$search}%"))
            ->orderBy('code')
            ->limit(200)
            ->get();

        return $candidates
            ->filter(fn (Item $item): bool => $user->can('update', $item))
            ->take(50)
            ->mapWithKeys(fn (Item $item): array => [$item->id => $this->formatItem($item)])
            ->all();
    }

    public function itemLabel(mixed $value): ?string
    {
        $item = Item::find($value);

        if (! $item instanceof Item) {
            return null;
        }

        $user = auth()->user();

        if ($user !== null && ! $user->can('update', $item)) {
            return null;
        }

        return $this->formatItem($item);
    }

    private function formatItem(Item $item): string
    {
        return $item->title ? "{$item->code} — {$item->title}" : $item->code;
    }

    public function render()
    {
        return view('livewire.uploaded-files-table');
    }
}
