<?php

namespace App\Services;

use App\Jobs\GenerateAudiowaveform;
use App\Jobs\GenerateDiffusionMedia;
use App\Models\Item;

class MediaProcessor
{
    /**
     * Dispatch processing jobs for an item.
     */
    public function processItem(Item $item): void
    {
        // Check if item has a file
        if (!$item->file_path) {
            return;
        }

        // Determine if it's Audio or Video
        if ($item->isVideo() || $item->isAudio()) {
            // Dispatch Diffusion generation
            GenerateDiffusionMedia::dispatch($item)
                ->onQueue('media_processing');

            // Dispatch Waveform generation (Audio only? Or Video too if it has audio track?)
            // Usually waveform is useful for Audio visualizer. For Video, it might be used too.
            // Let's assume Audio and Video both get a waveform (ffmpeg can extract audio from video).
            GenerateAudiowaveform::dispatch($item)
                ->onQueue('media_processing');
        }
    }
}
