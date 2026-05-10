<?php

use App\Filament\Resources\ItemTypes\Pages\CreateItemType;
use App\Models\ItemType;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('item type allows all extensions when allowed_extensions is null', function () {
    $itemType = ItemType::factory()->create([
        'allowed_extensions' => null,
    ]);

    expect($itemType->allowed_extensions)->toBeNull();
    expect($itemType->isExtensionAllowed('pdf'))->toBeTrue();
    expect($itemType->isExtensionAllowed('any'))->toBeTrue();
});

test('item type allows all extensions when allowed_extensions is empty string', function () {
    $itemType = ItemType::factory()->create([
        'allowed_extensions' => '',
    ]);

    expect($itemType->allowed_extensions)->toBe('');
    expect($itemType->isExtensionAllowed('pdf'))->toBeTrue();
});

test('item type restricts extensions when allowed_extensions is set', function () {
    $itemType = ItemType::factory()->create([
        'allowed_extensions' => 'pdf,jpg',
    ]);

    expect($itemType->isExtensionAllowed('pdf'))->toBeTrue();
    expect($itemType->isExtensionAllowed('jpg'))->toBeTrue();
    expect($itemType->isExtensionAllowed('png'))->toBeFalse();
});

test('item type isExtensionAllowed handles spaces and case sensitivity', function () {
    $itemType = ItemType::factory()->create([
        'allowed_extensions' => 'pdf, JPG , PNG',
    ]);

    expect($itemType->isExtensionAllowed('pdf'))->toBeTrue();
    expect($itemType->isExtensionAllowed('jpg'))->toBeTrue();
    expect($itemType->isExtensionAllowed('JPG'))->toBeTrue();
    expect($itemType->isExtensionAllowed('png'))->toBeTrue();
    expect($itemType->isExtensionAllowed('gif'))->toBeFalse();
});

test('item type form initializes created_by with current user', function () {
    actingAs($this->user);

    Livewire::test(CreateItemType::class)
        ->assertFormSet([
            'created_by' => $this->user->id,
        ]);
});
