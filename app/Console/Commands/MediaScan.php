<?php

namespace App\Console\Commands;

use App\Services\MediaScanner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Finder\Finder;

class MediaScan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:scan {path? : Optional specific path to scan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan the configured media folder for files and match them with items.';

    /**
     * Execute the console command.
     */
    public function handle(MediaScanner $scanner)
    {
        $scanPath = $this->argument('path');

        if (!$scanPath) {
            // Load from settings
            $settingsPath = 'mms_settings.json';
            if (Storage::disk('local')->exists($settingsPath)) {
                $settings = json_decode(Storage::disk('local')->get($settingsPath), true);
                $scanPath = $settings['scan_path'] ?? null;
            }
        }

        if (!$scanPath) {
            $this->error('No scan path configured. Please configure it in the Media Settings or provide it as an argument.');
            return 1;
        }

        if (!is_dir($scanPath)) {
            $this->error("The directory [{$scanPath}] does not exist.");
            return 1;
        }

        $this->info("Scanning directory: {$scanPath}");

        // Initialize Finder
        $finder = new Finder();
        $finder->files()->in($scanPath)->ignoreDotFiles(true);
        // Add more filters if needed (e.g. valid extensions)

        if (!$finder->hasResults()) {
            $this->info("No files found.");
            return 0;
        }

        $totalCount = $finder->count(); // This might be slow if huge, but Finder calculates it reasonable fast usually.
        // If count is too slow, we can skip progress bar total.

        $this->output->progressStart($totalCount);

        $batchSize = 100;
        $batch = [];

        foreach ($finder as $file) {
            $batch[] = $file;

            if (count($batch) >= $batchSize) {
                $scanner->scanBatch($batch, 'local', $scanPath);
                $this->output->progressAdvance(count($batch));
                $batch = [];
            }
        }

        // Process remaining
        if (count($batch) > 0) {
            $scanner->scanBatch($batch, 'local', $scanPath);
            $this->output->progressAdvance(count($batch));
        }

        $this->output->progressFinish();
        $this->info("Scan completed.");

        return 0;
    }
}
