<?php

use App\Exceptions\ItemFileReplacementException;
use App\Filament\Resources\Items\Pages\ViewItem;
use App\Jobs\GenerateAudiowaveform;
use App\Jobs\GenerateDiffusionMedia;
use App\Livewire\UploadedFilesTable;
use App\Models\Collection;
use App\Models\Item;
use App\Models\ItemProcessingState;
use App\Models\ItemType;
use App\Models\MediaVariation;
use App\Models\PendingFile;
use App\Models\User;
use App\Observers\ItemObserver;
use App\Services\ItemFileReplacer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

/*
|--------------------------------------------------------------------------
| Fixtures minimales et valides (MIME détectable par libmagic)
|--------------------------------------------------------------------------
*/

function ifrWav(string $marker): string
{
    $data = $marker;
    $dataSize = strlen($data);

    return 'RIFF'.pack('V', 36 + $dataSize).'WAVE'
        .'fmt '.pack('V', 16).pack('v', 1).pack('v', 1)
        .pack('V', 8000).pack('V', 8000).pack('v', 1).pack('v', 8)
        .'data'.pack('V', $dataSize).$data;
}

function ifrMp3(string $marker): string
{
    return str_repeat("\xFF\xFB\x90\x00", 8).$marker;
}

function ifrPdf(string $marker): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n{$marker}\n%%EOF";
}

function ifrMime(string $extension): string
{
    return match (strtolower($extension)) {
        'wav' => 'audio/x-wav',
        'mp3' => 'audio/mpeg',
        'pdf' => 'application/pdf',
        default => 'application/octet-stream',
    };
}

function ifrMakeItem(Collection $collection, User $user, array $overrides = []): Item
{
    $code = $overrides['code'] ?? 'IFR_'.strtoupper(uniqid());
    $extension = $overrides['file_extension'] ?? 'wav';
    $content = $overrides['content'] ?? ifrWav('OLD');
    $filePath = $overrides['file_path'] ?? ('items/'.now()->format('Y/m/d').'/'.$code.'.'.$extension);

    Storage::disk('original_medias')->put($filePath, $content);

    unset($overrides['content']);

    return Item::create(array_merge([
        'itemable_type' => Collection::class,
        'itemable_id' => $collection->id,
        'item_type_id' => null,
        'is_sub' => false,
        'code' => $code,
        'code_prefix' => $code,
        'code_suffix' => null,
        'title' => 'Item '.$code,
        'file_path' => $filePath,
        'file_name' => $code.'.'.$extension,
        'file_size' => strlen($content),
        'file_type' => ifrMime($extension),
        'file_extension' => $extension,
        'duration' => null,
        'upload_date' => now()->toDateString(),
        'uploaded_by' => $user->id,
        'created_by' => $user->id,
        'public_access' => 'full',
    ], $overrides));
}

