<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application medias path
    |--------------------------------------------------------------------------
    |
    | This value is the path where all originals medias are stored.
    |
    */

    'medias_path' => env('MMS_MEDIAS_PATH', app_path().'/medias'),

    /*
    |--------------------------------------------------------------------------
    | Application public medias path
    |--------------------------------------------------------------------------
    |
    | This value is the path where all publics medias are stored for diffusion
    | Converted originals medias are stored in this folder.
    |
    */
    'public_medias_path' => env('MMS_PUBLICS_MEDIAS_PATH', storage_path().'/medias'),

    /*
    |--------------------------------------------------------------------------
    | Encoding Settings
    |--------------------------------------------------------------------------
    |
    | Default values and options for audio/video encoding and waveform generation.
    |
    */

    'encoding' => [
        'video' => [
            'codec' => [
                'default' => 'libx264',
                'options' => [
                    'libx264' => 'H.264 (AVC)',
                    'libx265' => 'H.265 (HEVC)',
                    'libvpx-vp9' => 'VP9',
                ],
            ],
            'preset' => [
                'default' => 'veryfast',
                'options' => [
                    'ultrafast' => 'Ultra rapide',
                    'superfast' => 'Super rapide',
                    'veryfast' => 'Très rapide',
                    'faster' => 'Plus rapide',
                    'fast' => 'Rapide',
                    'medium' => 'Moyen',
                    'slow' => 'Lent',
                    'slower' => 'Plus lent',
                    'veryslow' => 'Très lent',
                ],
            ],
            'crf' => [
                'default' => 23,
                'min' => 0,
                'max' => 51,
            ],
            'audio_bitrate' => [
                'default' => '128k',
                'options' => [
                    '64k' => '64k',
                    '96k' => '96k',
                    '128k' => '128k',
                    '192k' => '192k',
                    '256k' => '256k',
                ],
            ],
            'hls_time' => [
                'default' => 4,
                'options' => [
                    2 => '2',
                    4 => '4',
                    6 => '6',
                    8 => '8',
                    10 => '10',
                    20 => '20',
                    30 => '30',
                    40 => '40',
                    50 => '50',
                    60 => '60',
                ],
            ],
        ],
        'audio' => [
            'codec' => [
                'default' => 'aac',
                'options' => [
                    'aac' => 'AAC',
                    'libmp3lame' => 'MP3',
                    'libopus' => 'Opus',
                ],
            ],
            'bitrate' => [
                'default' => '128k',
                'options' => [
                    '64k' => '64k',
                    '96k' => '96k',
                    '128k' => '128k',
                    '192k' => '192k',
                    '256k' => '256k',
                    '320k' => '320k',
                ],
            ],
            'channels' => [
                'default' => 2,
                'options' => [
                    1 => 'Mono',
                    2 => 'Stéréo',
                ],
            ],
            'hls_time' => [
                'default' => 10,
                'options' => [
                    4 => '4',
                    6 => '6',
                    8 => '8',
                    10 => '10',
                    15 => '15',
                    20 => '20',
                    30 => '30',
                    40 => '40',
                    50 => '50',
                    60 => '60',
                ],
            ],
        ],
        'waveform' => [
            'pixels_per_second' => [
                'default' => 20,
                'options' => [
                    10 => '10',
                    15 => '15',
                    20 => '20',
                    25 => '25',
                    30 => '30',
                    40 => '40',
                    50 => '50',
                ],
            ],
            'bits' => [
                'default' => 8,
                'options' => [
                    8 => '8 bits',
                    16 => '16 bits',
                ],
            ],
        ],
    ],
];
