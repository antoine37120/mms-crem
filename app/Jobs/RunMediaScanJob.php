<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Admin\ScannedFileAdminService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunMediaScanJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 3600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ?string $scanPath = null,
        public ?User $user = null
    ) {
        $this->onQueue('media_processing');
    }

    /**
     * Execute the job.
     */
    public function handle(ScannedFileAdminService $service): void
    {
        Log::info('Début du scan média asynchrone...');

        $stats = $service->runScan($this->scanPath);

        Log::info('Scan média terminé', $stats);

        // Dispatch automatiquement items:process-pending-media pour traiter les items nouvellement associés
        Artisan::queue('items:process-pending-media');

        if ($this->user) {
            $notification = Notification::make()
                ->title('Scan média terminé')
                ->body(sprintf(
                    'Le scan est terminé. %d fichiers trouvés, %d associés, %d orphelins. Le traitement des médias a été lancé.',
                    $stats['found'],
                    $stats['matched'],
                    $stats['orphaned']
                ))
                ->success();

            // On tente l'envoi en base de données seulement si la table existe
            try {
                $notification->sendToDatabase($this->user);
            } catch (\Exception $e) {
                Log::warning("Notification de scan média non envoyée à l'utilisateur : ".$e->getMessage());
            }
        }
    }
}
