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
            $resolver = app(\App\Services\MediaVariationPathResolver::class);

            // 1. Get Settings
            $settingsPath = 'mms_settings.json';
            $settings = [];
            if (Storage::disk('local')->exists($settingsPath)) {
                $settings = json_decode(Storage::disk('local')->get($settingsPath), true);
            }
            // Use configured path or default to system command
            $audiowaveformPath = $settings['audiowaveform_path'] ?? 'audiowaveform';
            $ffmpegPath = $settings['ffmpeg_path'] ?? 'ffmpeg';
            $diffusionDisk = $settings['diffusion_disk'] ?? 'diffusion_medias';

            // Encoding Settings
            $pixelsPerSecond = $settings['waveform_pixels_per_second'] ?? config('mms.encoding.waveform.pixels_per_second.default', 20);
            $bits = $settings['waveform_bits'] ?? config('mms.encoding.waveform.bits.default', 8);

            // 2. Paths
            $inputPath = Storage::disk('original_medias')->path($this->item->file_path);

            if (! file_exists($inputPath)) {
                throw new \Exception("Source file not found at: {$inputPath}");
            }

            $outputDir = $resolver->variationDir($this->item, 'waveform');
            $fileName = $this->item->code.'.json';

            // Cleanup existing waveform file before generation
            Storage::disk($diffusionDisk)->delete($outputDir.'/'.$fileName);
            Storage::disk($diffusionDisk)->makeDirectory($outputDir);
            $outputFileAbsolute = Storage::disk($diffusionDisk)->path($outputDir.'/'.$fileName);

            // 3. Command
            if ($this->item->isVideo()) {
                // For video, we extract audio using ffmpeg and pipe it to audiowaveform
                $result = Process::timeout($this->timeout)->pipe(function ($pipe) use ($ffmpegPath, $inputPath, $audiowaveformPath, $outputFileAbsolute, $pixelsPerSecond, $bits) {
                    $pipe->command([
                        $ffmpegPath,
                        '-i', $inputPath,
                        '-vn', // No video
                        '-ac', '1', // Mono is enough for waveform
                        '-f', 'wav',
                        '-',
                    ]);
                    $pipe->command([
                        $audiowaveformPath,
                        '--input-format', 'wav',
                        '-o', $outputFileAbsolute,
                        '--pixels-per-second', (string) $pixelsPerSecond,
                        '--bits', (string) $bits,
                    ]);
                });
                $commandLog = "{$ffmpegPath} -i {$inputPath} -vn -ac 1 -f wav - | {$audiowaveformPath} --input-format wav -o {$outputFileAbsolute} --pixels-per-second {$pixelsPerSecond} --bits {$bits}";
            } else {
                // For audio, audiowaveform can handle it directly (most formats)
                $command = [
                    $audiowaveformPath,
                    '-i', $inputPath,
                    '-o', $outputFileAbsolute,
                    '--pixels-per-second', (string) $pixelsPerSecond,
                    '--bits', (string) $bits,
                ];
                $result = Process::timeout($this->timeout)->run($command);
                $commandLog = implode(' ', $command);
            }

            // 4. Execute (result already obtained above)
            if ($result->failed()) {
                throw new \Exception('Audiowaveform failed: '.$result->errorOutput());
            }

            $fileSize = Storage::disk($diffusionDisk)->size($outputDir.'/'.$fileName);

            // 5. Create or Update Variation
            MediaVariation::updateOrCreate(
                [
                    'item_id' => $this->item->id,
                    'profile_name' => 'waveform_json',
                ],
                [
                    'type' => MediaVariationType::DATA, // Data for JSON
                    'disk' => $diffusionDisk,
                    'file_path' => $resolver->variationPath($this->item, 'waveform', $fileName),
                    'file_size' => $fileSize,
                    'mime_type' => 'application/json',
                    'is_streaming' => false,
                    'status' => MediaVariationStatus::READY,
                    'generation_params' => ['command' => $commandLog],
                ]
            );

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