function ifrMakePendingFile(User $user, string $extension, string $content, ?string $name = null, array $overrides = []): PendingFile
{
    $name ??= 'envoi-'.uniqid().'.'.$extension;
    $path = 'pending-files/'.uniqid().'-'.$name;

    Storage::disk('local')->put($path, $content);

    return PendingFile::create(array_merge([
        'user_id' => $user->id,
        'original_name' => $name,
        'stored_name' => basename($path),
        'file_path' => $path,
        'file_size' => strlen($content),
        'file_type' => ifrMime($extension),
        'file_extension' => $extension,
        'upload_status' => PendingFile::STATUS_COMPLETED,
        'client_signature' => md5($content),
    ], $overrides));
}

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('original_medias');
    Storage::fake('diffusion_medias');
    Queue::fake();
    config(['audit.enabled' => true, 'audit.console' => true]);

    $this->admin = User::factory()->admin()->create();
    $this->collection = Collection::create([
        'code' => 'COLL_IFR',
        'title' => 'Collection IFR',
        'created_by' => $this->admin->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| AC1 — même jour, même extension (chemin cible identique à l'ancien)
|--------------------------------------------------------------------------
*/

test('AC1 remplace un fichier à chemin identique sans perdre la cote ni l’identifiant', function () {
    $oldContent = ifrWav('ANCIEN-CONTENU');
    $newContent = ifrWav('NOUVEAU-CONTENU');

    $item = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_SAME',
        'content' => $oldContent,
    ]);

    $originalPath = $item->file_path;
    $originalId = $item->id;

    $pending = ifrMakePendingFile($this->admin, 'wav', $newContent, 'nouveau.wav');

    Queue::fake();
    actingAs($this->admin);

    $result = app(ItemFileReplacer::class)->replace($item, $pending);

    $fresh = $item->fresh();

    expect($fresh->file_path)->toBe($originalPath)
        ->and($fresh->id)->toBe($originalId)
        ->and($fresh->code)->toBe('IFR_SAME')
        ->and($fresh->code_prefix)->toBe('IFR_SAME')
        ->and($fresh->file_name)->toBe('nouveau.wav')
        ->and($result->newPath)->toBe($originalPath)
        ->and($result->archivePath)->not->toBeNull();

    Storage::disk('original_medias')->assertExists($originalPath);
    expect(Storage::disk('original_medias')->get($originalPath))->toBe($newContent);

    Storage::disk('original_medias')->assertExists($result->archivePath);
    expect(Storage::disk('original_medias')->get($result->archivePath))->toBe($oldContent);
});

/*
|--------------------------------------------------------------------------
| AC2 — changement d'extension
|--------------------------------------------------------------------------
*/

test('AC2 change d’extension wav → mp3 et met à jour les métadonnées techniques', function () {
    $oldContent = ifrWav('ANCIEN-WAV');
    $newContent = ifrMp3('NOUVEAU-MP3');

    $item = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_EXT',
        'content' => $oldContent,
    ]);

    $oldPath = $item->file_path;
    $pending = ifrMakePendingFile($this->admin, 'mp3', $newContent, 'nouveau.mp3');

    Queue::fake();
    actingAs($this->admin);

    $result = app(ItemFileReplacer::class)->replace($item, $pending);
    $fresh = $item->fresh();

    $expectedPath = 'items/'.now()->format('Y/m/d').'/IFR_EXT.mp3';

    expect($result->newPath)->toBe($expectedPath)
        ->and($fresh->file_path)->toBe($expectedPath)
        ->and($fresh->file_extension)->toBe('mp3')
        ->and($fresh->file_type)->toBe('audio/mpeg')
        ->and($fresh->file_size)->toBe(strlen($newContent))
        ->and($fresh->file_name)->toBe('nouveau.mp3');

    Storage::disk('original_medias')->assertMissing($oldPath);
    Storage::disk('original_medias')->assertExists($expectedPath);

    expect(Storage::disk('original_medias')->get($result->archivePath))->toBe($oldContent);
});

/*
|--------------------------------------------------------------------------
| AC3 — audio remplacé par un PDF : durée et dérivés nettoyés
|--------------------------------------------------------------------------
*/

test('AC3 remplace un audio par un PDF et nettoie durée, variations et dérivés', function () {
    $item = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_PDF',
        'content' => ifrWav('ANCIEN-AUDIO'),
    ]);

    $item->forceFill(['duration' => 42])->saveQuietly();

    $diffusionDir = 'items/'.now()->format('Y/m/d').'/IFR_PDF/diffusion';
    $waveformDir = 'items/'.now()->format('Y/m/d').'/IFR_PDF/waveform';

    Storage::disk('diffusion_medias')->put($diffusionDir.'/IFR_PDF.m3u8', '#EXTM3U');
    Storage::disk('diffusion_medias')->put($waveformDir.'/IFR_PDF.json', '{}');

    MediaVariation::create([
        'item_id' => $item->id,
        'profile_name' => 'default',
        'type' => 'audio',
        'disk' => 'diffusion_medias',
        'file_path' => $diffusionDir.'/IFR_PDF.m3u8',
        'mime_type' => 'application/vnd.apple.mpegurl',
        'is_streaming' => true,
        'status' => 'ready',
    ]);

    ItemProcessingState::create([
        'item_id' => $item->id,
        'process_type' => 'diffusion',
        'status' => 'completed',
    ]);

    $pending = ifrMakePendingFile($this->admin, 'pdf', ifrPdf('NOUVEAU-PDF'), 'document.pdf');

    Queue::fake();
    actingAs($this->admin);

    $result = app(ItemFileReplacer::class)->replace($item, $pending);
    $fresh = $item->fresh();

    expect($fresh->file_type)->toBe('application/pdf')
        ->and($fresh->file_extension)->toBe('pdf')
        ->and($fresh->duration)->toBeNull()
        ->and($result->newPath)->toBe('items/'.now()->format('Y/m/d').'/IFR_PDF.pdf');

    expect(MediaVariation::where('item_id', $item->id)->count())->toBe(0);
    expect(ItemProcessingState::where('item_id', $item->id)->count())->toBe(0);

    Storage::disk('diffusion_medias')->assertMissing($diffusionDir);
    Storage::disk('diffusion_medias')->assertMissing($waveformDir);

    Queue::assertNotPushed(GenerateDiffusionMedia::class);
    Queue::assertNotPushed(GenerateAudiowaveform::class);
});

