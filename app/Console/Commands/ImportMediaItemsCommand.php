<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportMediaItemsCommand extends Command
{
    protected $signature = 'import:media-items
                            {--dry-run : Exécution en mode test sans sauvegarder}
                            {--limit= : Limiter le nombre d\'imports}';

    protected $description = 'Importer les items depuis la table media_items';

    protected int $imported = 0;

    protected int $imported_sub = 0;

    protected int $imported_duplicate = 0;

    protected int $skipped = 0;

    protected int $errors = 0;

    protected array $errorMessages = [];

    public function handle(): int
    {
        $this->info('=== Import des Items depuis media_items ===');
        $this->newLine();

        // Récupérer les données de media_items
        $query = DB::table('media_items')
            ->select([
                'id',
                'title',
                'filename',
                'collection_id',
                'code',
                'mimetype',
                'summary',
                'comment',
            ])
            ->whereNotNull('collection_id')
            ->where('collection_id', '!=', 0);

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $mediaItems = $query->get();

        if ($mediaItems->isEmpty()) {
            $this->warn('Aucun élément trouvé dans media_items avec collection_id valide');

            return Command::SUCCESS;
        }

        $this->info("Nombre d'éléments à traiter : {$mediaItems->count()}");
        $this->newLine();

        // Créer la barre de progression
        $progressBar = $this->output->createProgressBar($mediaItems->count());
        $progressBar->start();

        foreach ($mediaItems as $mediaItem) {
            try {
                $this->processMediaItem($mediaItem);
            } catch (\Exception $e) {
                $this->errors++;
                $this->errorMessages[] = sprintf(
                    'ID %d (code: %s): %s',
                    $mediaItem->id,
                    $mediaItem->code,
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

    protected function processMediaItem($mediaItem): void
    {
        // 1. Récupérer le code de collection depuis media_collections via collection_id
        $mediaCollection = DB::table('media_collections')
            ->where('id', $mediaItem->collection_id)
            ->first();

        if (! $mediaCollection) {
            $this->skipped++;
            $this->errorMessages[] = "ID {$mediaItem->id}: media_collections introuvable pour collection_id {$mediaItem->collection_id}";

            return;
        }

        // 2. Trouver la collection correspondante dans notre base via le code
        $collection = Collection::where('code', $mediaCollection->code)->first();

        if (! $collection) {
            $this->skipped++;
            $this->errorMessages[] = "ID {$mediaItem->id}: Collection introuvable avec code {$mediaCollection->code}";

            return;
        }

        // 3. Vérifier si l'item a un fichier principal ou des fichiers liés
        $hasMainFile = ! empty($mediaItem->filename);
        $hasRelatedFiles = DB::table('media_item_related')
            ->where('item_id', $mediaItem->id)
            ->whereNotNull('filename')
            ->where('filename', '!=', '')
            ->exists();

        // Si ni fichier principal ni fichiers liés, on saute
        if (! $hasMainFile && ! $hasRelatedFiles) {
            $this->skipped++;
            $this->errorMessages[] = "ID {$mediaItem->id}: Aucun fichier principal ou lié trouvé";

            return;
        }

        // 4. Extraire les informations du fichier principal si disponible
        $extension = null;
        $filename = null;
        $itemCode = null;
        $codePrefix = null;
        $codeSuffix = null;

        if ($hasMainFile) {
            $pathInfo = pathinfo($mediaItem->filename);
            $extension = strtolower($pathInfo['extension'] ?? '');
            $filename = $pathInfo['basename'] ?? '';
        }

        // 5. Logique complexe de détermination du code
        $originalCode = null;
        if (! empty($mediaItem->code)) {
            $collectionCode = $collection->code;

            // Vérifier si le code de l'item commence par le code de la collection
            if (str_starts_with($mediaItem->code, $collectionCode)) {
                // Le code commence par le code de collection
                $codePrefix = $collectionCode;

                // Extraire le reste du code (après le code de collection)
                $remainingCode = substr($mediaItem->code, strlen($collectionCode));

                // Si le reste commence par un underscore, on le retire
                if (str_starts_with($remainingCode, '_')) {
                    $remainingCode = substr($remainingCode, 1);
                }

                // Le reste devient le code_suffix
                if (! empty($remainingCode)) {
                    $codeSuffix = $remainingCode;
                    $itemCode = $collectionCode.'_'.$remainingCode;
                } else {
                    // Pas de suffix, le code est juste le code de collection
                    $itemCode = $collectionCode;
                }
            } else {
                // Le code ne commence pas par le code de collection
                // On utilise la méthode classique : code complet sans distinction prefix/suffix
                $itemCode = $mediaItem->code;
                $codePrefix = $mediaItem->code;
                $codeSuffix = null;
            }
        } else {
            // Pas de code dans media_items, utiliser le nom du fichier principal ou un code généré
            if ($hasMainFile) {
                $itemCode = pathinfo($filename, PATHINFO_FILENAME);
                $codePrefix = $itemCode;
                $codeSuffix = null;
            } else {
                // Générer un code temporaire basé sur l'ID
                $itemCode = 'TEMP_'.$mediaItem->id;
                $codePrefix = $itemCode;
                $codeSuffix = null;
            }
        }

        // 6. Déterminer le type MIME
        $mimeType = null;
        if ($hasMainFile) {
            $mimeType = $this->getMimeTypeFromExtension($extension);
        }

        // 7. Gérer les conflits de code
        $originalItemCode = $itemCode;
        $increment = 1;
        $maxAttempts = 100; // Limite pour éviter les boucles infinies

        while ($hasMainFile && Item::where('code', $itemCode)->where('file_extension', $extension)->exists()) {
            if ($increment >= $maxAttempts) {
                $this->skipped++;
                $this->errorMessages[] = "ID {$mediaItem->id}: Impossible de trouver un code unique pour {$originalItemCode} (trop de tentatives)";

                return;
            }

            // Ajouter un incrément au code
            $itemCode = $originalItemCode.'_DUPLICATE_'.$increment;

            // Si on a un code_suffix, on l'incrémente aussi
            if ($codeSuffix) {
                $codeSuffix = $originalCode.'_DUPLICATE_'.$increment;
                $itemCode = $codePrefix.'_'.$codeSuffix;
            }

            $increment++;
        }

        if ($increment > 1) {
            $this->imported_duplicate++;
        }

        // 8. Mode dry-run : ne pas sauvegarder
        if ($this->option('dry-run')) {
            $debugInfo = $codeSuffix
                ? "prefix={$codePrefix}, suffix={$codeSuffix}, code={$itemCode}"
                : "code={$itemCode}";

            $fileInfo = $hasMainFile
                ? "fichier principal: {$filename}"
                : 'pas de fichier principal, mais fichiers liés';

            $this->imported++;
            $this->line("  [DRY-RUN] Créerait l'item : {$debugInfo} ({$fileInfo}) pour la collection {$collection->code}");

            return;
        }

        // 9. Préparer les données pour l'insertion
        $data = [
            'itemable_type' => Collection::class,
            'itemable_id' => $collection->id,
            'code' => $itemCode,
            'code_prefix' => $codePrefix,
            'code_suffix' => $codeSuffix,
            'title' => $mediaItem->title ?: null,
            'file_path' => $hasMainFile ? $mediaItem->filename : null,
            'file_name' => $hasMainFile ? $filename : null,
            'is_sub' => false, // Les items de media_items sont des items principaux
            'file_extension' => $hasMainFile ? $extension : null,
            'file_type' => $mimeType,
            'file_size' => 0, // Valeur par défaut
            'duration' => 0,   // Valeur par défaut
            'created_by' => 1, // ID utilisateur par défaut
            'uploaded_by' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'upload_date' => now(),
        ];

        // 10. Insérer l'item directement avec DB::insert
        DB::table('items')->insert($data);

        $last_item_insert = DB::getPdo()->lastInsertId(); // ID de l'item principal
        $this->imported++;

        // 11. Si l'item a des fichiers liés, les ajouter comme items secondaires
        if ($hasRelatedFiles) {
            $relatedFiles = DB::table('media_item_related')
                ->where('item_id', $mediaItem->id)
                ->whereNotNull('filename')
                ->where('filename', '!=', '')
                ->get();

            foreach ($relatedFiles as $relatedFile) {
                $relatedPathInfo = pathinfo($relatedFile->filename);
                $relatedExtension = strtolower($relatedPathInfo['extension'] ?? '');
                $relatedFilename = $relatedPathInfo['basename'] ?? '';
                $relatedItemCode = pathinfo($relatedFilename, PATHINFO_FILENAME);

                // Vérifier si le fichier lié existe déjà
                if (Item::where('code', $relatedItemCode)->where('file_extension', $relatedExtension)->exists()) {
                    continue;
                }

                $relatedMimeType = ! empty($relatedFile->mime_type)
                    ? $relatedFile->mime_type
                    : $this->getMimeTypeFromExtension($relatedExtension);

                $relatedData = [
                    'itemable_type' => Item::class, // Item parent
                    'itemable_id' => $last_item_insert, // ID de l'item principal
                    'code' => $relatedItemCode,
                    'code_prefix' => $relatedItemCode,
                    'code_suffix' => null,
                    'title' => $relatedFile->title ?: null,
                    'file_path' => $relatedFile->filename,
                    'file_name' => $relatedFilename,
                    'is_sub' => true, // Item secondaire
                    'file_extension' => $relatedExtension,
                    'file_type' => $relatedMimeType,
                    'file_size' => 0, // Valeur par défaut
                    'duration' => 0,   // Valeur par défaut
                    'created_by' => 1, // ID utilisateur par défaut
                    'uploaded_by' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'upload_date' => now(),
                ];

                DB::table('items')->insert($relatedData);

                $this->imported_sub++;
            }
        }
    }

    protected function displaySummary(): void
    {
        $this->info('=== Résumé de l\'importation ===');
        $this->table(
            ['Statut', 'Nombre'],
            [
                ['✓ Importés', $this->imported],
                ['✓ Importés sub', $this->imported_sub],
                ['✓ Importés duplicate', $this->imported_duplicate],
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
            'm4v' => 'video/x-m4v',
            'ajame' => 'audio/ajame',
            'a' => 'audio/basic',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}
