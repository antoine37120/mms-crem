<?php

namespace App\Services\Admin;

use App\Enums\ScannedFileStatus;
use App\Models\Item;
use App\Models\ScannedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SplFileInfo;
use Symfony\Component\Finder\Finder;

class ScannedFileAdminService
{
    protected string $settingsPath;

    public function __construct()
    {
        $this->settingsPath = storage_path('app/private/mms_settings.json');
    }

    /**
     * Get settings from mms_settings.json
     */
    protected function getSettings(): array
    {
        if (! File::exists($this->settingsPath)) {
            return [];
        }

        return json_decode(File::get($this->settingsPath), true) ?? [];
    }

    /**
     * Run a full scan of the directory.
     */
    public function runScan(?string $scanPath = null): array
    {
        $settings = $this->getSettings();
        $rootScanPath = $scanPath ?? $settings['scan_path'] ?? config('mms.medias_path');

        if (! $rootScanPath || ! File::isDirectory($rootScanPath)) {
            return ['found' => 0, 'matched' => 0, 'orphaned' => 0];
        }

        $finder = new Finder;
        $finder->files()->in($rootScanPath);

        $stats = ['found' => 0, 'matched' => 0, 'orphaned' => 0];

        foreach ($finder as $file) {
            $stats['found']++;
            $matched = $this->processFile($file, $rootScanPath);
            if ($matched) {
                $stats['matched']++;
            } else {
                $stats['orphaned']++;
            }
        }

        return $stats;
    }

    /**
     * Process a single file found during scan.
     */
    protected function processFile(SplFileInfo $file, string $rootScanPath): bool
    {
        $absolutePath = $file->getRealPath();
        $fileName = $file->getBasename();

        $scannedFile = ScannedFile::firstOrNew([
            'file_path' => $absolutePath,
        ]);

        $scannedFile->file_name = $fileName;
        $scannedFile->size = $file->getSize();
        $scannedFile->last_scanned_at = now();
        $scannedFile->disk = 'original_medias'; // Les fichiers sont dans MMS_MEDIAS_PATH, donc disque original_medias

        if (! $scannedFile->exists) {
            $scannedFile->status = ScannedFileStatus::ORPHAN;
        }

        $matched = $this->performMatch($scannedFile, $rootScanPath);

        $scannedFile->save();

        return $matched;
    }

    /**
     * Logic to match a ScannedFile to an Item.
     * Note: Les fichiers ne sont jamais déplacés ou dupliqués.
     */
    protected function performMatch(ScannedFile $scannedFile, string $rootScanPath): bool
    {
        $code = pathinfo($scannedFile->file_name, PATHINFO_FILENAME);
        $item = Item::where('code', $code)->first();

        if ($item) {
            $scannedFile->item_id = $item->id;
            $scannedFile->status = ScannedFileStatus::ASSOCIATED;

            // Normalisation des chemins pour le calcul du chemin relatif
            $realRoot = realpath($rootScanPath);
            $realFile = realpath($scannedFile->file_path);

            // Calculate relative path: remove scan_path from absolute path
            // Example: C:\path\to\medias\file.mp4 -> file.mp4 (if scan_path is C:\path\to\medias)
            $relativePath = ltrim(Str::after($realFile, $realRoot), DIRECTORY_SEPARATOR);

            $needsSave = false;
            if (empty($item->file_path)) {
                $item->file_path = $relativePath;
                $needsSave = true;
            }

            if (empty($item->md5) && File::exists($scannedFile->file_path)) {
                $item->md5 = md5_file($scannedFile->file_path);
                $needsSave = true;
            }

            if ($needsSave) {
                // IMPORTANT: saveQuietly to avoid triggering ItemObserver
                $item->saveQuietly();
            }

            return true;
        }

        return false;
    }

    /**
     * Try to match an existing ScannedFile record to an Item.
     */
    public function tryMatch(ScannedFile $record): bool
    {
        $settings = $this->getSettings();
        $rootScanPath = $settings['scan_path'] ?? config('mms.medias_path');

        if (! $rootScanPath) {
            return false;
        }

        $matched = $this->performMatch($record, $rootScanPath);
        if ($matched) {
            $record->save();
        }

        return $matched;
    }

    /**
     * Rescan a single ScannedFile record.
     */
    public function rescan(ScannedFile $record): bool
    {
        if (! File::exists($record->file_path)) {
            return false;
        }

        $record->size = File::size($record->file_path);
        $record->last_scanned_at = now();

        if ($record->status === ScannedFileStatus::ORPHAN) {
            $this->tryMatch($record);
        } elseif ($record->status === ScannedFileStatus::ASSOCIATED && $record->item_id) {
            $item = $record->item;
            if ($item && empty($item->md5)) {
                $item->md5 = md5_file($record->file_path);
                $item->saveQuietly();
            }
        }

        return $record->save();
    }

    /**
     * Launch processing of pending media.
     */
    public function processPending(bool $force = false): void
    {
        Artisan::queue('items:process-pending-media', ['--force' => $force]);
    }
}
