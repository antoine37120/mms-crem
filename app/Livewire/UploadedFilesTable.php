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
                TextColumn::make('stored_name')
                    ->searchable(),
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
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
    public function render()
    {
        return view('livewire.uploaded-files-table');
    }
}
