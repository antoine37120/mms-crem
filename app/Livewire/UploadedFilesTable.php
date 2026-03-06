<?php

namespace App\Livewire;

use Filament\Actions\BulkActionGroup;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\Layout\Stack;
use Illuminate\Contracts\View\View;
use Filament\Tables\Table;
use App\Models\PendingFile;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Livewire\Attributes\On;
use Illuminate\Support\Number; //  Number::fileSize(1024);

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


    public function render()
    {
        return view('livewire.uploaded-files-table');
    }
}
