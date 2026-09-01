<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Test de la commande import:telemeta.
 *
 * La base de test est mms_crem_test (voir phpunit.xml) : la base réelle mms_crem
 * n'est jamais touchée. RefreshDatabase rejoue les migrations, puis ce fichier
 * crée les tables Telemeta media_* (absentes des migrations) avec les colonnes
 * lues par la commande, et y insère des fixtures minimales.
 */

beforeEach(function () {
    createTelemetaTestTables();
    seedTelemetaFixtures();
});

it('importe fonds, corpus, collections, items et fichiers liés depuis media_*', function () {
    $this->artisan('import:telemeta')->assertSuccessful();

    expect(DB::table('fonds')->count())->toBe(2)
        ->and(DB::table('corpuses')->count())->toBe(2)
        ->and(DB::table('collections')->count())->toBe(2)
        ->and(DB::table('corpus_fond')->count())->toBe(2)
        ->and(DB::table('collection_corpus')->count())->toBe(1)
        // 2 items principaux (la fiche sans code ni fichier est ignorée)
        // + 1 sous-item media_item_related + 3 fichiers liés fonds/corpus/collection
        ->and(DB::table('items')->count())->toBe(6);

    $item = DB::table('items')->where('code', 'CNRSMH_I_1963_001_001')->first();

    expect($item->itemable_type)->toBe(App\Models\Collection::class)
        ->and($item->is_sub)->toBe(0)
        ->and($item->public_access)->toBe('full')
        ->and($item->upload_date)->toBe('2020-01-15')
        ->and($item->file_type)->toBe('audio/wav')
        ->and($item->code_prefix)->toBe('CNRSMH_I_1963_001')
        ->and($item->code_suffix)->toBe('001');

    // mimetype vide -> déduit de l'extension
    $item2 = DB::table('items')->where('code', 'CNRSMH_I_1963_001_002')->first();
    expect($item2->file_type)->toBe('audio/mpeg')
        ->and($item2->upload_date)->toBe(now()->toDateString());

    // Fichier lié attaché à l'item principal
    $sub = DB::table('items')->where('code', 'CNRSMH_I_1963_001_001_livret')->first();
    expect($sub->is_sub)->toBe(1)
        ->and($sub->itemable_type)->toBe(App\Models\Item::class)
        ->and($sub->itemable_id)->toBe($item->id)
        ->and($sub->file_type)->toBe('application/pdf');

    // Fichiers liés attachés aux fonds / corpus / collections
    $fondRelated = DB::table('items')->where('code', 'note_fonds')->first();
    expect($fondRelated->itemable_type)->toBe(App\Models\Fond::class)
        ->and($fondRelated->is_sub)->toBe(1)
        ->and($fondRelated->itemable_id)->toBe(DB::table('fonds')->where('code', 'CNRSMH_I')->value('id'));

    $corpusRelated = DB::table('items')->where('code', 'photo_corpus')->first();
    expect($corpusRelated->itemable_type)->toBe(App\Models\Corpus::class)
        ->and($corpusRelated->itemable_id)->toBe(DB::table('corpuses')->where('code', 'CNRSMH_I_1963')->value('id'));

    $collectionRelated = DB::table('items')->where('code', 'booklet')->first();
    expect($collectionRelated->itemable_type)->toBe(App\Models\Collection::class)
        // mime_type NULL en source -> déduit de l'extension
        ->and($collectionRelated->file_type)->toBe('application/pdf')
        ->and($collectionRelated->itemable_id)->toBe(DB::table('collections')->where('code', 'CNRSMH_I_1963_001')->value('id'));

    // Mapping public_access : '' -> défaut config, mixedmetadata -> mixed, none conservé
    expect(DB::table('fonds')->where('code', 'FONDS_ORPHELIN')->value('public_access'))
        ->toBe((string) config('mms.access.defaults.fond'))
        ->and(DB::table('corpuses')->where('code', 'CORPUS_ORPHE')->value('public_access'))->toBe('mixed')
        ->and(DB::table('collections')->where('code', 'COLL_SANS_CORPUS')->value('public_access'))->toBe('none');
});

