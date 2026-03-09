<?php

namespace App\Jobs;

use App\Enums\ItemProcessingStatus;
use App\Enums\ItemProcessingType;
use App\Enums\MediaVariationStatus;
use App\Enums\MediaVariationType;
use App\Models\Item;
use App\Models\MediaVariation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class GenerateAudiowaveform implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(
        public Item $item
    ) {}

    public function handle(): void
    {
        $this->item->updateProcessingState(
            ItemProcessingType::WAVEFORM,
            ItemProcessingStatus::PROCESSING,
            'Generating waveform...'
        );

        try {
            // 1. Get Settings
            $settingsPath = 'mms_settings.json';
            $settings = [];
            if (Storage::disk('local')->exists($settingsPath)) {
                $settings = json_decode(Storage::disk('local')->get($settingsPath), true);
            }
            // Use configured path or default to system command
            $audiowaveformPath = $settings['audiowaveform_path'] ?? 'audiowaveform';
            $diffusionDisk = $settings['diffusion_disk'] ?? 'diffusion_medias';

            // 2. Paths
            $inputPath = Storage::disk('original_medias')->path($this->item->file_path);

            if (!file_exists($inputPath)) {
                 throw new \Exception("Source file not found at: {$inputPath}");
            }

            $outputDir = 'items/' . $this->item->code . '/waveform';
            $fileName = $this->item->code . '.json';

            Storage::disk($diffusionDisk)->makeDirectory($outputDir);
            $outputFileAbsolute = Storage::disk($diffusionDisk)->path($outputDir . '/' . $fileName);

            // 3. Command
            // audiowaveform -i input.mp3 -o output.json
            $command = [
                $audiowaveformPath,
                '-i', $inputPath,
                '-o', $outputFileAbsolute,
                '--pixels-per-second', '20',
                '--bits', '8'
            ];

            // 4. Execute
            $result = Process::run($command);

            if ($result->failed()) {
                throw new \Exception("Audiowaveform failed: " . $result->errorOutput());
            }

            $fileSize = Storage::disk($diffusionDisk)->size($outputDir . '/' . $fileName);

            // 5. Create Variation
            MediaVariation::create([
                'item_id' => $this->item->id,
                'profile_name' => 'waveform_json',
                'type' => MediaVariationType::DATA, // Data for JSON
                'disk' => $diffusionDisk,
                'file_path' => $outputDir . '/' . $fileName,
                'file_size' => $fileSize,
                'mime_type' => 'application/json',
                'is_streaming' => false,
                'status' => MediaVariationStatus::READY,
                'generation_params' => ['command' => implode(' ', $command)],
            ]);

            $this->item->updateProcessingState(
                ItemProcessingType::WAVEFORM,
                ItemProcessingStatus::COMPLETED,
                'Waveform generated successfully.'
            );

        } catch (\Exception $e) {
            $this->item->updateProcessingState(
                ItemProcessingType::WAVEFORM,
                ItemProcessingStatus::FAILED,
                $e->getMessage()
            );
            $this->fail($e);
        }
    }
}