/*
|--------------------------------------------------------------------------
| AC4 — audio remplacé par audio : un seul dispatch de chaque job
|--------------------------------------------------------------------------
*/

test('AC4 relance HLS et forme d’onde une seule fois pour un audio', function () {
    $item = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_AUDIO',
        'content' => ifrWav('ANCIEN-AUDIO'),
    ]);

    $pending = ifrMakePendingFile($this->admin, 'wav', ifrWav('NOUVEL-AUDIO'), 'nouvel-audio.wav');

    Queue::fake();
    actingAs($this->admin);

    app(ItemFileReplacer::class)->replace($item, $pending);

    Queue::assertPushed(GenerateDiffusionMedia::class, 1);
    Queue::assertPushed(GenerateAudiowaveform::class, 1);
    Queue::assertPushedOn('media_processing', GenerateDiffusionMedia::class);
    Queue::assertPushedOn('media_processing', GenerateAudiowaveform::class);
});

/*
|--------------------------------------------------------------------------
| AC5 — md5 et avertissement de doublon
|--------------------------------------------------------------------------
*/

test('AC5 met à jour le md5 et avertit si un autre item porte déjà ce md5', function () {
    $newContent = ifrWav('CONTENU-DUPLIQUE');

    $other = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_DOUBLON',
        'content' => ifrWav('AUTRE-CONTENU'),
        'md5' => md5($newContent),
    ]);

    $item = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_MD5',
        'content' => ifrWav('ANCIEN'),
    ]);

    $pending = ifrMakePendingFile($this->admin, 'wav', $newContent, 'doublon.wav');

    Queue::fake();
    actingAs($this->admin);

    $result = app(ItemFileReplacer::class)->replace($item, $pending);

    expect($item->fresh()->md5)->toBe(md5($newContent))
        ->and($result->warnings)->not->toBeEmpty();

    expect(implode(' ', $result->warnings))->toContain($other->code);
});

/*
|--------------------------------------------------------------------------
| AC6 — archivage par défaut de l'ancien original
|--------------------------------------------------------------------------
*/

test('AC6 archive l’ancien original quand la suppression n’est pas demandée', function () {
    $oldContent = ifrWav('ANCIEN-A-ARCHIVER');

    $item = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_ARCHIVE',
        'content' => $oldContent,
    ]);

    // Simule un ancien fichier rangé dans un dossier daté antérieur.
    $ancientPath = 'items/2020/01/01/IFR_ARCHIVE.wav';
    Storage::disk('original_medias')->put($ancientPath, $oldContent);
    $item->forceFill(['file_path' => $ancientPath])->saveQuietly();

    $pending = ifrMakePendingFile($this->admin, 'wav', ifrWav('NOUVEAU'), 'nouveau.wav');

    Queue::fake();
    actingAs($this->admin);

    $result = app(ItemFileReplacer::class)->replace($item, $pending, deleteOld: false);

    expect($result->archivePath)->not->toBeNull()
        ->and($result->oldDeleted)->toBeFalse();

    Storage::disk('original_medias')->assertExists($result->archivePath);
    expect(Storage::disk('original_medias')->get($result->archivePath))->toBe($oldContent);
    Storage::disk('original_medias')->assertMissing($ancientPath);
});

/*
|--------------------------------------------------------------------------
| AC7 — suppression de l'ancien original sur demande
|--------------------------------------------------------------------------
*/

