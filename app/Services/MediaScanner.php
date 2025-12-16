<?php

namespace App\Services;

use App\Enums\ScannedFileStatus;
use App\Models\Item;
use App\Models\ScannedFile;
use Illuminate\Support\Collection;
use SplFileInfo;

class MediaScanner
{
    /**
     * Scan a single file and update/create database record.
     */
    public function scanFile(SplFileInfo $file, string $disk = 'local'): ScannedFile
    {
        $filePath = $file->getPathname();
        $fileName = $file->getBasename(); // e.g. "video.mp4"
        $nameWithoutExtension = pathinfo($fileName, PATHINFO_FILENAME); // e.g. "video"

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
            $this->matchItem($scannedFile);
        }

        return $scannedFile;
    }

    /**
     * Attempt to match a ScannedFile to an Item by code.
     */
    public function matchItem(ScannedFile $scannedFile): bool
    {
        // Logic: Filename without extension === Item Code
        $code = pathinfo($scannedFile->file_name, PATHINFO_FILENAME);

        // Find item
        $item = Item::where('code', $code)->first();

        if ($item) {
            $scannedFile->item_id = $item->id;
            $scannedFile->status = ScannedFileStatus::ASSOCIATED;
            $scannedFile->save();
            return true;
        }

        return false;
    }

    /**
     * Optimized batch scanning.
     *
     * @param SplFileInfo[] $files
     */
    public function scanBatch(array $files, string $disk = 'local'): void
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
        // Note: 'upsert' works but handling 'status' logic (only if new) is tricky with upsert if we want to preserve existing status unless we force re-eval.
        // For accurate status logic, we might iterate. Since we pre-loaded data, it's fast.

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

            // If already associated, we generally keep it? Or verify?
            // Spec says "Explore/Scan".
            // If it's ORPHAN, try to match.
            // If it's ASSOCIATED, check if item still exists?
            // For now, let's just retry matching if ORPHAN, or if we want to ensure consistency.
            // Let's stick to: If ORPHAN, try match.

            if ($record->status === ScannedFileStatus::ORPHAN) {
                if ($item = $items->get($code)) {
                    $record->item_id = $item->id;
                    $record->status = ScannedFileStatus::ASSOCIATED;
                }
            }

            $record->save();
        }
    }
}
