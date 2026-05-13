<?php

use App\Jobs\GenerateAudiowaveform;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('original_medias');
    Storage::fake('diffusion_medias');

    Item::unsetEventDispatcher();

    $user = User::factory()->create();
    $this->actingAs($user);

    // Create a dummy settings file
    Storage::disk('local')->put('mms_settings.json', json_encode([
        'ffmpeg_path' => 'ffmpeg',
        'audiowaveform_path' => 'audiowaveform',
        'diffusion_disk' => 'diffusion_medias',
    ]));

    $this->user = $user;
});

it('uses direct audiowaveform for audio items', function () {
    $item = Item::create([
        'code' => 'ITEM001',
        'file_path' => 'test.mp3',
        'file_name' => 'test.mp3',
        'file_type' => 'audio/mpeg',
        'itemable_type' => 'App\Models\Collection',
        'itemable_id' => 1,
        'created_by' => $this->user->id,
        'uploaded_by' => $this->user->id,
    ]);

    // Mock existence of source file
    Storage::disk('original_medias')->put('test.mp3', 'dummy content');

    Process::fake([
        'audiowaveform*' => function ($process) {
            Storage::disk('diffusion_medias')->put('items/ITEM001/waveform/ITEM001.json', '{}');
            return Process::result('ok');
        },
    ]);

    // Manually create the output file so size check doesn't fail
    Storage::disk('diffusion_medias')->put('items/ITEM001/waveform/ITEM001.json', '{}');

    (new GenerateAudiowaveform($item))->handle();

    Process::assertRan(function ($process) {
        $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

        return str_contains($command, 'audiowaveform') &&
               str_contains($command, '-i') &&
               ! str_contains($command, 'ffmpeg');
    });
});

it('uses ffmpeg pipe for video items', function () {
    $item = Item::create([
        'code' => 'ITEM002',
        'file_path' => 'test.mp4',
        'file_name' => 'test.mp4',
        'file_type' => 'video/mp4',
        'itemable_type' => 'App\Models\Collection',
        'itemable_id' => 1,
        'created_by' => $this->user->id,
        'uploaded_by' => $this->user->id,
    ]);

    // Mock existence of source file
    Storage::disk('original_medias')->put('test.mp4', 'dummy content');

    Process::fake([
        '*' => function ($process) {
            if (str_contains(is_array($process->command) ? implode(' ', $process->command) : $process->command, 'audiowaveform')) {
                Storage::disk('diffusion_medias')->put('items/ITEM002/waveform/ITEM002.json', '{}');
            }
            return Process::result('ok');
        },
    ]);

    // Manually create the output file
    Storage::disk('diffusion_medias')->put('items/ITEM002/waveform/ITEM002.json', '{}');

    (new GenerateAudiowaveform($item))->handle();

    // Debug: what processes were recorded?
    // dd(Process::recorded());

    // With Process::pipe, Laravel records each individual command in the pipe when using fake
    Process::assertRan(function ($process) {
        $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

        return str_contains($command, 'ffmpeg');
    });

    Process::assertRan(function ($process) {
        $command = is_array($process->command) ? implode(' ', $process->command) : $process->command;

        return str_contains($command, 'audiowaveform');
    });
});

it('updates existing variation when reprocessed', function () {
    $item = Item::create([
        'code' => 'ITEM003',
        'file_path' => 'test.mp3',
        'file_name' => 'test.mp3',
        'file_type' => 'audio/mpeg',
        'itemable_type' => 'App\Models\Collection',
        'itemable_id' => 1,
        'created_by' => $this->user->id,
        'uploaded_by' => $this->user->id,
    ]);

    // Mock existence of source file
    Storage::disk('original_medias')->put('test.mp3', 'dummy content');

    Process::fake([
        '*' => function ($process) {
            if (str_contains(is_array($process->command) ? implode(' ', $process->command) : $process->command, 'audiowaveform')) {
                Storage::disk('diffusion_medias')->put('items/ITEM003/waveform/ITEM003.json', 'new content');
            }
            return Process::result('ok');
        },
    ]);

    // Pre-create a variation
    \App\Models\MediaVariation::create([
        'item_id' => $item->id,
        'profile_name' => 'waveform_json',
        'type' => \App\Enums\MediaVariationType::DATA,
        'disk' => 'diffusion_medias',
        'file_path' => 'items/ITEM003/waveform/ITEM003.json',
        'file_size' => 10,
        'mime_type' => 'application/json',
        'is_streaming' => false,
        'status' => \App\Enums\MediaVariationStatus::READY,
    ]);

    // Manually create the output file so size check doesn't fail
    Storage::disk('diffusion_medias')->put('items/ITEM003/waveform/ITEM003.json', 'new content');

    (new GenerateAudiowaveform($item))->handle();

    // Assert only 1 variation exists for this item
    expect(\App\Models\MediaVariation::where('item_id', $item->id)->count())->toBe(1);

    // Assert it was updated (e.g., file_size changed from 10 to something else)
    $variation = \App\Models\MediaVariation::where('item_id', $item->id)->first();
    expect($variation->file_size)->toBe(Storage::disk('diffusion_medias')->size('items/ITEM003/waveform/ITEM003.json'));
});
