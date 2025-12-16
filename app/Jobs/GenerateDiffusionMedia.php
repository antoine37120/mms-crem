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
use Illuminate\Support\Str;

class GenerateDiffusionMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600; // 1 hour max

    public function __construct(
        public Item $item
    ) {}

    public function handle(): void
    {
        $this->item->updateProcessingState(
            ItemProcessingType::DIFFUSION,
            ItemProcessingStatus::PROCESSING,
            'Starting diffusion generation...'
        );

        try {
            // 1. Get Settings
            $settingsPath = 'mms_settings.json';
            $settings = [];
            if (Storage::disk('local')->exists($settingsPath)) {
                $settings = json_decode(Storage::disk('local')->get($settingsPath), true);
            }
            $ffmpegPath = $settings['ffmpeg_path'] ?? 'ffmpeg';
            $diffusionDisk = $settings['diffusion_disk'] ?? 'diffusion_medias';

            // 2. Input/Output Paths
            $inputPath = Storage::disk('original_medias')->path($this->item->file_path);

            // Check if input exists
            if (!file_exists($inputPath)) {
                 throw new \Exception("Source file not found at: {$inputPath}");
            }

            $outputDir = 'items/' . $this->item->code . '/diffusion';
            $outputPathRelative = $outputDir . '/' . $this->item->code; // Base name

            // Ensure output directory exists
            Storage::disk($diffusionDisk)->makeDirectory($outputDir);
            $outputDirAbsolute = Storage::disk($diffusionDisk)->path($outputDir);

            // 3. Command Generation
            if ($this->item->isVideo()) {
                // HLS Generation
                // Simple HLS command: 360p, 720p, 1080p
                // For MVP, single stream HLS
                $playlistName = $this->item->code . '.m3u8';
                $outputFileAbsolute = $outputDirAbsolute . '/' . $playlistName;

                $command = [
                    $ffmpegPath,
                    '-y',
                    '-i', $inputPath,
                    '-c:v', 'libx264',
                    '-preset', 'veryfast',
                    '-g', '48', // Keyframe interval (GOP)
                    '-sc_threshold', '0',
                    '-c:a', 'aac',
                    '-b:a', '128k',
                    '-ac', '2',
                    '-f', 'hls',
                    '-hls_time', '4',
                    '-hls_playlist_type', 'vod',
                    '-hls_segment_filename', $outputDirAbsolute . '/' . $this->item->code . '_%03d.ts',
                    $outputFileAbsolute
                ];

                $variationType = MediaVariationType::VIDEO;
                $mimeType = 'application/x-mpegURL';
                $finalPath = $outputDir . '/' . $playlistName;
                $isStreaming = true;

            } else {
                // Audio - MP3 conversion
                $fileName = $this->item->code . '.mp3';
                $outputFileAbsolute = $outputDirAbsolute . '/' . $fileName;

                $command = [
                    $ffmpegPath,
                    '-y',
                    '-i', $inputPath,
                    '-c:a', 'libmp3lame',
                    '-q:a', '2', // VBR Quality
                    $outputFileAbsolute
                ];

                $variationType = MediaVariationType::AUDIO;
                $mimeType = 'audio/mpeg';
                $finalPath = $outputDir . '/' . $fileName;
                $isStreaming = false;
            }

            // 4. Execute
            $result = Process::run($command);

            if ($result->failed()) {
                throw new \Exception("FFmpeg failed: " . $result->errorOutput());
            }

            // 5. Create Variation
            MediaVariation::create([
                'item_id' => $this->item->id,
                'profile_name' => $isStreaming ? 'hls_standard' : 'mp3_standard',
                'type' => $variationType,
                'disk' => $diffusionDisk,
                'file_path' => $finalPath,
                'mime_type' => $mimeType,
                'is_streaming' => $isStreaming,
                'status' => MediaVariationStatus::READY,
                'generation_params' => ['command' => implode(' ', $command)],
            ]);

            $this->item->updateProcessingState(
                ItemProcessingType::DIFFUSION,
                ItemProcessingStatus::COMPLETED,
                'Media generated successfully.'
            );

        } catch (\Exception $e) {
            $this->item->updateProcessingState(
                ItemProcessingType::DIFFUSION,
                ItemProcessingStatus::FAILED,
                $e->getMessage()
            );
            $this->fail($e);
        }
    }
}
