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
use Illuminate\Contracts\View\View;
use Filament\Tables\Table;
use App\Models\PendingFile;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Livewire\Attributes\On;
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
                    ->searchable(),
                /*TextColumn::make('stored_name')
                    ->searchable(),*/
                /*TextColumn::make('file_path')
                    ->searchable(),*/
                TextColumn::make('file_size')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('file_type')
                    ->searchable(),
                /*TextColumn::make('file_extension')
                    ->searchable(),
                TextColumn::make('upload_status')
                    ->badge(),*/
                TextColumn::make('suggested_code')
                    ->searchable(),
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
                //
            ])
            ->recordActions([

                Action::make('convertToItem')
                    ->label('Convertir en Item')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function (PendingFile $record) {
                        $this->dispatch('actionPendingFileToItem', pendingFileId: $record->id);
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

    #[On('pending-file-deleted')]
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
