<?php

use App\Models\Item;
use App\Models\MediaVariation;
use Illuminate\Support\Facades\Storage;

it('serves segment file using MediaVariationPathResolver', function () {
    $item = new Item([
        'code' => 'CODE123',
        'file_path' => 'items/2026/05/21/CODE123.wav',
        'itemable_type' => 'App\Models\Collection',
        'itemable_id' => 1,
        'created_by' => 1,
        'file_name' => 'test.wav',
    ]);
    $item->saveQuietly();

    $variation = new MediaVariation([
        'item_id' => $item->id,
        'is_streaming' => true,
        'disk' => 'local',
        'status' => \App\Enums\MediaVariationStatus::READY,
        'type' => 'video',
        'profile_name' => 'hls_standard',
        'file_path' => 'dummy/path',
        'mime_type' => 'video/mp2t',
    ]);
    $variation->saveQuietly();

    $segment = 'CODE123_001.ts';
    $path = 'items/2026/05/21/CODE123/diffusion/'.$segment;

    Storage::fake('local');
    Storage::disk('local')->put($path, 'dummy-content');

    $response = $this->get('/media/CODE123/'.$segment);

    $response->assertStatus(200);
    $response->assertHeader('Content-Type', 'video/MP2T');
});