test('AC7 supprime l’ancien original quand la case est cochée', function () {
    $item = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_SUPPR',
        'content' => ifrWav('ANCIEN-A-SUPPRIMER'),
    ]);

    // Ancien fichier rangé dans un dossier daté antérieur (chemin ≠ cible).
    $oldPath = 'items/2020/01/01/IFR_SUPPR.wav';
    Storage::disk('original_medias')->put($oldPath, ifrWav('ANCIEN-A-SUPPRIMER'));
    $item->forceFill(['file_path' => $oldPath])->saveQuietly();

    $pending = ifrMakePendingFile($this->admin, 'wav', ifrWav('NOUVEAU'), 'nouveau.wav');

    Queue::fake();
    actingAs($this->admin);

    $result = app(ItemFileReplacer::class)->replace($item, $pending, deleteOld: true);

    expect($result->archivePath)->toBeNull()
        ->and($result->oldDeleted)->toBeTrue();

    Storage::disk('original_medias')->assertMissing($oldPath);
    expect(Storage::disk('original_medias')->allFiles('items/_remplaces'))->toBe([]);
    expect(Storage::disk('original_medias')->get($result->newPath))->toBe(ifrWav('NOUVEAU'));
});

/*
|--------------------------------------------------------------------------
| AC8 — droits
|--------------------------------------------------------------------------
*/

test('AC8 refuse un utilisateur sans droit update et ne modifie rien', function () {
    $item = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_DROITS',
        'content' => ifrWav('ANCIEN'),
    ]);

    $oldPath = $item->file_path;
    $pending = ifrMakePendingFile($this->admin, 'wav', ifrWav('NOUVEAU'), 'nouveau.wav');

    $researcher = User::factory()->chercheur()->create();
    actingAs($researcher);

    expect(fn () => app(ItemFileReplacer::class)->replace($item, $pending))
        ->toThrow(AuthorizationException::class);

    expect($item->fresh()->file_path)->toBe($oldPath)
        ->and($item->fresh()->md5)->toBeNull()
        ->and(PendingFile::find($pending->id))->not->toBeNull();

    Storage::disk('original_medias')->assertExists($oldPath);
    expect(Storage::disk('original_medias')->get($oldPath))->toBe(ifrWav('ANCIEN'));
    Storage::disk('local')->assertExists($pending->file_path);
});

test('AC8 autorise le chercheur créateur, le super-admin et le documentaliste scopé', function () {
    $researcher = User::factory()->chercheur()->create();

    $researcherItem = ifrMakeItem($this->collection, $researcher, [
        'code' => 'IFR_CREATEUR',
        'content' => ifrWav('ANCIEN-CREATEUR'),
    ]);
    $researcherPending = ifrMakePendingFile($researcher, 'wav', ifrWav('NOUVEAU-CREATEUR'), 'createur.wav');

    Queue::fake();
    actingAs($researcher);
    app(ItemFileReplacer::class)->replace($researcherItem, $researcherPending);

    expect($researcherItem->fresh()->file_name)->toBe('createur.wav');

    $adminItem = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_ADMIN',
        'content' => ifrWav('ANCIEN-ADMIN'),
    ]);
    $adminPending = ifrMakePendingFile($this->admin, 'wav', ifrWav('NOUVEAU-ADMIN'), 'admin.wav');

    Queue::fake();
    actingAs($this->admin);
    app(ItemFileReplacer::class)->replace($adminItem, $adminPending);

    expect($adminItem->fresh()->file_name)->toBe('admin.wav');

    $documentalist = User::factory()->documentaliste()->create();
    $documentalist->scopedCollections()->attach($this->collection->id);

    $documentalistItem = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_DOC',
        'content' => ifrWav('ANCIEN-DOC'),
    ]);
    $documentalistPending = ifrMakePendingFile($documentalist, 'wav', ifrWav('NOUVEAU-DOC'), 'doc.wav');

    Queue::fake();
    actingAs($documentalist);
    app(ItemFileReplacer::class)->replace($documentalistItem, $documentalistPending);

    expect($documentalistItem->fresh()->file_name)->toBe('doc.wav');
});

/*
|--------------------------------------------------------------------------
| AC9 — validation du PendingFile et de l'extension
|--------------------------------------------------------------------------
*/

