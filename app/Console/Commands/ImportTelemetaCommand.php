<?php

namespace App\Console\Commands;

use App\Models\Collection;
use App\Models\Corpus;
use App\Models\Fond;
use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportTelemetaCommand extends Command
{
    protected $signature = 'import:telemeta
                            {--only= : Étapes à exécuter parmi fonds,corpus,collections,items,related (séparées par des virgules)}
                            {--dry-run : Afficher les opérations sans les exécuter}
                            {--limit= : Limiter le nombre de lignes lues par table source}
                            {--user-id=1 : ID de l\'utilisateur créateur}';

    protected $description = 'Import complet depuis les tables Telemeta media_* : fonds, corpus, collections, items et fichiers liés. Idempotent (skip si existe), aucune suppression.';

    private const STEPS = ['fonds', 'corpus', 'collections', 'items', 'related'];

    protected bool $dryRun = false;

    protected int $limit = 0;

    /** code -> id des entités applicatives */
    protected array $fondsByCode = [];

    protected array $corpusesByCode = [];

    protected array $collectionsByCode = [];

    /** id media_* -> code */
    protected array $mediaFondsCodeById = [];

    protected array $mediaCorpusCodeById = [];

    protected array $mediaCollectionsCodeById = [];

    /** clés "code|extension" des items existants, y compris soft-deleted (l'index unique ne les exclut pas) */
    protected array $itemKeys = [];

    protected array $stats = [];

    protected array $errorMessages = [];

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->limit = (int) $this->option('limit');
        $userId = (int) $this->option('user-id');

        $steps = $this->resolveSteps();
        if ($steps === null) {
            return Command::FAILURE;
        }

        $this->info('=== Import Telemeta (tables media_*) ===');
        $this->info('Utilisateur créateur ID : '.$userId);
        if ($this->dryRun) {
            $this->warn('MODE DRY-RUN : aucune donnée ne sera écrite');
        }
        $this->newLine();

        $this->loadMaps();

        foreach ($steps as $step) {
            $this->stats[$step] = ['created' => 0, 'skipped' => 0, 'subs' => 0, 'links' => 0, 'errors' => 0];

            match ($step) {
                'fonds' => $this->importFonds($userId),
                'corpus' => $this->importCorpus($userId),
                'collections' => $this->importCollections($userId),
                'items' => $this->importItems($userId),
                'related' => $this->importRelated($userId),
            };
        }

        $this->newLine();
        $this->displaySummary();

        return Command::SUCCESS;
    }

    /**
     * Résoudre les étapes à exécuter depuis --only (toutes par défaut).
     */
    private function resolveSteps(): ?array
    {
        $only = $this->option('only');

        if (empty($only)) {
            return self::STEPS;
        }

        $steps = array_values(array_filter(array_map('trim', explode(',', strtolower($only)))));

        if ($steps === []) {
            $this->error('Option --only vide. Étapes valides : '.implode(', ', self::STEPS));

            return null;
        }

        $invalid = array_diff($steps, self::STEPS);
        if ($invalid !== []) {
            $this->error('Étapes inconnues : '.implode(', ', $invalid).' (valides : '.implode(', ', self::STEPS).')');

            return null;
        }

        return $steps;
    }

    /**
     * Charger en mémoire les correspondances code<->id et les items déjà présents.
     */
    private function loadMaps(): void
    {
        $this->fondsByCode = DB::table('fonds')->pluck('id', 'code')->all();
        $this->corpusesByCode = DB::table('corpuses')->pluck('id', 'code')->all();
        $this->collectionsByCode = DB::table('collections')->pluck('id', 'code')->all();

        foreach ($this->mediaTable('media_fonds')->select('id', 'code')->get() as $row) {
            $this->mediaFondsCodeById[$row->id] = $row->code;
        }
        foreach ($this->mediaTable('media_corpus')->select('id', 'code')->get() as $row) {
            $this->mediaCorpusCodeById[$row->id] = $row->code;
        }
        foreach ($this->mediaTable('media_collections')->select('id', 'code')->get() as $row) {
            $this->mediaCollectionsCodeById[$row->id] = $row->code;
        }

        foreach (DB::table('items')->select('code', 'file_extension')->get() as $row) {
            $this->itemKeys[$this->itemKey($row->code, $row->file_extension)] = true;
        }
    }

    private function itemKey(?string $code, ?string $extension): string
    {
        return mb_strtolower((string) $code).'|'.mb_strtolower((string) $extension);
    }

    /**
     * Étape 1 : fonds depuis media_fonds.
     */
    private function importFonds(int $userId): void
    {
        $this->info('--- Étape 1/5 : Fonds (media_fonds) ---');

        $rows = $this->sourceQuery('media_fonds')->get();
        $bar = $this->output->createProgressBar(count($rows));

        foreach ($rows as $row) {
            $bar->advance();

            if (trim((string) $row->code) === '') {
                $this->stats['fonds']['errors']++;
                $this->logError("media_fonds ID {$row->id} : code vide");

                continue;
            }

            if (isset($this->fondsByCode[$row->code])) {
                $this->stats['fonds']['skipped']++;

                continue;
            }

            if ($this->dryRun) {
                $this->stats['fonds']['created']++;

                continue;
            }

            try {
                $now = now();
                DB::table('fonds')->insert([
                    'code' => $row->code,
                    'title' => $row->title ?: null,
                    'public_access' => $this->mapPublicAccess($row->public_access, 'fond'),
                    'created_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->fondsByCode[$row->code] = (int) DB::getPdo()->lastInsertId();
                $this->stats['fonds']['created']++;
            } catch (\Throwable $e) {
                $this->stats['fonds']['errors']++;
                $this->logError("media_fonds ID {$row->id} ({$row->code}) : ".$e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Étape 2 : corpus depuis media_corpus + pivots corpus_fond depuis media_fonds_children.
     */
    private function importCorpus(int $userId): void
    {
        $this->info('--- Étape 2/5 : Corpus (media_corpus + media_fonds_children) ---');

        $rows = $this->sourceQuery('media_corpus')->get();
        $bar = $this->output->createProgressBar(count($rows));

        foreach ($rows as $row) {
            $bar->advance();

            if (trim((string) $row->code) === '') {
                $this->stats['corpus']['errors']++;
                $this->logError("media_corpus ID {$row->id} : code vide");

                continue;
            }

            if (isset($this->corpusesByCode[$row->code])) {
                $this->stats['corpus']['skipped']++;

                continue;
            }

            if ($this->dryRun) {
                $this->stats['corpus']['created']++;

                continue;
            }

            try {
                $now = now();
                DB::table('corpuses')->insert([
                    'code' => $row->code,
                    'title' => $row->title ?: null,
                    'public_access' => $this->mapPublicAccess($row->public_access, 'corpus'),
                    'created_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->corpusesByCode[$row->code] = (int) DB::getPdo()->lastInsertId();
                $this->stats['corpus']['created']++;
            } catch (\Throwable $e) {
                $this->stats['corpus']['errors']++;
                $this->logError("media_corpus ID {$row->id} ({$row->code}) : ".$e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine();

        $this->importPivot('corpus_fond', 'media_fonds_children', 'corpus_id', 'fond_id', 'mediacorpus_id', 'mediafonds_id', $this->corpusesByCode, $this->fondsByCode, $this->mediaCorpusCodeById, $this->mediaFondsCodeById, 'corpus');
    }

    /**
     * Étape 3 : collections depuis media_collections + pivots collection_corpus depuis media_corpus_children.
     */
    private function importCollections(int $userId): void
    {
        $this->info('--- Étape 3/5 : Collections (media_collections + media_corpus_children) ---');

        $rows = $this->sourceQuery('media_collections')->get();
        $bar = $this->output->createProgressBar(count($rows));

        foreach ($rows as $row) {
            $bar->advance();

            if (trim((string) $row->code) === '') {
                $this->stats['collections']['errors']++;
                $this->logError("media_collections ID {$row->id} : code vide");

                continue;
            }

            if (isset($this->collectionsByCode[$row->code])) {
                $this->stats['collections']['skipped']++;

                continue;
            }

            if ($this->dryRun) {
                $this->stats['collections']['created']++;

                continue;
            }

            try {
                $now = now();
                DB::table('collections')->insert([
                    'code' => $row->code,
                    'title' => $row->title ?: null,
                    'public_access' => $this->mapPublicAccess($row->public_access, 'collection'),
                    'created_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->collectionsByCode[$row->code] = (int) DB::getPdo()->lastInsertId();
                $this->stats['collections']['created']++;
            } catch (\Throwable $e) {
                $this->stats['collections']['errors']++;
                $this->logError("media_collections ID {$row->id} ({$row->code}) : ".$e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine();

        $this->importPivot('collection_corpus', 'media_corpus_children', 'collection_id', 'corpus_id', 'mediacollection_id', 'mediacorpus_id', $this->collectionsByCode, $this->corpusesByCode, $this->mediaCollectionsCodeById, $this->mediaCorpusCodeById, 'collections');
    }

    /**
     * Importer les liens de hiérarchie (media_*_children) dans la table pivot applicative.
     *
     * @param  array<int, int>  $childByCode   code enfant -> id app (ex: corpus)
     * @param  array<int, int>  $parentByCode  code parent -> id app (ex: fond)
     * @param  array<int, string>  $childCodeById  id media enfant -> code
     * @param  array<int, string>  $parentCodeById  id media parent -> code
     */
    private function importPivot(string $pivotTable, string $childrenTable, string $pivotChildColumn, string $pivotParentColumn, string $mediaChildColumn, string $mediaParentColumn, array $childByCode, array $parentByCode, array $childCodeById, array $parentCodeById, string $step): void
    {
        $existing = [];
        foreach (DB::table($pivotTable)->select($pivotChildColumn, $pivotParentColumn)->get() as $row) {
            $existing[$row->$pivotChildColumn.'|'.$row->$pivotParentColumn] = true;
        }

        $rows = $this->sourceQuery($childrenTable)->get();
        $bar = $this->output->createProgressBar(count($rows));

        foreach ($rows as $row) {
            $bar->advance();

            $childId = $childByCode[$childCodeById[$row->$mediaChildColumn] ?? ''] ?? null;
            $parentId = $parentByCode[$parentCodeById[$row->$mediaParentColumn] ?? ''] ?? null;

            if ($childId === null || $parentId === null) {
                $this->stats[$step]['errors']++;
                $this->logError("{$childrenTable} ID {$row->id} : parent/enfant absent côté applicatif (parent media {$row->$mediaParentColumn} -> ".($parentCodeById[$row->$mediaParentColumn] ?? '?').", enfant media {$row->$mediaChildColumn} -> ".($childCodeById[$row->$mediaChildColumn] ?? '?').')');

                continue;
            }

            $key = $childId.'|'.$parentId;
            if (isset($existing[$key])) {
                $this->stats[$step]['skipped']++;

                continue;
            }

            if ($this->dryRun) {
                $this->stats[$step]['links']++;

                continue;
            }

            try {
                $now = now();
                DB::table($pivotTable)->insertOrIgnore([
                    $pivotChildColumn => $childId,
                    $pivotParentColumn => $parentId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $existing[$key] = true;
                $this->stats[$step]['links']++;
            } catch (\Throwable $e) {
                $this->stats[$step]['errors']++;
                $this->logError("{$childrenTable} ID {$row->id} : ".$e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Étape 4 : items principaux depuis media_items (+ fichiers liés media_item_related en sous-items).
     * Les items sans code ET sans fichier sont ignorés (comme import:media-items).
     * Insert SQL brut : bypass Item::creating (processFileUpload déplacerait physiquement les fichiers) et ItemObserver.
     */
    private function importItems(int $userId): void
    {
        $this->info('--- Étape 4/5 : Items (media_items + media_item_related) ---');

        $total = $this->itemsQuery()->count();
        $bar = $this->output->createProgressBar($total);

        $process = function ($items) use ($bar, $userId) {
            foreach ($items as $mediaItem) {
                $bar->advance();

                try {
                    $this->processMediaItem($mediaItem, $userId);
                } catch (\Throwable $e) {
                    $this->stats['items']['errors']++;
                    $this->logError("media_items ID {$mediaItem->id} ({$mediaItem->code}) : ".$e->getMessage());
                }
            }
        };

        $query = $this->itemsQuery();

        if ($this->limit > 0) {
            $process($query->limit($this->limit)->get());
        } else {
            $query->chunkById(1000, $process);
        }

        $bar->finish();
        $this->newLine();
    }

    private function itemsQuery()
    {
        return $this->mediaTable('media_items')
            ->select('id', 'code', 'title', 'filename', 'collection_id', 'mimetype', 'public_access', 'digitization_date')
            ->whereNotNull('collection_id')
            ->where('collection_id', '!=', 0)
            ->where(function ($q) {
                // Ignorer les fiches sans code ni fichier (décision métier : comme import:media-items)
                $q->where(function ($q) {
                    $q->whereNotNull('code')->where('code', '!=', '');
                })->orWhere(function ($q) {
                    $q->whereNotNull('filename')->where('filename', '!=', '');
                });
            })
            ->orderBy('id');
    }

    private function processMediaItem(object $mediaItem, int $userId): void
    {
        // 1. Résoudre la collection applicative via le code media_collections
        $mediaCollectionCode = $this->mediaCollectionsCodeById[$mediaItem->collection_id] ?? null;
        $collectionId = $mediaCollectionCode !== null ? ($this->collectionsByCode[$mediaCollectionCode] ?? null) : null;

        if ($collectionId === null) {
            $this->stats['items']['skipped']++;
            $this->logError("media_items ID {$mediaItem->id} : collection introuvable (media_collections ID {$mediaItem->collection_id}, code ".($mediaCollectionCode ?? 'inconnu').')');

            return;
        }

        // 2. Fichier principal
        $hasFile = ! empty($mediaItem->filename);
        $extension = null;
        $fileName = null;
        if ($hasFile) {
            $extension = strtolower(pathinfo($mediaItem->filename, PATHINFO_EXTENSION));
            $extension = $extension !== '' ? $extension : null;
            $fileName = basename($mediaItem->filename);
        }

        // 3. Code (logique reprise de ImportMediaItemsCommand)
        $itemCode = null;
        $codePrefix = null;
        $codeSuffix = null;

        if (! empty($mediaItem->code)) {
            if ($mediaCollectionCode !== null && str_starts_with($mediaItem->code, $mediaCollectionCode)) {
                $codePrefix = $mediaCollectionCode;
                $remaining = substr($mediaItem->code, strlen($mediaCollectionCode));
                if (str_starts_with($remaining, '_')) {
                    $remaining = substr($remaining, 1);
                }

                if ($remaining !== '') {
                    $codeSuffix = $remaining;
                    $itemCode = $mediaCollectionCode.'_'.$remaining;
                } else {
                    $itemCode = $mediaCollectionCode;
                }
            } else {
                $itemCode = $mediaItem->code;
                $codePrefix = $mediaItem->code;
            }
        } else {
            $itemCode = pathinfo((string) $fileName, PATHINFO_FILENAME);
            $codePrefix = $itemCode;
        }

        // 4. Déjà présent (réimport) -> skip, l'idempotence garantit l'absence de doublons
        $key = $this->itemKey($itemCode, $extension);
        if (isset($this->itemKeys[$key])) {
            $this->stats['items']['skipped']++;

            return;
        }

        // 5. Insertion brute de l'item principal
        $itemId = 0;

        if (! $this->dryRun) {
            $now = now();
            DB::table('items')->insert([
                'itemable_type' => Collection::class,
                'itemable_id' => $collectionId,
                'item_type_id' => null,
                'code' => $itemCode,
                'code_prefix' => $codePrefix ?? '',
                'code_suffix' => $codeSuffix,
                'is_sub' => false,
                'title' => $mediaItem->title ?: null,
                'file_path' => $hasFile ? $mediaItem->filename : null,
                'file_name' => $fileName,
                'file_extension' => $extension,
                'file_type' => $hasFile
                    ? ((! empty($mediaItem->mimetype)) ? $mediaItem->mimetype : $this->getMimeTypeFromExtension($extension))
                    : null,
                'file_size' => 0,
                'duration' => 0,
                'upload_date' => $mediaItem->digitization_date ?: now()->toDateString(),
                'uploaded_by' => $userId,
                'created_by' => $userId,
                'public_access' => $this->mapPublicAccess($mediaItem->public_access, 'item'),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $itemId = (int) DB::getPdo()->lastInsertId();
        }

        $this->itemKeys[$key] = true;
        $this->stats['items']['created']++;

        // 6. Fichiers liés -> sous-items attachés à l'item principal
        $relatedFiles = $this->mediaTable('media_item_related')
            ->select('id', 'title', 'filename', 'mime_type')
            ->where('item_id', $mediaItem->id)
            ->whereNotNull('filename')
            ->where('filename', '!=', '')
            ->get();

        foreach ($relatedFiles as $related) {
            $relatedExtension = strtolower(pathinfo($related->filename, PATHINFO_EXTENSION));
            $relatedExtension = $relatedExtension !== '' ? $relatedExtension : null;
            $relatedCode = pathinfo($related->filename, PATHINFO_FILENAME);
            $relatedKey = $this->itemKey($relatedCode, $relatedExtension);

            if (isset($this->itemKeys[$relatedKey])) {
                $this->stats['items']['skipped']++;

                continue;
            }

            if ($this->dryRun) {
                $this->stats['items']['subs']++;

                continue;
            }

            $relatedNow = now();
            DB::table('items')->insert([
                'itemable_type' => Item::class,
                'itemable_id' => $itemId,
                'item_type_id' => null,
                'code' => $relatedCode,
                'code_prefix' => $relatedCode,
                'code_suffix' => null,
                'is_sub' => true,
                'title' => $related->title ?: null,
                'file_path' => $related->filename,
                'file_name' => basename($related->filename),
                'file_extension' => $relatedExtension,
                'file_type' => (! empty($related->mime_type)) ? $related->mime_type : $this->getMimeTypeFromExtension($relatedExtension),
                'file_size' => 0,
                'duration' => 0,
                'upload_date' => $relatedNow->toDateString(),
                'uploaded_by' => $userId,
                'created_by' => $userId,
                'public_access' => config('mms.access.defaults.media'),
                'created_at' => $relatedNow,
                'updated_at' => $relatedNow,
            ]);

            $this->itemKeys[$relatedKey] = true;
            $this->stats['items']['subs']++;
        }
    }

    /**
     * Étape 5 : fichiers liés aux fonds / corpus / collections (is_sub) depuis media_*_related.
     */
    private function importRelated(int $userId): void
    {
        $this->info('--- Étape 5/5 : Fichiers liés (media_fonds_related, media_corpus_related, media_collection_related) ---');

        $configs = [
            ['table' => 'media_fonds_related', 'media' => 'media_fonds', 'parentByCode' => $this->fondsByCode, 'codeById' => $this->mediaFondsCodeById, 'model' => Fond::class, 'label' => 'fonds'],
            ['table' => 'media_corpus_related', 'media' => 'media_corpus', 'parentByCode' => $this->corpusesByCode, 'codeById' => $this->mediaCorpusCodeById, 'model' => Corpus::class, 'label' => 'corpus'],
            ['table' => 'media_collection_related', 'media' => 'media_collections', 'parentByCode' => $this->collectionsByCode, 'codeById' => $this->mediaCollectionsCodeById, 'model' => Collection::class, 'label' => 'collections'],
        ];

        foreach ($configs as $config) {
            $this->importRelatedTable($config, $userId);
        }
    }

    private function importRelatedTable(array $config, int $userId): void
    {
        $table = $config['table'];
        $step = 'related';

        $rows = $this->sourceQuery($table)->get();
        $bar = $this->output->createProgressBar(count($rows));

        foreach ($rows as $row) {
            $bar->advance();

            try {
                if (empty($row->filename)) {
                    $this->stats[$step]['skipped']++;

                    continue;
                }

                // Parent applicatif via resource_id -> code media
                $mediaParentCode = $config['codeById'][$row->resource_id] ?? null;
                $parentId = $mediaParentCode !== null ? ($config['parentByCode'][$mediaParentCode] ?? null) : null;

                if ($parentId === null) {
                    $this->stats[$step]['skipped']++;
                    $this->logError("{$table} ID {$row->id} : parent introuvable (resource_id {$row->resource_id}, code ".($mediaParentCode ?? 'inconnu').')');

                    continue;
                }

                $extension = strtolower(pathinfo($row->filename, PATHINFO_EXTENSION));
                $extension = $extension !== '' ? $extension : null;
                $itemCode = pathinfo($row->filename, PATHINFO_FILENAME);
                $key = $this->itemKey($itemCode, $extension);

                if (isset($this->itemKeys[$key])) {
                    $this->stats[$step]['skipped']++;

                    continue;
                }

                if ($this->dryRun) {
                    $this->stats[$step]['subs']++;

                    continue;
                }

                $now = now();
                DB::table('items')->insert([
                    'itemable_type' => $config['model'],
                    'itemable_id' => $parentId,
                    'item_type_id' => null,
                    'code' => $itemCode,
                    'code_prefix' => $itemCode,
                    'code_suffix' => null,
                    'is_sub' => true,
                    'title' => $row->title ?: null,
                    'file_path' => $row->filename,
                    'file_name' => basename($row->filename),
                    'file_extension' => $extension,
                    'file_type' => (! empty($row->mime_type)) ? $row->mime_type : $this->getMimeTypeFromExtension($extension),
                    'file_size' => 0,
                    'duration' => 0,
                    'upload_date' => $now->toDateString(),
                    'uploaded_by' => $userId,
                    'created_by' => $userId,
                    'public_access' => config('mms.access.defaults.media'),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $this->itemKeys[$key] = true;
                $this->stats[$step]['subs']++;
            } catch (\Throwable $e) {
                $this->stats[$step]['errors']++;
                $this->logError("{$table} ID {$row->id} : ".$e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine();
    }

    /**
     * Table source Telemeta via la connexion dédiée (base mms_telemeta).
     */
    private function mediaTable(string $table)
    {
        return DB::connection('telemeta')->table($table);
    }

    /**
     * Requête source Telemeta avec --limit éventuel.
     */
    private function sourceQuery(string $table)
    {
        $query = $this->mediaTable($table);

        if ($this->limit > 0) {
            $query->limit($this->limit);
        }

        return $query;
    }

    /**
     * Mapper l'accès public Telemeta vers les valeurs applicatives.
     * '' -> défaut de la config, mixedmetadata -> mixed, none/full/metadata/mixed -> tel quel.
     */
    private function mapPublicAccess(?string $value, string $defaultKey): string
    {
        $value = trim((string) $value);

        return match ($value) {
            '' => (string) config("mms.access.defaults.{$defaultKey}"),
            'mixedmetadata' => 'mixed',
            'full', 'metadata', 'mixed', 'none' => $value,
            default => (string) config("mms.access.defaults.{$defaultKey}"),
        };
    }

    private function logError(string $message): void
    {
        if (count($this->errorMessages) < 50) {
            $this->errorMessages[] = $message;
        }
    }

    private function displaySummary(): void
    {
        $rows = [];
        foreach ($this->stats as $step => $stats) {
            $rows[] = [
                $step,
                $stats['created'],
                $stats['skipped'],
                $stats['subs'],
                $stats['links'],
                $stats['errors'],
            ];
        }

        $this->info('=== Résumé de l\'import ===');
        $this->table(
            ['Étape', 'Créés', 'Déjà présents (ignorés)', 'Sous-items', 'Liens pivots', 'Erreurs'],
            $rows
        );

        if ($this->errorMessages !== []) {
            $this->newLine();
            $totalErrors = array_sum(array_column($this->stats, 'errors'));
            $this->error('Détails des erreurs ('.$totalErrors.' au total) :');
            foreach (array_slice($this->errorMessages, 0, 20) as $message) {
                $this->line('  - '.$message);
            }

            if (count($this->errorMessages) > 20) {
                $this->line('  ... et '.(count($this->errorMessages) - 20).' autres');
            }
        }
    }

    /**
     * Obtenir le type MIME depuis l'extension (liste reprise des autres commandes d'import).
     */
    private function getMimeTypeFromExtension(?string $extension): string
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

        return $mimeTypes[$extension ?? ''] ?? 'application/octet-stream';
    }
}
