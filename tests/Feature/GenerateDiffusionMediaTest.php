<?php

use App\Enums\MediaVariationStatus;
use App\Enums\MediaVariationType;
use App\Jobs\GenerateDiffusionMedia;
use App\Models\Item;
use App\Models\MediaVariation;
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
        'ffprobe_path' => 'ffprobe',
        'diffusion_disk' => 'diffusion_medias',
    ]));

    $this->user = $user;
});

it('updates existing variation when reprocessed in GenerateDiffusionMedia', function () {
    $item = Item::create([
        'code' => 'ITEM_DIFF_001',
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
            // Simulate creation of output files by ffmpeg
            if (str_contains(is_array($process->command) ? $process->command[0] : $process->command, 'ffmpeg')) {
                Storage::disk('diffusion_medias')->put('items/ITEM_DIFF_001/diffusion/ITEM_DIFF_001.m3u8', 'content');
                Storage::disk('diffusion_medias')->put('items/ITEM_DIFF_001/diffusion/ITEM_DIFF_001_000.ts', 'segment content');
            }

            return Process::result('ok');
        },
    ]);

    // Pre-create a variation
    MediaVariation::create([
        'item_id' => $item->id,
        'profile_name' => 'hls_standard',
        'type' => MediaVariationType::VIDEO,
        'disk' => 'diffusion_medias',
        'file_path' => 'items/ITEM_DIFF_001/diffusion/ITEM_DIFF_001.m3u8',
        'file_size' => 10,
        'mime_type' => 'application/x-mpegURL',
        'is_streaming' => true,
        'status' => MediaVariationStatus::READY,
    ]);

    // Manually create some files in the output dir so size calculation works
    Storage::disk('diffusion_medias')->put('items/ITEM_DIFF_001/diffusion/ITEM_DIFF_001.m3u8', 'content');
    Storage::disk('diffusion_medias')->put('items/ITEM_DIFF_001/diffusion/ITEM_DIFF_001_000.ts', 'segment content');

    (new GenerateDiffusionMedia($item))->handle();

    // Assert only 1 variation exists for this item and profile
    expect(MediaVariation::where('item_id', $item->id)->where('profile_name', 'hls_standard')->count())->toBe(1);

    $variation = MediaVariation::where('item_id', $item->id)->where('profile_name', 'hls_standard')->first();
    // Size should be sum of m3u8 and ts files
    $expectedSize = Storage::disk('diffusion_medias')->size('items/ITEM_DIFF_001/diffusion/ITEM_DIFF_001.m3u8') +
                    Storage::disk('diffusion_medias')->size('items/ITEM_DIFF_001/diffusion/ITEM_DIFF_001_000.ts');

    expect($variation->file_size)->toBe($expectedSize);
});