test('AC9 refuse un PendingFile incomplet, absent, incohérent ou d’un autre utilisateur', function () {
    $item = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_VALID',
        'content' => ifrWav('ANCIEN'),
    ]);

    $oldPath = $item->file_path;
    actingAs($this->admin);

    $notCompleted = ifrMakePendingFile($this->admin, 'wav', ifrWav('X'), null, [
        'upload_status' => PendingFile::STATUS_UPLOADING,
    ]);

    expect(fn () => app(ItemFileReplacer::class)->replace($item, $notCompleted))
        ->toThrow(ItemFileReplacementException::class);

    $missing = ifrMakePendingFile($this->admin, 'wav', ifrWav('Y'));
    Storage::disk('local')->delete($missing->file_path);

    expect(fn () => app(ItemFileReplacer::class)->replace($item, $missing))
        ->toThrow(ItemFileReplacementException::class);

    $badSize = ifrMakePendingFile($this->admin, 'wav', ifrWav('Z'), null, [
        'file_size' => 999999,
    ]);

    expect(fn () => app(ItemFileReplacer::class)->replace($item, $badSize))
        ->toThrow(ItemFileReplacementException::class);

    $badChecksum = ifrMakePendingFile($this->admin, 'wav', ifrWav('W'), null, [
        'client_signature' => md5('autre-contenu'),
    ]);

    expect(fn () => app(ItemFileReplacer::class)->replace($item, $badChecksum))
        ->toThrow(ItemFileReplacementException::class);

    expect($item->fresh()->file_path)->toBe($oldPath)
        ->and($item->fresh()->md5)->toBeNull()
        ->and(PendingFile::find($notCompleted->id))->not->toBeNull();

    Storage::disk('original_medias')->assertExists($oldPath);

    // Propriété du PendingFile : un non super-admin ne peut pas utiliser le
    // fichier d'un autre utilisateur.
    $creator = User::factory()->chercheur()->create();
    $creatorItem = ifrMakeItem($this->collection, $creator, [
        'code' => 'IFR_VALID_OWNER',
        'content' => ifrWav('ANCIEN-CREATEUR'),
    ]);
    $otherUser = User::factory()->chercheur()->create();
    $foreign = ifrMakePendingFile($otherUser, 'wav', ifrWav('V'));

    actingAs($creator);

    expect(fn () => app(ItemFileReplacer::class)->replace($creatorItem, $foreign))
        ->toThrow(ItemFileReplacementException::class);

    expect(PendingFile::find($foreign->id))->not->toBeNull()
        ->and($creatorItem->fresh()->file_name)->toBe('IFR_VALID_OWNER.wav');
});

test('AC9 refuse une extension non autorisée par le type de média', function () {
    $itemType = ItemType::create([
        'name' => 'Type PDF',
        'suffix' => 'PDF',
        'allowed_extensions' => ['pdf'],
        'is_active' => true,
        'created_by' => $this->admin->id,
    ]);

    $item = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_TYPE',
        'content' => ifrWav('ANCIEN'),
        'is_sub' => true,
        'item_type_id' => $itemType->id,
    ]);

    $pending = ifrMakePendingFile($this->admin, 'wav', ifrWav('NOUVEAU'), 'refuse.wav');

    actingAs($this->admin);

    expect(fn () => app(ItemFileReplacer::class)->replace($item, $pending))
        ->toThrow(ItemFileReplacementException::class);

    expect($item->fresh()->file_extension)->toBe('wav')
        ->and(PendingFile::find($pending->id))->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| AC10 — nettoyage du PendingFile
|--------------------------------------------------------------------------
*/

test('AC10 supprime le PendingFile et son fichier local après succès', function () {
    $item = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_CLEAN',
        'content' => ifrWav('ANCIEN'),
    ]);

    $pending = ifrMakePendingFile($this->admin, 'wav', ifrWav('NOUVEAU'), 'nouveau.wav');
    $pendingId = $pending->id;
    $pendingPath = $pending->file_path;

    Queue::fake();
    actingAs($this->admin);

    app(ItemFileReplacer::class)->replace($item, $pending);

    expect(PendingFile::find($pendingId))->toBeNull();
    Storage::disk('local')->assertMissing($pendingPath);
});

/*
|--------------------------------------------------------------------------
| AC11 — audit
|--------------------------------------------------------------------------
*/

