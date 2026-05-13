<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class MediaEncodingSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settingsPath = 'mms_settings.json';
        $disk = \Illuminate\Support\Facades\Storage::disk('local');

        $settings = [];
        if ($disk->exists($settingsPath)) {
            $settings = json_decode($disk->get($settingsPath), true) ?? [];
        }

        $defaults = [
            'video_codec' => config('mms.encoding.video.codec.default', 'libx264'),
            'video_preset' => config('mms.encoding.video.preset.default', 'veryfast'),
            'video_crf' => config('mms.encoding.video.crf.default', 23),
            'video_audio_bitrate' => config('mms.encoding.video.audio_bitrate.default', '128k'),
            'video_hls_time' => config('mms.encoding.video.hls_time.default', 4),

            'audio_codec' => config('mms.encoding.audio.codec.default', 'aac'),
            'audio_bitrate' => config('mms.encoding.audio.bitrate.default', '128k'),
            'audio_channels' => config('mms.encoding.audio.channels.default', 2),
            'audio_hls_time' => config('mms.encoding.audio.hls_time.default', 10),

            'waveform_pixels_per_second' => config('mms.encoding.waveform.pixels_per_second.default', 20),
            'waveform_bits' => config('mms.encoding.waveform.bits.default', 8),
        ];

        // On ne remplace que les valeurs absentes pour ne pas écraser les changements manuels éventuels
        foreach ($defaults as $key => $value) {
            if (! array_key_exists($key, $settings)) {
                $settings[$key] = $value;
            }
        }

        $disk->put($settingsPath, json_encode($settings, JSON_PRETTY_PRINT));
    }
}
