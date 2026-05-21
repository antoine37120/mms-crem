<?php

namespace App\Console\Commands;

use App\Models\DocumentationPage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportDocumentation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docs:import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import documentation pages from markdown files in resources/docs';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $docsPath = resource_path('docs');

        if (! File::exists($docsPath)) {
            $this->error("The directory {$docsPath} does not exist.");

            return;
        }

        $files = File::files($docsPath);
        $importedCount = 0;

        foreach ($files as $file) {
            if ($file->getExtension() === 'md') {
                $filename = $file->getFilenameWithoutExtension();

                // Parse order and title from filename (e.g. 05-administration)
                $order = 0;
                $titleStr = $filename;

                if (preg_match('/^(\d+)-(.*)$/', $filename, $matches)) {
                    $order = (int) $matches[1];
                    $titleStr = str_replace('-', ' ', $matches[2]);
                    $titleStr = ucfirst($titleStr);
                } else {
                    $titleStr = str_replace('-', ' ', $filename);
                    $titleStr = ucfirst($titleStr);
                }

                $content = File::get($file->getPathname());

                // Optionally, try to extract H1 from content for better title
                if (preg_match('/^#\s+(.*?)$/m', $content, $matches)) {
                    $titleStr = trim($matches[1]);
                }

                DocumentationPage::updateOrCreate(
                    ['title' => $titleStr],
                    [
                        'content' => $content,
                        'order' => $order,
                    ]
                );

                $importedCount++;
                $this->info("Imported: {$titleStr}");
            }
        }

        $this->info("Successfully imported {$importedCount} documentation pages.");
    }
}
