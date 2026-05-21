<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\Corpus;
use App\Models\Fond;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportCNRSMHECommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'import:cnrsmh-e
                           {csv? : Chemin vers le fichier CSV à importer}
                           {--user-id=1 : ID de l\'utilisateur créateur (défaut: 1)}
                           {--dry-run : Afficher les opérations sans les exécuter}';

    /**
     * The console command description.
     */
    protected $description = 'Importe les fonds, corpus, collections et items depuis le fichier CSV pour les codes CNRSMH_E avec 2 séries de 3 chiffres';

    /**
     * Extensions trouvées pour statistiques
     */
    private array $foundExtensions = [];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Récupération des arguments et options
        $csvFile = $this->argument('csv') ?: storage_path('app/cnrsmh_matches2.csv');
        $userId = $this->option('user-id');
        $isDryRun = $this->option('dry-run');

        // Vérification que le fichier CSV existe
        if (! File::exists($csvFile)) {
            $this->error("Le fichier CSV '{$csvFile}' n'existe pas.");

            return Command::FAILURE;
        }

        $this->info("Importation E depuis : {$csvFile}");
        $this->info("Utilisateur créateur ID : {$userId}");

        if ($isDryRun) {
            $this->warn('MODE DRY-RUN : Aucune donnée ne sera sauvegardée');
        }

        try {
            // Lecture du CSV
            $csvData = $this->readCSV($csvFile);

            if (empty($csvData)) {
                $this->error('Le fichier CSV est vide ou mal formaté');

                return Command::FAILURE;
            }

            $stats = [
                'fonds_created' => 0,
                'fonds_existing' => 0,
                'corpus_created' => 0,
                'corpus_existing' => 0,
                'collections_created' => 0,
                'collections_existing' => 0,
                'items_created' => 0,
                'items_existing' => 0,
                'errors' => 0,
            ];

            $this->info('Traitement de '.count($csvData).' lignes...');

            foreach ($csvData as $index => $row) {
                $this->processRow($row, $userId, $isDryRun, $stats, $index + 1);
            }

            // Affichage des statistiques
            $this->displayStats($stats, $isDryRun);

            // Afficher les extensions trouvées
            $this->displayFoundExtensions();

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Erreur lors de l'importation : ".$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Lire le fichier CSV
     */
    private function readCSV(string $filePath): array
    {
        $data = [];
        $file = fopen($filePath, 'r');

        // Ignorer le BOM UTF-8 si présent
        $bom = fread($file, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($file);
        }

        // Ignorer la ligne d'en-têtes
        $headers = fgetcsv($file, 0, ';');

        while (($row = fgetcsv($file, 0, ';')) !== false) {
            if (count($row) >= 2) {
                $data[] = [
                    'ligne_complete' => $row[0],
                    'pattern_trouve' => $row[1],
                ];
            }
        }

        fclose($file);

        return $data;
    }

    /**
     * Traiter une ligne du CSV
     */
    private function processRow(array $row, int $userId, bool $isDryRun, array &$stats, int $lineNumber): void
    {
        try {
            $pattern = $row['pattern_trouve'];
            $filePath = $row['ligne_complete'];

            // Extraire les parties du pattern pour CNRSMH_E
            // Exemple: CNRSMH_E_1963_008_123_ ->
            // Fond: CNRSMH_E
            // Corpus: CNRSMH_E_1963
            // Collection: CNRSMH_E_1963_008_123

            if (! preg_match('/CNRSMH_E_(\d{4})_(\d{3})_(\d{3})/', $pattern, $matches)) {
                $this->warn("Ligne {$lineNumber}: Pattern invalide '{$pattern}' (attendu: CNRSMH_E_YYYY_XXX_XXX)");
                $stats['errors']++;

                return;
            }

            $year = $matches[1];
            $firstThreeDigits = $matches[2];
            $lastThreeDigits = $matches[3];

            // Codes à créer
            $fondCode = 'CNRSMH_E';
            $corpusCode = "CNRSMH_E_{$year}";
            $collectionCode = "CNRSMH_E_{$year}_{$firstThreeDigits}_{$lastThreeDigits}";

            // Extraire les informations du fichier
            $fileInfo = $this->extractFileInfo($filePath);

            // Créer ou récupérer le fond
            $fond = $this->createOrGetFond($fondCode, $userId, $isDryRun, $stats);
            if (! $fond && ! $isDryRun) {
                $stats['errors']++;

                return;
            }

            // Créer ou récupérer le corpus
            $corpus = $this->createOrGetCorpus($corpusCode, $fond, $year, $userId, $isDryRun, $stats);
            if (! $corpus && ! $isDryRun) {
                $stats['errors']++;

                return;
            }

            // Créer ou récupérer la collection
            $collection = $this->createOrGetCollection($collectionCode, $corpus, $year, $firstThreeDigits, $lastThreeDigits, $userId, $isDryRun, $stats);
            if (! $collection && ! $isDryRun) {
                $stats['errors']++;

                return;
            }

            // Créer l'item
            $this->createOrGetItem($collection, $collectionCode, $filePath, $fileInfo, $userId, $isDryRun, $stats, $lineNumber);

        } catch (\Exception $e) {
            $this->error("Erreur ligne {$lineNumber}: ".$e->getMessage());
            $stats['errors']++;
        }
    }

    /**
     * Extraire les informations du fichier depuis le chemin
     */
    private function extractFileInfo(string $filePath): array
    {
        $pathInfo = pathinfo($filePath);
        $extension = strtolower($pathInfo['extension'] ?? '');
        $fileName = $pathInfo['basename'] ?? '';

        // Ajouter l'extension aux statistiques
        if (! empty($extension)) {
            if (! isset($this->foundExtensions[$extension])) {
                $this->foundExtensions[$extension] = 0;
            }
            $this->foundExtensions[$extension]++;
        }

        // Déterminer le type MIME
        $mimeType = $this->getMimeTypeFromExtension($extension);

        return [
            'file_name' => $fileName,
            'file_extension' => $extension,
            'file_type' => $mimeType,
            'file_size' => 0, // Valeur par défaut
            'duration' => 0,   // Valeur par défaut
        ];
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
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Créer ou récupérer un fond
     */
    private function createOrGetFond(string $code, int $userId, bool $isDryRun, array &$stats)
    {
        if ($isDryRun) {
            $this->line("  [FOND] Créerait/récupérerait: {$code}");
            $stats['fonds_created']++;

            return (object) ['id' => 1, 'code' => $code];
        }

        $fond = Fond::where('code', $code)->first();

        if ($fond) {
            $stats['fonds_existing']++;

            return $fond;
        }

        $fond = Fond::create([
            'code' => $code,
            'title' => 'Fonds CNRSMH E',
            'created_by' => $userId,
        ]);

        $this->info("  [FOND CRÉÉ] {$code}");
        $stats['fonds_created']++;

        return $fond;
    }

    /**
     * Créer ou récupérer un corpus
     */
    private function createOrGetCorpus(string $code, $fond, string $year, int $userId, bool $isDryRun, array &$stats)
    {
        if ($isDryRun) {
            $this->line("  [CORPUS] Créerait/récupérerait: {$code}");
            $stats['corpus_created']++;

            return (object) ['id' => 1, 'code' => $code];
        }

        $corpus = Corpus::where('code', $code)->first();

        if ($corpus) {
            $stats['corpus_existing']++;

            return $corpus;
        }

        $corpus = Corpus::create([
            'fond_id' => $fond->id,
            'code' => $code,
            'title' => "Corpus E {$year}",
            'created_by' => $userId,
        ]);

        $this->info("  [CORPUS CRÉÉ] {$code}");
        $stats['corpus_created']++;

        return $corpus;
    }

    /**
     * Créer ou récupérer une collection
     */
    private function createOrGetCollection(string $code, $corpus, string $year, string $firstDigits, string $lastDigits, int $userId, bool $isDryRun, array &$stats)
    {
        if ($isDryRun) {
            $this->line("  [COLLECTION] Créerait/récupérerait: {$code}");
            $stats['collections_created']++;

            return (object) ['id' => 1, 'code' => $code];
        }

        $collection = Collection::where('code', $code)->first();

        if ($collection) {
            $stats['collections_existing']++;

            return $collection;
        }

        $collection = Collection::create([
            'corpus_id' => $corpus->id,
            'code' => $code,
            'title' => "Collection E {$year}_{$firstDigits}_{$lastDigits}",
            'created_by' => $userId,
        ]);

        $this->info("  [COLLECTION CRÉÉE] {$code}");
        $stats['collections_created']++;

        return $collection;
    }

    /**
     * Créer ou récupérer un item avec requête SQL brute
     */
    private function createOrGetItem($collection, string $collectionCode, string $filePath, array $fileInfo, int $userId, bool $isDryRun, array &$stats, int $lineNumber): void
    {
        // Le code de l'item correspond au nom du fichier sans extension
        $itemCode = pathinfo($fileInfo['file_name'], PATHINFO_FILENAME);

        if ($isDryRun) {
            $this->line("  [ITEM] Créerait/récupérerait: {$itemCode}");
            $this->line("    File: {$filePath}");
            $this->line("    Extension: {$fileInfo['file_extension']}");
            $this->line("    MIME: {$fileInfo['file_type']}");
            $stats['items_created']++;

            return;
        }

        try {
            // Vérifier si l'item existe déjà avec une requête SQL brute
            $existingItem = DB::selectOne("
                SELECT id FROM items
                WHERE code = ? AND itemable_type = 'App\\Models\\Collection' AND itemable_id = ?
            ", [$itemCode, $collection->id]);

            if ($existingItem) {
                $stats['items_existing']++;

                return;
            }

            // Vérifier s'il existe un conflit d'index unique sur code + file_extension
            $conflictingItem = \App\Models\Item::where('code', $itemCode)
                ->where('file_extension', $fileInfo['file_extension'])
                ->first();

            if ($conflictingItem) {
                // Écrire le conflit dans un fichier CSV
                $this->logDuplicateConflict($conflictingItem->file_path, $filePath, $itemCode, $fileInfo['file_extension'], $lineNumber);
                $stats['items_existing']++;
                $this->warn("  [CONFLIT E] Item avec code '{$itemCode}' et extension '{$fileInfo['file_extension']}' existe déjà");

                return;
            }

            // Insérer l'item avec une requête SQL brute
            $now = now()->format('Y-m-d H:i:s');

            DB::insert('
                INSERT INTO items (
                    itemable_type, itemable_id, code, title, file_path, file_name,
                    file_size, file_type, file_extension, duration, upload_date,
                    uploaded_by, created_by, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ', [
                'App\\Models\\Collection',
                $collection->id,
                $itemCode,
                'Item '.$itemCode,
                $filePath,
                $fileInfo['file_name'],
                $fileInfo['file_size'],
                $fileInfo['file_type'],
                $fileInfo['file_extension'],
                $fileInfo['duration'],
                now()->toDateString(),
                $userId,
                $userId,
                $now,
                $now,
            ]);

            $this->info("  [ITEM CRÉÉ] {$itemCode}");
            $stats['items_created']++;

        } catch (\Exception $e) {
            // Vérifier si c'est une erreur de contrainte unique
            if (str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'UNIQUE constraint')) {
                $this->handleDuplicateError($itemCode, $fileInfo['file_extension'], $filePath, $lineNumber, $stats);
            } else {
                $this->error("Erreur création item ligne {$lineNumber}: ".$e->getMessage());
                $stats['errors']++;
            }
        }
    }

    /**
     * Gérer les erreurs de doublons
     */
    private function handleDuplicateError(string $itemCode, string $extension, string $newFilePath, int $lineNumber, array &$stats): void
    {
        // Rechercher l'item existant avec Eloquent
        $existingItem = \App\Models\Item::where('code', $itemCode)
            ->where('file_extension', $extension)
            ->first();

        if ($existingItem) {
            $this->logDuplicateConflict($existingItem->file_path, $newFilePath, $itemCode, $extension, $lineNumber);
            $this->warn("  [DOUBLON DÉTECTÉ E] Item '{$itemCode}.{$extension}' existe déjà (ligne {$lineNumber})");
            $stats['items_existing']++;
        } else {
            $this->error("Erreur de doublon non identifiée ligne {$lineNumber}");
            $stats['errors']++;
        }
    }

    /**
     * Enregistrer les conflits de doublons dans un fichier CSV
     */
    private function logDuplicateConflict(string $existingFilePath, string $newFilePath, string $itemCode, string $extension, int $lineNumber): void
    {
        $conflictFile = storage_path('app/cnrsmh_e_conflicts.csv');
        $isNewFile = ! file_exists($conflictFile);

        $file = fopen($conflictFile, 'a');

        // Ajouter BOM UTF-8 pour Excel si c'est un nouveau fichier
        if ($isNewFile) {
            fwrite($file, "\xEF\xBB\xBF");
            // Écrire les en-têtes
            fputcsv($file, [
                'ligne_csv',
                'code_item',
                'extension',
                'file_path_existant',
                'file_path_nouveau',
                'date_detection',
            ], ';');
        }

        // Écrire la ligne de conflit
        fputcsv($file, [
            $lineNumber,
            $itemCode,
            $extension,
            $existingFilePath,
            $newFilePath,
            now()->format('Y-m-d H:i:s'),
        ], ';');

        fclose($file);
    }

    /**
     * Afficher les statistiques
     */
    private function displayStats(array $stats, bool $isDryRun): void
    {
        $this->info("\n=== STATISTIQUES E ".($isDryRun ? '(DRY-RUN)' : '').' ===');
        $this->info("Fonds créés: {$stats['fonds_created']}");
        $this->info("Fonds existants: {$stats['fonds_existing']}");
        $this->info("Corpus créés: {$stats['corpus_created']}");
        $this->info("Corpus existants: {$stats['corpus_existing']}");
        $this->info("Collections créées: {$stats['collections_created']}");
        $this->info("Collections existantes: {$stats['collections_existing']}");
        $this->info("Items créés: {$stats['items_created']}");
        $this->info("Items existants: {$stats['items_existing']}");

        if ($stats['errors'] > 0) {
            $this->error("Erreurs: {$stats['errors']}");
        }

        // Vérifier si un fichier de conflits a été créé
        $conflictFile = storage_path('app/cnrsmh_e_conflicts.csv');
        if (file_exists($conflictFile) && ! $isDryRun) {
            $this->warn("\n⚠️  Fichier de conflits E généré : {$conflictFile}");
            $this->info('Ce fichier contient les détails des items en doublon détectés.');
        }
    }

    /**
     * Afficher les extensions trouvées
     */
    private function displayFoundExtensions(): void
    {
        if (! empty($this->foundExtensions)) {
            $this->info("\n=== EXTENSIONS DE FICHIERS TROUVÉES ===");

            arsort($this->foundExtensions); // Trier par nombre décroissant

            foreach ($this->foundExtensions as $extension => $count) {
                $mimeType = $this->getMimeTypeFromExtension($extension);
                $this->info("  .{$extension} : {$count} fichiers ({$mimeType})");
            }

            $totalFiles = array_sum($this->foundExtensions);
            $this->info("\nTotal fichiers traités: {$totalFiles}");
        }
    }
}
