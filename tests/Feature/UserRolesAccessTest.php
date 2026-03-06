<?php

use App\Enums\UserRole;
use App\Models\Collection;
use App\Models\Corpus;
use App\Models\Fond;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('super admin has access to everything', function () {
    $admin = User::factory()->create(['role' => UserRole::ADMINISTRATEUR]);
    $fond = Fond::create(['code' => 'TEST_FOND_1', 'created_by' => $admin->id]);

    expect($admin->hasAccessToModel($fond))->toBeTrue();
});

test('chercheur has access only to own creations', function () {
    $chercheur = User::factory()->create(['role' => UserRole::CHERCHEUR]);
    $otherUser = User::factory()->create();

    $ownFond = Fond::create(['code' => 'TEST_FOND_2', 'created_by' => $chercheur->id]);
    $otherFond = Fond::create(['code' => 'TEST_FOND_3', 'created_by' => $otherUser->id]);

    expect($chercheur->hasAccessToModel($ownFond))->toBeTrue();
    expect($chercheur->hasAccessToModel($otherFond))->toBeFalse();
});

test('documentaliste has access via fond', function () {
    $doc = User::factory()->create(['role' => UserRole::DOCUMENTALISTE]);
    $creator = User::factory()->create();
    
    $fondA = Fond::create(['code' => 'TEST_FOND_A', 'created_by' => $creator->id]);
    $fondB = Fond::create(['code' => 'TEST_FOND_B', 'created_by' => $creator->id]);

    // Assign Fond A to Doc
    $doc->scopedFonds()->attach($fondA);

    $corpusUnderA = Corpus::forceCreate(['code' => 'CORPUS_A', 'created_by' => $creator->id, 'fond_id' => $fondA->id]);
    $corpusUnderA->fonds()->attach($fondA);

    $corpusUnderB = Corpus::forceCreate(['code' => 'CORPUS_B', 'created_by' => $creator->id, 'fond_id' => $fondB->id]);
    $corpusUnderB->fonds()->attach($fondB);

    $collectionUnderA = Collection::forceCreate(['code' => 'COLLECTION_A', 'created_by' => $creator->id, 'corpus_id' => $corpusUnderA->id]);
    $collectionUnderA->corpuses()->attach($corpusUnderA);

    // Access to Fond A and its children
    expect($doc->hasAccessToModel($fondA))->toBeTrue();
    expect($doc->hasAccessToModel($corpusUnderA))->toBeTrue();
    expect($doc->hasAccessToModel($collectionUnderA))->toBeTrue();

    // No access to Fond B
    expect($doc->hasAccessToModel($fondB))->toBeFalse();
    expect($doc->hasAccessToModel($corpusUnderB))->toBeFalse();
});

test('documentaliste has access via multiple parents', function () {
    $doc = User::factory()->create(['role' => UserRole::DOCUMENTALISTE]);
    $creator = User::factory()->create();
    
    $fondA = Fond::create(['code' => 'TEST_FOND_MA', 'created_by' => $creator->id]);
    $fondB = Fond::create(['code' => 'TEST_FOND_MB', 'created_by' => $creator->id]);
    
    // Scope only on Fond A
    $doc->scopedFonds()->attach($fondA);

    // Corpus linked to BOTH Fond A and Fond B
    $sharedCorpus = Corpus::forceCreate(['code' => 'CORPUS_SHARED', 'created_by' => $creator->id, 'fond_id' => $fondA->id]);
    $sharedCorpus->fonds()->attach([$fondA->id, $fondB->id]);

    // Collection linked to the shared corpus
    $collectionInSharedCorpus = Collection::forceCreate(['code' => 'COL_SHARED', 'created_by' => $creator->id, 'corpus_id' => $sharedCorpus->id]);
    $collectionInSharedCorpus->corpuses()->attach($sharedCorpus);

    // Doc should have access because it's linked to Fond A
    expect($doc->hasAccessToModel($sharedCorpus))->toBeTrue();
    expect($doc->hasAccessToModel($collectionInSharedCorpus))->toBeTrue();
});

test('documentaliste has access to item', function () {
    $doc = User::factory()->create(['role' => UserRole::DOCUMENTALISTE]);
    $creator = User::factory()->create();
    
    $fondA = Fond::create(['code' => 'TEST_FOND_IA', 'created_by' => $creator->id]);
    $doc->scopedFonds()->attach($fondA);

    $itemInFondA = Item::create([
        'itemable_type' => Fond::class,
        'itemable_id' => $fondA->id,
        'code_prefix' => 'ITEM_A',
        'file_path' => 'fake_path.txt',
        'file_name' => 'fake_name.txt',
        'file_size' => 100,
        'file_type' => 'text/plain',
        'file_extension' => 'txt',
        'upload_date' => now(),
        'uploaded_by' => $creator->id,
        'created_by' => $creator->id
    ]);

    $fondB = Fond::create(['code' => 'TEST_FOND_IB', 'created_by' => $creator->id]);
    $itemInFondB = Item::create([
        'itemable_type' => Fond::class,
        'itemable_id' => $fondB->id,
        'code_prefix' => 'ITEM_B',
        'file_path' => 'fake_path.txt',
        'file_name' => 'fake_name.txt',
        'file_size' => 100,
        'file_type' => 'text/plain',
        'file_extension' => 'txt',
        'upload_date' => now(),
        'uploaded_by' => $creator->id,
        'created_by' => $creator->id
    ]);

    expect($doc->hasAccessToModel($itemInFondA))->toBeTrue();
    expect($doc->hasAccessToModel($itemInFondB))->toBeFalse();
});
