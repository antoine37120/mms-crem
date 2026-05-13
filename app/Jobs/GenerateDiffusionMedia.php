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
            $ffprobePath = $settings['ffprobe_path'] ?? 'ffprobe';
            $diffusionDisk = $settings['diffusion_disk'] ?? 'diffusion_medias';

            // Encoding Settings
            $videoCodec = $settings['video_codec'] ?? config('mms.encoding.video.codec.default', 'libx264');
            $videoPreset = $settings['video_preset'] ?? config('mms.encoding.video.preset.default', 'veryfast');
            $videoCrf = $settings['video_crf'] ?? config('mms.encoding.video.crf.default', 23);
            $videoAudioBitrate = $settings['video_audio_bitrate'] ?? config('mms.encoding.video.audio_bitrate.default', '128k');
            $videoHlsTime = $settings['video_hls_time'] ?? config('mms.encoding.video.hls_time.default', 4);

            $audioCodec = $settings['audio_codec'] ?? config('mms.encoding.audio.codec.default', 'aac');
            $audioBitrate = $settings['audio_bitrate'] ?? config('mms.encoding.audio.bitrate.default', '128k');
            $audioChannels = $settings['audio_channels'] ?? config('mms.encoding.audio.channels.default', 2);
            $audioHlsTime = $settings['audio_hls_time'] ?? config('mms.encoding.audio.hls_time.default', 10);

            // 2. Input/Output Paths
            $inputPath = Storage::disk('original_medias')->path($this->item->file_path);

            // Check if input exists
            if (! file_exists($inputPath)) {
                throw new \Exception("Source file not found at: {$inputPath}");
            }

            $outputDir = 'items/'.$this->item->code.'/diffusion';
            $outputPathRelative = $outputDir.'/'.$this->item->code; // Base name

            // Ensure output directory exists
            Storage::disk($diffusionDisk)->makeDirectory($outputDir);
            $outputDirAbsolute = Storage::disk($diffusionDisk)->path($outputDir);

            // 2.5 Extract Duration if missing (using ffprobe)
            if (! $this->item->duration) {
                try {
                    // Command to get duration in seconds
                    // ffprobe -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 input.mp4
                    $probeCommand = [
                        $ffprobePath,
                        '-v', 'error',
                        '-show_entries', 'format=duration',
                        '-of', 'default=noprint_wrappers=1:nokey=1',
                        $inputPath,
                    ];

                    $probeResult = Process::run($probeCommand);

                    if ($probeResult->successful()) {
                        $duration = (float) trim($probeResult->output());
                        if ($duration > 0) {
                            $this->item->duration = (int) round($duration);
                            $this->item->saveQuietly(); // Save without triggering observers/events
                        }
                    } else {
                        \Log::warning("FFprobe failed to extract duration for Item {$this->item->id}: ".$probeResult->errorOutput());
                    }
                } catch (\Exception $e) {
                    \Log::warning("Exception during duration extraction for Item {$this->item->id}: ".$e->getMessage());
                }
            }

            // 3. Command Generation
            if ($this->item->isVideo()) {
                // HLS Generation
                // Simple HLS command: 360p, 720p, 1080p
                // For MVP, single stream HLS
                $playlistName = $this->item->code.'.m3u8';
                $outputFileAbsolute = $outputDirAbsolute.'/'.$playlistName;

                $command = [
                    $ffmpegPath,
                    '-y',
                    '-i', $inputPath,
                    '-c:v', $videoCodec,
                    '-preset', $videoPreset,
                    '-crf', (string) $videoCrf,
                    '-g', '48', // Keyframe interval (GOP)
                    '-sc_threshold', '0',
                    '-c:a', 'aac',
                    '-b:a', $videoAudioBitrate,
                    '-ac', (string) $audioChannels,
                    '-f', 'hls',
                    '-hls_time', (string) $videoHlsTime,
                    '-hls_playlist_type', 'vod',
                    '-hls_base_url', $this->item->code.'/',
                    '-hls_segment_filename', $outputDirAbsolute.'/'.$this->item->code.'_%03d.ts',
                    $outputFileAbsolute,
                ];

                $variationType = MediaVariationType::VIDEO;
                $mimeType = 'application/x-mpegURL';
                $finalPath = $outputDir.'/'.$playlistName;
                $isStreaming = true;

            } else {
                // Audio - HLS conversion (Streaming)
                $playlistName = $this->item->code.'.m3u8';
                $outputFileAbsolute = $outputDirAbsolute.'/'.$playlistName;

                $command = [
                    $ffmpegPath,
                    '-y',
                    '-i', $inputPath,
                    '-c:a', $audioCodec,
                    '-b:a', $audioBitrate, // Bitrate
                    '-ac', (string) $audioChannels,     // Channels
                    '-f', 'hls',
                    '-hls_time', (string) $audioHlsTime, // Segment duration
                    '-hls_playlist_type', 'vod',
                    '-hls_base_url', $this->item->code.'/',
                    '-hls_segment_filename', $outputDirAbsolute.'/'.$this->item->code.'_%03d.ts',
                    $outputFileAbsolute,
                ];

                $variationType = MediaVariationType::AUDIO;
                $mimeType = 'application/x-mpegURL';
                $finalPath = $outputDir.'/'.$playlistName;
                $isStreaming = true;
            }

            // 4. Execute
            $result = Process::run($command);

            if ($result->failed()) {
                throw new \Exception('FFmpeg failed: '.$result->errorOutput());
            }

            // Calculate total size of the generated folder
            $totalSize = 0;
            $files = Storage::disk($diffusionDisk)->allFiles($outputDir);
            foreach ($files as $file) {
                $totalSize += Storage::disk($diffusionDisk)->size($file);
            }

            // 5. Create or Update Variation
            MediaVariation::updateOrCreate(
                [
                    'item_id' => $this->item->id,
                    'profile_name' => $isStreaming ? 'hls_standard' : 'mp3_standard',
                ],
                [
                    'type' => $variationType,
                    'disk' => $diffusionDisk,
                    'file_path' => $finalPath,
                    'file_size' => $totalSize,
                    'mime_type' => $mimeType,
                    'is_streaming' => $isStreaming,
                    'status' => MediaVariationStatus::READY,
                    'generation_params' => ['command' => implode(' ', $command)],
                ]
            );

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
