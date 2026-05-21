<?php

namespace App\Livewire;

use App\Models\Collection;
use App\Models\Corpus;
use App\Models\Fond;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\PendingFile;
use Carbon\Carbon;
use Filament\Actions\Action as TableAction;
use Filament\Actions\BulkAction;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Http\File;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class ImportItemsCsv extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    public ?array $data = [];

    public array $parsedCsvRows = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('csv_file')
                    ->label('Fichier CSV d\'import')
                    ->acceptedFileTypes(['text/csv', 'application/csv', 'text/plain'])
                    ->storeFiles(false)
                    ->live()
                    ->afterStateUpdated(function ($state) {
                        $this->parseCsv($state);
                    }),
            ])
            ->statePath('data');
    }

    protected function parseCsv($file)
    {
        $this->parsedCsvRows = [];
        if (! $file) {
            return;
        }

        // We use the file path before clearing the state
        $path = is_string($file) ? $file : $file->getRealPath();

        // Clear the file from the component state immediately to prevent
        // LaraDumps or Livewire serialization issues with large file objects.
        $this->data['csv_file'] = null;

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return;
        }

        // Lire l'en-tête (on force le BOM check si besoin)
        $header = fgetcsv($handle, 1000, ',');

        // Remove BOM from the first header value if exists
        if ($header && isset($header[0])) {
            $header[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $header[0]);
        }

        $rowIndex = 1;

        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            $rowIndex++;

            // Ignorer les lignes vides
            if (empty(array_filter($row))) {
                continue;
            }

            // Assurer que la ligne a le même nombre d'éléments que l'en-tête
            if (count($header) !== count($row)) {
                $row = array_pad($row, count($header), null);
            }

            $rowData = array_combine($header, $row);

            // Clean keys just in case
            $cleanedRowData = [];
            foreach ($rowData as $k => $v) {
                $cleanedRowData[trim($k)] = trim($v);
            }
            $rowData = $cleanedRowData;

            $validatedData = $this->validateCsvRow($rowIndex, $rowData);
            $this->parsedCsvRows[$validatedData['__id']] = $validatedData;
        }

        fclose($handle);

        // Final clean up of the temporary file if it was a TemporaryUploadedFile
        if (! is_string($file) && method_exists($file, 'delete')) {
            $file->delete();
        }
    }

    protected function validateCsvRow(int $rowIndex, array $row): array
    {
        $fileName = $row['file_name'] ?? null;
        $parentCode = $row['parent_code'] ?? null;
        $codeSuffix = $row['code_suffix'] ?? null;
        $title = $row['title'] ?? null;
        $isSub = isset($row['is_sub']) ? (bool) $row['is_sub'] : false;
        $itemTypeSuffix = $row['item_type_suffix'] ?? null;
        $languageCode = $row['language_code'] ?? null;

        $errors = [];
        $status = 'ready';

        // 1. Check PendingFile
        $pendingFile = null;
        if (empty($fileName)) {
            $errors[] = 'file_name est requis.';
        } else {
            $pendingFile = PendingFile::where('user_id', auth()->id())
                ->where('original_name', $fileName)
                ->where('upload_status', PendingFile::STATUS_COMPLETED)
                ->first();

            if (! $pendingFile) {
                $errors[] = "Fichier '{$fileName}' introuvable ou upload incomplet.";
            }
        }

        // 2. Check Parent Entity
        $parentModel = null;
        if (empty($parentCode)) {
            $errors[] = 'parent_code est requis.';
        } else {
            // Find parent entity
            $parentModel = Fond::where('code', $parentCode)->first()
                ?? Corpus::where('code', $parentCode)->first()
                ?? Collection::where('code', $parentCode)->first()
                ?? Item::where('code', $parentCode)->first();

            if (! $parentModel) {
                $errors[] = "parent_code '{$parentCode}' introuvable.";
            } else {
                if (! auth()->user()->hasAccessToModel($parentModel)) {
                    $errors[] = "Vous n'avez pas l'autorisation d'importer dans ce parent ({$parentCode}).";
                }
            }
        }

        // 3. Check ItemType
        $itemType = null;
        if ($isSub && empty($itemTypeSuffix)) {
            $errors[] = 'item_type_suffix est requis pour un média associé (is_sub=true).';
        } elseif (! empty($itemTypeSuffix)) {
            $itemType = ItemType::where('suffix', $itemTypeSuffix)->first();
            if (! $itemType) {
                $errors[] = "Suffixe de type '{$itemTypeSuffix}' invalide.";
            }
        }

        // 4. Determine Code Suffix and validate Language constraints
        $suffixParts = [];

        if ($itemType) {
            $suffixParts[] = $itemType->suffix;

            if ($itemType->requires_language) {
                if (empty($languageCode)) {
                    $errors[] = "language_code est requis pour le type '{$itemType->name}'.";
                } else {
                    $suffixParts[] = $languageCode;
                }
            } elseif (! empty($languageCode)) {
                // Si une langue est fournie même si non requise par le type, on l'ajoute ?
                // Selon vos exemples : parent_code _ item_type_suffix _ language_code _ code_suffix
                $suffixParts[] = $languageCode;
            }
        }

        if (! empty($codeSuffix)) {
            $suffixParts[] = $codeSuffix;
        }

        $calculatedCodeSuffix = implode('_', $suffixParts);

        // Validation : Un item doit toujours avoir un suffixe (Type, Langue ou Manuel)
        // pour ne pas avoir la même cote que son parent (Collection, Fond, Item parent, etc.).
        if (empty($calculatedCodeSuffix)) {
            $errors[] = "L'Item doit avoir au moins un type ou un suffixe pour le différencier de son parent ({$parentCode}).";
        }

        // 5. Code Uniqueness
        $finalCode = $parentCode;
        if (! empty($calculatedCodeSuffix)) {
            $finalCode .= '_'.$calculatedCodeSuffix;
        }

        if ($pendingFile) {
            $existingItemQuery = Item::where('code', $parentCode);
            if (! empty($calculatedCodeSuffix)) {
                $existingItemQuery->where('code', $parentCode.'_'.$calculatedCodeSuffix);
            }
            $existingItemQuery->where('file_extension', $pendingFile->file_extension);

            if ($existingItemQuery->exists()) {
                $errors[] = "L'Item avec la cote '{$finalCode}' et l'extension '{$pendingFile->file_extension}' existe déjà.";
            }
        }

        // 6. Check Extension matches ItemType constraints
        if ($itemType && $pendingFile) {
            if (! $itemType->isExtensionAllowed($pendingFile->file_extension)) {
                $errors[] = "L'extension '{$pendingFile->file_extension}' n'est pas autorisée pour le type '{$itemType->name}'.";
            }
        }

        if (count($errors) > 0) {
            $status = 'error';
        }

        // Generate a unique ID for the row (used as record key in Custom Data table)
        $rowId = "row_{$rowIndex}";

        // Generate a human-readable label for the parent
        $parentLabel = 'N/A';
        if ($parentModel) {
            $typeLabel = match (get_class($parentModel)) {
                Fond::class => 'Fond',
                Corpus::class => 'Corpus',
                Collection::class => 'Collection',
                Item::class => 'Item',
                default => basename(str_replace('\\', '/', get_class($parentModel))),
            };
            $parentLabel = "{$typeLabel} ({$parentCode})";
        }

        return [
            '__id' => $rowId,
            'file_name' => $fileName,
            'parent_label' => $parentLabel,
            'parent_code' => $parentCode,
            'final_code' => $finalCode,
            'code_suffix' => $calculatedCodeSuffix,
            'title' => $title,
            'is_sub' => $isSub,
            'item_type_suffix' => $itemTypeSuffix,
            'language_code' => $languageCode,
            'status' => $status,
            'errors' => implode(', ', $errors),

            // Store resolved references to avoid re-querying during action
            '__pending_file_id' => $pendingFile ? $pendingFile->id : null,
            '__parent_type' => $parentModel ? get_class($parentModel) : null,
            '__parent_id' => $parentModel ? $parentModel->id : null,
            '__item_type_id' => $itemType ? $itemType->id : null,
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn () => $this->parsedCsvRows)
            ->resolveSelectedRecordsUsing(function (array $keys, bool $isTrackingDeselectedKeys, array $deselectedKeys): SupportCollection {
                $baseRecords = $isTrackingDeselectedKeys
                    ? Arr::except($this->parsedCsvRows, $deselectedKeys)
                    : Arr::only($this->parsedCsvRows, $keys);

                return SupportCollection::make($baseRecords);
            })
            ->checkIfRecordIsSelectableUsing(fn ($record): bool => $record['status'] === 'ready')
            ->headerActions([
                TableAction::make('import_all_ready')
                    ->label('Importer tout ce qui est prêt')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->hidden(fn () => ! SupportCollection::make($this->parsedCsvRows)->contains('status', 'ready'))
                    ->action(function () {
                        $readyRecords = SupportCollection::make($this->parsedCsvRows)
                            ->filter(fn ($row) => $row['status'] === 'ready');
                        $this->importRecords($readyRecords);
                    }),
            ])
            ->columns([
                IconColumn::make('status')
                    ->label('État')
                    ->icon(fn (string $state): string => match ($state) {
                        'ready' => 'heroicon-o-check-circle',
                        'error' => 'heroicon-o-x-circle',
                        'imported' => 'heroicon-o-check-badge',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'ready' => 'success',
                        'error' => 'danger',
                        'imported' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('file_name')
                    ->label('Fichier'),
                TextColumn::make('parent_label')
                    ->label('Parent détecté')
                    ->color(fn ($record) => $record['status'] === 'error' && str_contains($record['errors'], 'parent_code') ? 'danger' : 'gray'),
                TextColumn::make('final_code')
                    ->label('Cote prévisionnelle'),
                TextColumn::make('title')
                    ->label('Titre')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_sub')
                    ->label('Média Asso.')
                    ->boolean(),
                TextColumn::make('item_type_suffix')
                    ->label('Type (Suf.)'),
                TextColumn::make('errors')
                    ->label('Erreurs')
                    ->color('danger')
                    ->wrap(),
            ])
            ->bulkActions([
                BulkAction::make('import_selected')
                    ->label("Lancer l'import")
                    ->icon('heroicon-o-arrow-up-tray')
                    ->requiresConfirmation()
                    ->modalHeading('Importer les lignes sélectionnées')
                    ->modalDescription('Les items seront créés et les fichiers déplacés. Les lignes en erreur seront ignorées.')
                    ->action(function (SupportCollection $records) {
                        $this->importRecords($records);
                    }),
            ])
            ->defaultPaginationPageOption(10);
    }

    protected function importRecords(SupportCollection $records)
    {
        $successCount = 0;
        $errorCount = 0;

        foreach ($records as $record) {
            if ($record['status'] !== 'ready') {
                continue; // Skip errors and already imported
            }

            try {
                $pendingFile = PendingFile::find($record['__pending_file_id']);
                if (! $pendingFile) {
                    throw new \Exception('Fichier introuvable.');
                }

                $data = [
                    'itemable_type' => $record['__parent_type'],
                    'itemable_id' => $record['__parent_id'],
                    'item_type_id' => $record['__item_type_id'],
                    'is_sub' => $record['is_sub'],
                    'code_prefix' => $record['parent_code'],
                    'code_suffix' => $record['code_suffix'],
                    'code' => $record['final_code'],
                    'title' => $record['title'],
                    'language_code' => $record['language_code'],
                    'file_name' => $pendingFile->original_name,
                    'file_size' => $pendingFile->file_size,
                    'file_type' => $pendingFile->file_type,
                    'file_extension' => $pendingFile->file_extension,
                    'upload_date' => $pendingFile->created_at,
                    'uploaded_by' => $pendingFile->user_id,
                    'created_by' => auth()->id(),
                ];

                // Fichier copy logic (similar to UploadedFileToItem)
                $createdAt = Carbon::parse($pendingFile->created_at);
                $datePath = ''; // Apparemment géré via le boot() de Item si c'est laissé vide, ou bien on le met à la racine.

                $fileName = $data['code'].'.'.$data['file_extension'];
                $newFilePath = $fileName;

                $currentFilePath = $pendingFile->file_path;

                if (! Storage::disk('local')->exists($currentFilePath)) {
                    throw new \Exception("Le fichier source n'existe pas : ".$currentFilePath);
                }

                Storage::disk('original_medias')->makeDirectory($datePath);
                $old_file_path = Storage::disk('local')->path($currentFilePath);

                Storage::disk('original_medias')->putFileAs($datePath, new File($old_file_path), $fileName);

                $data['file_path'] = $newFilePath;

                $item = Item::create($data);

                // Delete temporary file
                Storage::disk('local')->delete($currentFilePath);
                $pendingFile->delete();

                // Remove from table on success
                unset($this->parsedCsvRows[$record['__id']]);
                $successCount++;

            } catch (\Exception $e) {
                \Log::error('Erreur import CSV', ['error' => $e->getMessage(), 'record' => $record]);
                $this->updateRowStatus($record['__id'], 'error', $e->getMessage());
                $errorCount++;
            }
        }

        Notification::make()
            ->title('Import terminé')
            ->body("{$successCount} items créés avec succès. {$errorCount} erreurs.")
            ->status($errorCount > 0 ? 'warning' : 'success')
            ->send();

        $this->dispatch('import-csv-completed');
    }

    protected function updateRowStatus(string $rowId, string $status, string $message = ''): void
    {
        if (isset($this->parsedCsvRows[$rowId])) {
            $this->parsedCsvRows[$rowId]['status'] = $status;
            if ($status === 'error') {
                $this->parsedCsvRows[$rowId]['errors'] = $message;
            } else {
                $this->parsedCsvRows[$rowId]['errors'] = '';
            }
        }
    }

    public function render()
    {
        return view('livewire.import-items-csv');
    }
}
