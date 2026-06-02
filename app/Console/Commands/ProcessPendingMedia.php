<?php

namespace App\Console\Commands;

use App\Enums\MediaVariationStatus;
use App\Jobs\ComputeItemMetadata;
use App\Models\Item;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ProcessPendingMedia extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'items:process-pending-media {--force : Forcer le re-encodage même si des variations diffusion/waveform existent déjà}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rattrape les items dont le fichier est maintenant disponible sur le disque pour lancer la génération diffusion et waveform.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $force = $this->option('force');

        $query = Item::query()
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '');

        if (! $force) {
            // Filtrer les items qui n'ont PAS de MediaVariation hls_standard prête ET pas de MediaVariation waveform_json
            $query->whereDoesntHave('mediaVariations', function ($q) {
                $q->where('profile_name', 'hls_standard')
                    ->where('status', MediaVariationStatus::READY);
            })->whereDoesntHave('mediaVariations', function ($q) {
                $q->where('profile_name', 'waveform_json');
            });
        }

        $totalCount = $query->count();

        // Calculer le nombre d'items déjà traités pour le rapport final
        $totalPotentialQuery = Item::query()
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '');
        $alreadyTreatedCount = $totalPotentialQuery->count() - $totalCount;

        if ($totalCount === 0) {
            $this->info('Aucun item à traiter.');

            return 0;
        }

        $this->info("{$totalCount} items à traiter...");
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $successCount = 0;
        $missingFileCount = 0;

        $query->chunkById(100, function ($items) use ($bar, $force, &$successCount, &$missingFileCount) {
            foreach ($items as $item) {
                $disk = Storage::disk('original_medias');

                if (! $disk->exists($item->file_path)) {
                    $missingFileCount++;
                    $bar->advance();

                    continue;
                }

                if ($force) {
                    // Supprimer les MediaVariation existantes (hls_standard, waveform_json)
                    $item->mediaVariations()
                        ->whereIn('profile_name', ['hls_standard', 'waveform_json'])
                        ->delete();

                    // Réinitialiser les ItemProcessingState
                    $item->processingStates()
                        ->whereIn('process_type', [\App\Enums\ItemProcessingType::DIFFUSION, \App\Enums\ItemProcessingType::WAVEFORM])
                        ->update([
                            'status' => \App\Enums\ItemProcessingStatus::PENDING,
                            'message' => 'Re-traitement forcé via commande artisan',
                            'started_at' => null,
                            'finished_at' => null,
                        ]);
                }

                // Dispatcher le job de calcul des métadonnées (file_type, file_size, md5)
                // Ce job enchaînera automatiquement sur la génération diffusion/waveform si nécessaire
                ComputeItemMetadata::dispatch($item)
                    ->onQueue('media_processing');

                $successCount++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info('Traitement terminé !');
        $this->table(
            ['Catégorie', 'Nombre'],
            [
                ['Items dispatchés avec succès', $successCount],
                ['Fichiers physiques manquants', $missingFileCount],
                ['Items déjà traités (sautés)', $alreadyTreatedCount],
            ]
        );

        return 0;
    }
}
