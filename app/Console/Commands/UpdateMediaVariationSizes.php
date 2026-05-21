<?php

namespace App\Console\Commands;

use App\Models\MediaVariation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class UpdateMediaVariationSizes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-media-variation-sizes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculates and updates file sizes for existing MediaVariations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $variations = MediaVariation::whereNull('file_size')->get();
        $this->info("Found {$variations->count()} variations with missing file size.");

        $bar = $this->output->createProgressBar($variations->count());
        $bar->start();

        foreach ($variations as $variation) {
            try {
                $disk = Storage::disk($variation->disk);
                $totalSize = 0;

                if ($variation->is_streaming) {
                    // For HLS, we need the whole directory size
                    // The file_path stores the .m3u8 but we want the parent directory
                    $dir = dirname($variation->file_path);
                    if ($disk->exists($dir)) {
                        $files = $disk->allFiles($dir);
                        foreach ($files as $file) {
                            $totalSize += $disk->size($file);
                        }
                    }
                } else {
                    // For single files like waveform
                    if ($disk->exists($variation->file_path)) {
                        $totalSize = $disk->size($variation->file_path);
                    }
                }

                if ($totalSize > 0) {
                    $variation->update(['file_size' => $totalSize]);
                }
            } catch (\Exception $e) {
                $this->error("Failed to process variation {$variation->id}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Done! Media Variation sizes updated.');
    }
}