test('AC11 trace l’ancien et le nouveau fichier dans l’audit de l’item', function () {
    $item = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_AUDIT',
        'content' => ifrWav('ANCIEN-AUDIT'),
    ]);

    $oldPath = $item->file_path;
    $newContent = ifrMp3('NOUVEAU-AUDIT');
    $pending = ifrMakePendingFile($this->admin, 'mp3', $newContent, 'audit.mp3');

    Queue::fake();
    actingAs($this->admin);

    $result = app(ItemFileReplacer::class)->replace($item, $pending);

    $audit = $item->fresh()->audits()->latest('id')->first();

    expect($audit)->not->toBeNull()
        ->and($audit->event)->toBe('updated')
        ->and($audit->old_values['file_path'])->toBe($oldPath)
        ->and($audit->new_values['file_path'])->toBe($result->newPath)
        ->and($audit->new_values['md5'])->toBe(md5($newContent));
});

/*
|--------------------------------------------------------------------------
| AC12 — corrections processFileUpload() et ItemObserver
|--------------------------------------------------------------------------
*/

test('AC12a processFileUpload ne perd pas le fichier quand ancien et nouveau chemins coïncident', function () {
    $newContent = ifrWav('NOUVEAU-SOURCE');
    $item = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_BUG',
        'content' => ifrWav('ANCIEN-CIBLE'),
    ]);

    $targetPath = $item->file_path;
    $sourcePath = 'items/2020/01/01/IFR_BUG.wav';
    Storage::disk('original_medias')->put($sourcePath, $newContent);

    $item->file_path = $sourcePath;
    $item->save();

    $fresh = $item->fresh();

    expect($fresh->file_path)->toBe($targetPath);
    Storage::disk('original_medias')->assertExists($targetPath);
    expect(Storage::disk('original_medias')->get($targetPath))->toBe($newContent);
    Storage::disk('original_medias')->assertMissing($sourcePath);
});

test('AC12b ItemObserver déclenche le traitement média via wasChanged', function () {
    $item = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_OBS',
        'content' => ifrWav('ANCIEN'),
    ]);

    Queue::fake();

    // Chemin différent de l'ancien : wasChanged('file_path') doit être vrai.
    $item->file_path = 'items/2021/02/03/IFR_OBS.wav';
    $item->save();

    // Isole le dispatch éventuel de la sauvegarde pour ne compter que
    // celui de l'observer invoqué explicitement.
    Queue::fake();

    (new ItemObserver)->updated($item);

    Queue::assertPushed(GenerateDiffusionMedia::class, 1);
    Queue::assertPushed(GenerateAudiowaveform::class, 1);
});

test('AC12b ItemObserver ne déclenche rien sans changement de file_path', function () {
    $item = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_OBS_NEG',
        'content' => ifrWav('ANCIEN'),
    ]);

    Queue::fake();

    $item->title = 'Titre inchangé structurellement';
    $item->save();

    (new ItemObserver)->updated($item);

    Queue::assertNothingPushed();
});

/*
|--------------------------------------------------------------------------
| AC13 — action Filament visible / masquée (best effort)
|--------------------------------------------------------------------------
*/

test('AC13 le gestionnaire d’envoi remplace le fichier d’un item existant', function () {
    $item = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_UPLOAD',
        'content' => ifrWav('ANCIEN'),
    ]);

    $pending = ifrMakePendingFile($this->admin, 'wav', ifrWav('NOUVEAU'), 'nouveau.wav');

    Queue::fake();
    actingAs($this->admin);

    Livewire::test(UploadedFilesTable::class)
        ->assertCanSeeTableRecords([$pending])
        ->callTableAction('replaceItemFile', $pending, data: [
            'item_id' => $item->id,
            'delete_old' => false,
        ])
        ->assertHasNoTableActionErrors();

    expect($item->fresh()->file_name)->toBe('nouveau.wav')
        ->and($item->fresh()->md5)->toBe(md5(ifrWav('NOUVEAU')))
        ->and(PendingFile::find($pending->id))->toBeNull();
});

test('AC13 l’action « Remplacer le fichier » est visible pour un utilisateur autorisé et masquée sinon', function () {
    $item = ifrMakeItem($this->collection, $this->admin, [
        'code' => 'IFR_UI',
        'content' => ifrWav('ANCIEN'),
    ]);

    actingAs($this->admin);

    Livewire::test(ViewItem::class, ['record' => $item->getRouteKey()])
        ->assertActionVisible('replaceFile');

    $outsider = User::factory()->chercheur()->create();
    actingAs($outsider);

    Livewire::test(ViewItem::class, ['record' => $item->getRouteKey()])
        ->assertActionHidden('replaceFile');
});
