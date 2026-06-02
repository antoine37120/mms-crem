<?php

namespace App\Jobs;

use App\Models\Item;
use App\Services\MediaProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ComputeItemMetadata implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 min max pour le md5 des très gros fichiers

    public function __construct(
        public Item $item
    ) {}

    public function handle(): void
    {
        $disk = Storage::disk('original_medias');

        if (! $disk->exists($this->item->file_path)) {
            $this->fail(new \Exception("Fichier introuvable : {$this->item->file_path}"));

            return;
        }

        $fullPath = $disk->path($this->item->file_path);

        // Calculer les métadonnées manquantes
        $needsSave = false;

        if (is_null($this->item->file_type)) {
            $this->item->file_type = mime_content_type($fullPath);
            $needsSave = true;
        }

        if (is_null($this->item->file_size)) {
            $this->item->file_size = filesize($fullPath);
            $needsSave = true;
        }

        if (is_null($this->item->md5)) {
            $this->item->md5 = md5_file($fullPath);
            $needsSave = true;
        }

        if ($needsSave) {
            $this->item->saveQuietly();
        }

        // Si c'est un fichier audio/vidéo, lancer la génération des variations
        if ($this->item->isAudio() || $this->item->isVideo()) {
            app(MediaProcessor::class)->processItem($this->item);
        }
    }
}
