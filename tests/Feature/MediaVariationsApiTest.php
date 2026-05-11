<?php

use App\Enums\MediaVariationStatus;
use App\Enums\MediaVariationType;
use App\Models\Collection;
use App\Models\Corpus;
use App\Models\Fond;
use App\Models\Item;
use App\Models\ItemType;
use App\Models\MediaVariation;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

beforeEach(function () {
    Storage::fake('original_medias');
    Storage::fake('public');
});

test('it lists media variations for an item by item type suffix', function () {
    $user = User::factory()->create();
    Item::unsetEventDispatcher();

    $itemType = ItemType::create([
        'name' => 'Musique',
        'suffix' => 'MU',
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    $item = Item::create([
        'code' => 'ROOT001',
        'file_type' => 'audio/mpeg',
        'file_path' => 'test.mp3',
        'file_name' => 'test.mp3',
        'title' => 'Root Item',
        'item_type_id' => $itemType->id,
        'itemable_type' => 'App\Models\Collection',
        'language_code' => 'fr',
        'itemable_id' => 1,
        'created_by' => $user->id,
        'uploaded_by' => $user->id,
    ]);

    Storage::disk('original_medias')->put('test.mp3', 'content');

    // Create a variation for root item
    MediaVariation::create([
        'item_id' => $item->id,
        'profile_name' => 'hls_standard',
        'type' => MediaVariationType::AUDIO,
        'disk' => 'public',
        'file_path' => 'items/ROOT001/diffusion/playlist.m3u8',
        'mime_type' => 'application/x-mpegURL',
        'is_streaming' => true,
        'status' => MediaVariationStatus::READY,
    ]);

    // Create an associated media (child item) with a different type and language
    $transType = ItemType::create([
        'name' => 'Transcription',
        'suffix' => 'TRA',
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    $childItem = Item::create([
        'code' => 'ROOT001_TRA_fr',
        'file_type' => 'application/pdf',
        'file_path' => 'trans.pdf',
        'file_name' => 'trans.pdf',
        'title' => 'Transcription',
        'item_type_id' => $transType->id,
        'itemable_type' => Item::class,
        'itemable_id' => $item->id,
        'language_code' => 'en', // English for testing native label
        'is_sub' => true,
        'created_by' => $user->id,
        'uploaded_by' => $user->id,
    ]);

    // Test for MU type
    $response = get('/media/ROOT001/MU');
    $response->assertStatus(200)
        ->assertJsonCount(2) // Original (ROOT001) + its HLS variation
        ->assertJsonFragment([
            'code' => 'ROOT001',
            'language_code' => 'fr',
            'language_label' => 'français',
        ]);

    // Test for TRA type
    $response = get('/media/ROOT001/TRA');
    $response->assertStatus(200)
        ->assertJsonCount(1) // Only child item
        ->assertJsonFragment([
            'code' => 'ROOT001_TRA_fr',
            'language_code' => 'en',
            'language_label' => 'English',
        ]);
});

test('it lists media variations for a collection parent', function () {
    $user = User::factory()->create();
    Item::unsetEventDispatcher();

    $fond = Fond::create([
        'code' => 'FOND001',
        'created_by' => $user->id,
    ]);

    $corpus = Corpus::forceCreate([
        'code' => 'COR001',
        'fond_id' => $fond->id,
        'created_by' => $user->id,
    ]);

    $collection = Collection::forceCreate([
        'code' => 'COL001',
        'corpus_id' => $corpus->id,
        'title' => 'Test Collection',
        'created_by' => $user->id,
    ]);

    $itemType = ItemType::create([
        'name' => 'Transcription',
        'suffix' => 'TRA',
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    Item::create([
        'code' => 'COL001_TRA_01',
        'title' => 'Collection Transcription',
        'item_type_id' => $itemType->id,
        'itemable_type' => Collection::class,
        'itemable_id' => $collection->id,
        'is_sub' => true,
        'created_by' => $user->id,
        'uploaded_by' => $user->id,
        'file_path' => 'test.pdf',
        'file_name' => 'test.pdf',
        'file_size' => 100,
        'file_type' => 'application/pdf',
        'file_extension' => 'pdf',
        'upload_date' => now(),
    ]);

    $response = get('/media/COL001/TRA');
    $response->assertStatus(200)
        ->assertJsonCount(1)
        ->assertJsonFragment(['code' => 'COL001_TRA_01']);
});

test('it serves a variation file', function () {
    $user = User::factory()->create();
    Item::unsetEventDispatcher();
    $item = Item::create([
        'code' => 'TEST001',
        'title' => 'Test Item',
        'file_name' => 'test.mp3',
        'itemable_type' => 'App\Models\AudioItem',
        'itemable_id' => 1,
        'created_by' => $user->id,
        'uploaded_by' => $user->id,
    ]);

    MediaVariation::create([
        'item_id' => $item->id,
        'profile_name' => 'thumbnail',
        'type' => MediaVariationType::IMAGE,
        'disk' => 'public',
        'file_path' => 'items/TEST001/thumb.jpg',
        'mime_type' => 'image/jpeg',
        'status' => MediaVariationStatus::READY,
    ]);

    Storage::disk('public')->put('items/TEST001/thumb.jpg', 'fake-image');

    $response = get('/media/TEST001/variation/thumbnail');

    $response->assertStatus(200)
        ->assertHeader('Access-Control-Allow-Origin', '*');
});

test('it returns empty list for invalid type in listing', function () {
    $user = User::factory()->create();
    Item::unsetEventDispatcher();
    Item::create([
        'code' => 'TEST404',
        'title' => 'Test Item',
        'file_name' => 'test.mp3',
        'itemable_type' => 'App\Models\Collection',
        'itemable_id' => 1,
        'created_by' => $user->id,
        'uploaded_by' => $user->id,
    ]);

    get('/media/TEST404/invalid-type')
        ->assertStatus(200)
        ->assertJsonCount(0);
});
