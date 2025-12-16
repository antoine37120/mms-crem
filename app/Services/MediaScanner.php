<?php

namespace App\Services;

use App\Enums\ScannedFileStatus;
use App\Models\Item;
use App\Models\ScannedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use SplFileInfo;

class MediaScanner
{
    /**
     * Scan a single file and update/create database record.
     */
    public function scanFile(SplFileInfo $file, string $disk = 'local', ?string $rootScanPath = null): ScannedFile
    {
        $filePath = $file->getPathname();
        $fileName = $file->getBasename(); // e.g. "video.mp4"

        // Find existing or new
        $scannedFile = ScannedFile::firstOrNew([
            'file_path' => $filePath,
        ]);

        $scannedFile->disk = $disk;
        $scannedFile->file_name = $fileName;
        $scannedFile->size = $file->getSize();
        $scannedFile->last_scanned_at = now();

        // If new, set default status
        if (!$scannedFile->exists) {
            $scannedFile->status = ScannedFileStatus::ORPHAN;
        }

        $scannedFile->save();

        // Attempt match if orphan
        if ($scannedFile->status === ScannedFileStatus::ORPHAN) {
            $this->matchItem($scannedFile, $rootScanPath);
        }

        return $scannedFile;
    }

    /**
     * Attempt to match a ScannedFile to an Item by code.
     */
    public function matchItem(ScannedFile $scannedFile, ?string $rootScanPath = null): bool
    {
        // Logic: Filename without extension === Item Code
        $code = pathinfo($scannedFile->file_name, PATHINFO_FILENAME);

        // Find item
        $item = Item::where('code', $code)->first();

        if ($item) {
            $scannedFile->item_id = $item->id;
            $scannedFile->status = ScannedFileStatus::ASSOCIATED;
            $scannedFile->save();

            // Auto-link Item file_path if empty
            if (empty($item->file_path) && $rootScanPath) {
                // Calculate relative path
                $relativePath = Str::after($scannedFile->file_path, $rootScanPath . DIRECTORY_SEPARATOR);
                // Ensure no leading slash if Str::after leaves one (depends on separator presence)
                $relativePath = ltrim($relativePath, DIRECTORY_SEPARATOR);

                $item->file_path = $relativePath;
                $item->save(); // Triggers Observer -> Processing
            }

            return true;
        }

        return false;
    }

    /**
     * Optimized batch scanning.
     *
     * @param SplFileInfo[] $files
     */
    public function scanBatch(array $files, string $disk = 'local', ?string $rootScanPath = null): void
    {
        if (empty($files)) {
            return;
        }

        // 1. Prepare data for processing
        $paths = [];
        $fileMap = []; // path -> SplFileInfo

        foreach ($files as $file) {
            $path = $file->getPathname();
            $paths[] = $path;
            $fileMap[$path] = $file;
        }

        // 2. Load existing ScannedFiles to minimize queries
        $existingRecords = ScannedFile::whereIn('file_path', $paths)->get()->keyBy('file_path');

        // 3. Prepare codes for bulk Item lookup
        $codesToLookup = [];
        foreach ($files as $file) {
            $codesToLookup[] = pathinfo($file->getBasename(), PATHINFO_FILENAME);
        }
        $codesToLookup = array_unique($codesToLookup);

        // 4. Load matching Items
        $items = Item::whereIn('code', $codesToLookup)->get()->keyBy('code');

        // 5. Upsert / Process records
        foreach ($files as $file) {
            $path = $file->getPathname();
            $fileName = $file->getBasename();
            $code = pathinfo($fileName, PATHINFO_FILENAME);

            $record = $existingRecords->get($path);

            if (!$record) {
                $record = new ScannedFile();
                $record->file_path = $path;
                $record->disk = $disk;
                $record->status = ScannedFileStatus::ORPHAN;
            }

            $record->file_name = $fileName;
            $record->size = $file->getSize();
            $record->last_scanned_at = now();

            if ($record->status === ScannedFileStatus::ORPHAN) {
                if ($item = $items->get($code)) {
                    $record->item_id = $item->id;
                    $record->status = ScannedFileStatus::ASSOCIATED;

                    // Auto-link Item file_path if empty
                    if (empty($item->file_path) && $rootScanPath) {
                        $relativePath = ltrim(Str::after($path, $rootScanPath . DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR);
                        $item->file_path = $relativePath;
                        $item->save(); // Triggers Observer -> Processing
                    }
                }
            }

            $record->save();
        }
    }
}
