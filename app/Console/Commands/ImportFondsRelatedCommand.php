<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\Corpus;
use App\Models\Fond;
use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportFondsRelatedCommand extends Command
{
    protected $signature = 'import:fonds-related
                            {--dry-run : Exécution en mode test sans sauvegarder}
                            {--limit= : Limiter le nombre d\'imports}
                            {--source= : Source des données (fonds ou corpus)}';

    protected $description = 'Importer les items depuis la table media_fonds_related';

    protected int $imported = 0;

    protected int $skipped = 0;

    protected int $errors = 0;

    protected array $errorMessages = [];

    protected string $sourceTable;

    protected string $relatedTable;

    protected string $parentModel;

    protected string $parentField;

    public function handle(): int
    {
        // Déterminer la source des données
        $this->determineSource();
        $this->info('=== Import des Items depuis media_fonds_related ===');
        $this->newLine();

        // Récupérer les données de media_fonds_related
        $query = DB::table($this->relatedTable)
            ->select([
                'id',
                'title',
                'filename',
                'resource_id',
                'description',
                'mime_type',
                'date',
                'credits',
                'public_access',
            ]);

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $relatedItems = $query->get();

        if ($relatedItems->isEmpty()) {
            $this->warn("Aucun élément trouvé dans {$this->relatedTable}");

            return Command::SUCCESS;
        }

        $this->info("Nombre d'éléments à traiter : {$relatedItems->count()}");
        $this->newLine();

        // Créer la barre de progression
        $progressBar = $this->output->createProgressBar($relatedItems->count());
        $progressBar->start();

        foreach ($relatedItems as $relatedItem) {
            try {
                $this->processRelatedItem($relatedItem);
            } catch (\Exception $e) {
                $this->errors++;
                $this->errorMessages[] = sprintf(
                    'ID %d (%s): %s',
                    $relatedItem->id,
                    $relatedItem->filename,
                    $e->getMessage()
                );
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Afficher le résumé
        $this->displaySummary();

        return Command::SUCCESS;
    }

    protected function determineSource(): void
    {
        $source = strtolower($this->option('source') ?? '');

        if ($source === 'collections') {
            $this->sourceTable = 'media_collections';
            $this->relatedTable = 'media_collection_related';
            $this->parentModel = Collection::class;
            $this->parentField = 'collection';
        } elseif ($source === 'corpus') {
            $this->sourceTable = 'media_corpus';
            $this->relatedTable = 'media_corpus_related';
            $this->parentModel = Corpus::class;
            $this->parentField = 'corpus';
        } elseif ($source === 'items') {
            $this->sourceTable = 'media_items';
            $this->relatedTable = 'media_item_related';
            $this->parentModel = Item::class;
            $this->parentField = 'item';
        } else {
            // Par défaut: fonds
            $this->sourceTable = 'media_fonds';
            $this->relatedTable = 'media_fonds_related';
            $this->parentModel = Fond::class;
            $this->parentField = 'fond';
        }
    }

    protected function processRelatedItem($relatedItem): void
    {

        if (empty($relatedItem->filename)) {
            $this->skipped++;

            return;
        }

        // 1. Récupérer le code du parent via resource_id
        $mediaParent = DB::table($this->sourceTable)
            ->where('id', $relatedItem->resource_id)
            ->first();

        if (! $mediaParent) {
            $this->skipped++;
            $this->errorMessages[] = "ID {$relatedItem->id}: {$this->sourceTable} introuvable pour resource_id {$relatedItem->resource_id}";

            $this->line("  [DRY-RUN] Créerait pas l'item pour le {$this->parentField} {$parent->code} ---------------------------ID {$relatedItem->id}: {$this->sourceTable} introuvable pour resource_id {$relatedItem->resource_id}");

            return;
        }

        // 2. Trouver le parent correspondant dans notre base
        $parent = $this->parentModel::where('code', $mediaParent->code)->first();

        if (! $parent) {

            $this->skipped++;
            $this->errorMessages[] = "ID {$relatedItem->id}: {$this->parentField} introuvable avec code {$mediaParent->code}";

            $this->line("  [DRY-RUN] Créerait pas l'item pour le {$this->parentField} {$parent->code} ---------------------------ID {$relatedItem->id}: {$this->parentField} introuvable avec code {$mediaParent->code}");

            return;
        }

        // 3. Extraire le code (nom de fichier sans extension)
        // $code = pathinfo($relatedItem->filename, PATHINFO_FILENAME);
        $pathInfo = pathinfo($relatedItem->filename);
        $extension = strtolower($pathInfo['extension'] ?? '');
        $filename = $pathInfo['basename'] ?? '';
        $itemCode = pathinfo($filename, PATHINFO_FILENAME);
        $mimeType = $this->getMimeTypeFromExtension($extension);

        // 4. Vérifier si l'item existe déjà
        // Vérifier s'il existe un conflit d'index unique sur code + file_extension
        if (Item::where('code', $itemCode)->where('file_extension', $extension)->exists()) {
            $this->skipped++;
            // $this->logDuplicateConflict($conflictingItem->file_path, $filePath, $itemCode, $fileInfo['file_extension'], $lineNumber);
            $this->errorMessages[] = "ID {$itemCode}: ext {$extension} existe déjà !";
            $this->line("  [DRY-RUN] Créerait pas l'item : {$itemCode} pour le {$this->parentField} {$parent->code} ---------------------------");

            return;
        }

        // 5. Mode dry-run : ne pas sauvegarder
        if ($this->option('dry-run')) {
            $this->imported++;
            $this->line("  [DRY-RUN] Créerait l'item : {$itemCode} pour le {$this->parentField} {$parent->code}");

            return;
        }

        // 6. Préparer les données pour l'insertion
        $data = [
            'itemable_type' => $this->parentModel,
            'itemable_id' => $parent->id,
            'code' => $itemCode,
            'code_prefix' => $itemCode,
            'title' => $relatedItem->title ?: null,
            'file_path' => $relatedItem->filename,
            'file_name' => $filename,
            'is_sub' => true,
            'file_extension' => $extension,
            'file_type' => $mimeType,
            'public_access' => (isset($relatedItem->public_access) && $relatedItem->public_access !== '' && $relatedItem->public_access !== null)
                ? $relatedItem->public_access
                : config('mms.access.defaults.media'),
            'file_size' => 0, // Valeur par défaut
            'duration' => 0,   // Valeur par défaut
            'created_by' => 1, // ID utilisateur par défaut
            'uploaded_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'upload_date' => now(),
        ];

        // Métadonnées supplémentaires si disponibles
        if ($relatedItem->mime_type) {
            $data['file_type'] = $relatedItem->mime_type;
        }

        // 7. Insérer l'item directement avec DB::insert
        DB::table('items')->insert($data);
        $this->imported++;
    }

    protected function displaySummary(): void
    {
        $this->info('=== Résumé de l\'importation ===');
        $this->table(
            ['Statut', 'Nombre'],
            [
                ['✓ Importés', $this->imported],
                ['⊘ Ignorés', $this->skipped],
                ['✗ Erreurs', $this->errors],
            ]
        );

        if (! empty($this->errorMessages) && $this->errors > 0) {
            $this->newLine();
            $this->error('Détails des erreurs :');
            foreach (array_slice($this->errorMessages, 0, 20) as $message) {
                $this->line("  - {$message}");
            }

            if (count($this->errorMessages) > 20) {
                $this->line('  ... et '.(count($this->errorMessages) - 20).' autres erreurs');
            }
        }
    }

    /**
     * Obtenir le type MIME depuis l'extension
     */
    private function getMimeTypeFromExtension(string $extension): string
    {
        $mimeTypes = [
            'wav' => 'audio/wav',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            'ogg' => 'audio/ogg',
            'aiff' => 'audio/aiff',
            'aif' => 'audio/aiff',
            'm4a' => 'audio/mp4',
            'flac' => 'audio/flac',
            'aac' => 'audio/aac',
            'wma' => 'audio/x-ms-wma',
            'avi' => 'video/x-msvideo',
            'wmv' => 'video/x-ms-wmv',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.ms-word',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'odt' => 'application/vnd.oasis.opendocument.text',
            'gif' => 'image/gif',
            'tif' => 'image/tiff',
            'tiff' => 'image/tiff',
            'flv' => 'video/x-flv',
            'mid' => 'audio/midi',
            'vod' => 'video/x-msvideo',
            'aiff' => 'audio/x-aiff',
            'aif' => 'audio/aiff',
            'm4v' => 'video/x-m4v',
            'ajame' => 'audio/ajame',
            'a' => 'audio/basic',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';

    }
}