it('ignore les items sans code ni fichier', function () {
    $this->artisan('import:telemeta')->assertSuccessful();

    expect(DB::table('items')->where('title', 'Fiche sans rien')->exists())->toBeFalse();
});

it('est idempotent : un second import ne crée aucun doublon', function () {
    $this->artisan('import:telemeta')->assertSuccessful();
    $this->artisan('import:telemeta')->assertSuccessful();

    expect(DB::table('fonds')->count())->toBe(2)
        ->and(DB::table('corpuses')->count())->toBe(2)
        ->and(DB::table('collections')->count())->toBe(2)
        ->and(DB::table('corpus_fond')->count())->toBe(2)
        ->and(DB::table('collection_corpus')->count())->toBe(1)
        ->and(DB::table('items')->count())->toBe(6);
});

it('n\'écrit rien en dry-run', function () {
    $this->artisan('import:telemeta', ['--dry-run' => true])->assertSuccessful();

    expect(DB::table('fonds')->count())->toBe(0)
        ->and(DB::table('corpuses')->count())->toBe(0)
        ->and(DB::table('collections')->count())->toBe(0)
        ->and(DB::table('items')->count())->toBe(0)
        ->and(DB::table('corpus_fond')->count())->toBe(0)
        ->and(DB::table('collection_corpus')->count())->toBe(0);
});

it('rejette une étape inconnue dans --only', function () {
    $this->artisan('import:telemeta', ['--only' => 'fonds,inconnu'])
        ->assertFailed();

    expect(DB::table('fonds')->count())->toBe(0);
});

it('exécute une seule étape avec --only', function () {
    $this->artisan('import:telemeta', ['--only' => 'fonds'])->assertSuccessful();

    expect(DB::table('fonds')->count())->toBe(2)
        ->and(DB::table('corpuses')->count())->toBe(0)
        ->and(DB::table('items')->count())->toBe(0);
});

/**
 * Crée les tables Telemeta media_* minimales (colonnes lues par la commande).
 */
function createTelemetaTestTables(): void
{
    Schema::create('media_fonds', function ($t) {
        $t->increments('id');
        $t->string('title')->nullable();
        $t->string('code')->unique();
        $t->string('public_access')->nullable();
    });

    Schema::create('media_fonds_children', function ($t) {
        $t->increments('id');
        $t->unsignedInteger('mediafonds_id');
        $t->unsignedInteger('mediacorpus_id');
    });

    Schema::create('media_corpus', function ($t) {
        $t->increments('id');
        $t->string('title')->nullable();
        $t->string('code')->unique();
        $t->string('public_access')->nullable();
    });

    Schema::create('media_corpus_children', function ($t) {
        $t->increments('id');
        $t->unsignedInteger('mediacorpus_id');
        $t->unsignedInteger('mediacollection_id');
    });

    Schema::create('media_collections', function ($t) {
        $t->increments('id');
        $t->string('title')->nullable();
        $t->string('code')->unique();
        $t->string('public_access')->nullable();
    });

    Schema::create('media_items', function ($t) {
        $t->increments('id');
        $t->string('title')->nullable();
        $t->unsignedInteger('collection_id');
        $t->string('code')->nullable();
        $t->string('filename', 1024)->nullable();
        $t->string('mimetype')->nullable();
        $t->string('public_access')->nullable();
        $t->date('digitization_date')->nullable();
    });

    foreach (['media_fonds_related', 'media_corpus_related', 'media_collection_related'] as $table) {
        Schema::create($table, function ($t) {
            $t->increments('id');
            $t->string('title')->nullable();
            $t->string('filename')->nullable();
            $t->unsignedInteger('resource_id');
            $t->string('mime_type')->nullable();
        });
    }

    Schema::create('media_item_related', function ($t) {
        $t->increments('id');
        $t->unsignedInteger('item_id');
        $t->string('title')->nullable();
        $t->string('filename')->nullable();
        $t->string('mime_type')->nullable();
    });
}

