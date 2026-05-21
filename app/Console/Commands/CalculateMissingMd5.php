<?php

namespace App\Console\Commands;

use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CalculateMissingMd5 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'items:calculate-md5 {--force : Recalculer le MD5 même s\'il existe déjà}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calcule et met à jour le hash MD5 pour les items qui n\'en ont pas (et dont le fichier existe).';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $force = $this->option('force');

        $query = Item::query()->whereNotNull('file_path')->where('file_path', '!=', '');

        if (! $force) {
            $query->where(function ($q) {
                $q->whereNull('md5')->orWhere('md5', '');
            });
        }

        $items = $query->get();
        $total = $items->count();

        if ($total === 0) {
            $this->info('Aucun item à traiter.');

            return;
        }

        $this->info("{$total} items à traiter...");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $updated = 0;
        $missingFiles = 0;

        foreach ($items as $item) {
            $fullPath = null;

            // On vérifie d'abord si le chemin peut être résolu sur le disque original_medias
            if (Storage::disk('original_medias')->exists($item->file_path)) {
                $fullPath = Storage::disk('original_medias')->path($item->file_path);
            }
            // Fallback (ex: chemins absolus ou disque par défaut)
            elseif (file_exists($item->file_path)) {
                $fullPath = $item->file_path;
            }

            if ($fullPath && file_exists($fullPath)) {
                $item->md5 = md5_file($fullPath);

                // Sauvegarde silencieuse (évite de déclencher d'autres observers non liés)
                Item::withoutEvents(function () use ($item) {
                    $item->save();
                });

                $updated++;
            } else {
                $missingFiles++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('Terminé !');
        $this->comment("- {$updated} items mis à jour avec le hash MD5.");

        if ($missingFiles > 0) {
            $this->error("- {$missingFiles} items ignorés car le fichier physique est introuvable.");
        }
    }
}
