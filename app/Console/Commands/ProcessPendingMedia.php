<?php

namespace App\Console\Commands;

use App\Enums\ItemProcessingStatus;
use App\Enums\ItemProcessingType;
use App\Enums\MediaVariationStatus;
use App\Models\Item;
use App\Models\ItemProcessingState;
use App\Models\MediaVariation;
use App\Services\MediaProcessor;
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
        $notAudioVideoCount = 0;

        $query->chunkById(100, function ($items) use ($bar, $force, &$successCount, &$missingFileCount, &$notAudioVideoCount) {
            foreach ($items as $item) {
                $disk = Storage::disk('original_medias');

                if (! $disk->exists($item->file_path)) {
                    $this->warn("\nFichier introuvable pour l'item #{$item->id} : {$item->file_path}");
                    $missingFileCount++;
                    $bar->advance();

                    continue;
                }

                $fullPath = $disk->path($item->file_path);

                // Mise à jour des métadonnées si NULL
                $needsSave = false;
                if (is_null($item->file_type)) {
                    $item->file_type = mime_content_type($fullPath);
                    $needsSave = true;
                }

                if (is_null($item->file_size)) {
                    $item->file_size = filesize($fullPath);
                    $needsSave = true;
                }

                if (is_null($item->md5)) {
                    $item->md5 = md5_file($fullPath);
                    $needsSave = true;
                }

                if ($needsSave) {
                    $item->saveQuietly();
                }

                // Limiter aux fichiers audio/vidéo uniquement
                if (! $item->isAudio() && ! $item->isVideo()) {
                    $notAudioVideoCount++;
                    $bar->advance();

                    continue;
                }

                if ($force) {
                    // Supprimer les MediaVariation existantes (hls_standard, waveform_json)
                    $item->mediaVariations()
                        ->whereIn('profile_name', ['hls_standard', 'waveform_json'])
                        ->delete();

                    // Réinitialiser les ItemProcessingState à PENDING
                    $item->processingStates()
                        ->whereIn('process_type', [ItemProcessingType::DIFFUSION, ItemProcessingType::WAVEFORM])
                        ->update([
                            'status' => ItemProcessingStatus::PENDING,
                            'message' => 'Re-traitement forcé via commande artisan',
                            'started_at' => null,
                            'finished_at' => null,
                        ]);
                }

                // Dispatcher les jobs
                app(MediaProcessor::class)->processItem($item);
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
                ['Items traités avec succès', $successCount],
                ['Fichiers physiques manquants', $missingFileCount],
                ['Types non supportés (ni audio ni vidéo)', $notAudioVideoCount],
                ['Items déjà traités (sautés)', $alreadyTreatedCount],
            ]
        );

        return 0;
    }
}