/**
 * Insère un jeu de données Telemeta minimal couvrant les cas clés.
 */
function seedTelemetaFixtures(): void
{
    DB::table('users')->insert([
        'name' => 'Importer',
        'email' => 'import-telemeta@test.local',
        'password' => bcrypt('secret'),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('media_fonds')->insert([
        ['id' => 1, 'code' => 'CNRSMH_I', 'title' => 'Fonds I', 'public_access' => 'full'],
        ['id' => 2, 'code' => 'FONDS_ORPHELIN', 'title' => 'Fonds sans corpus lié', 'public_access' => ''],
    ]);

    DB::table('media_corpus')->insert([
        ['id' => 10, 'code' => 'CNRSMH_I_1963', 'title' => 'Corpus 1963', 'public_access' => 'metadata'],
        ['id' => 11, 'code' => 'CORPUS_ORPHE', 'title' => 'Corpus sans fond', 'public_access' => 'mixedmetadata'],
    ]);

    DB::table('media_fonds_children')->insert([
        ['mediafonds_id' => 1, 'mediacorpus_id' => 10],
        ['mediafonds_id' => 1, 'mediacorpus_id' => 11],
    ]);

    DB::table('media_collections')->insert([
        ['id' => 100, 'code' => 'CNRSMH_I_1963_001', 'title' => 'Collection A', 'public_access' => 'metadata'],
        ['id' => 101, 'code' => 'COLL_SANS_CORPUS', 'title' => 'Collection B', 'public_access' => 'none'],
    ]);

    DB::table('media_corpus_children')->insert([
        ['mediacorpus_id' => 10, 'mediacollection_id' => 100],
    ]);

    DB::table('media_items')->insert([
        [
            'id' => 1000,
            'collection_id' => 100,
            'code' => 'CNRSMH_I_1963_001_001',
            'title' => 'Item 1',
            'filename' => 'CNRSMH_I_1963_001_001.wav',
            'mimetype' => 'audio/wav',
            'public_access' => 'full',
            'digitization_date' => '2020-01-15',
        ],
        [
            'id' => 1001,
            'collection_id' => 100,
            'code' => 'CNRSMH_I_1963_001_002',
            'title' => 'Item 2',
            'filename' => 'CNRSMH_I_1963_001_002.mp3',
            'mimetype' => '',
            'public_access' => 'metadata',
            'digitization_date' => null,
        ],
        [
            // Fiche sans code ni fichier : doit être ignorée
            'id' => 1002,
            'collection_id' => 100,
            'code' => null,
            'title' => 'Fiche sans rien',
            'filename' => null,
            'mimetype' => '',
            'public_access' => 'metadata',
            'digitization_date' => null,
        ],
    ]);

    DB::table('media_item_related')->insert([
        ['item_id' => 1000, 'title' => 'Livret', 'filename' => 'CNRSMH_I_1963_001_001_livret.pdf', 'mime_type' => 'application/pdf'],
    ]);

    DB::table('media_fonds_related')->insert([
        ['resource_id' => 1, 'title' => 'Note fonds', 'filename' => 'note_fonds.pdf', 'mime_type' => 'application/pdf'],
    ]);

    DB::table('media_corpus_related')->insert([
        ['resource_id' => 10, 'title' => 'Photo corpus', 'filename' => 'photo_corpus.jpg', 'mime_type' => 'image/jpeg'],
    ]);

    DB::table('media_collection_related')->insert([
        ['resource_id' => 100, 'title' => 'Booklet', 'filename' => 'booklet.pdf', 'mime_type' => null],
    ]);
}
