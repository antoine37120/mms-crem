<?php

use App\Enums\UserRole;
use App\Filament\Pages\MediaSettings;
use App\Models\User;
use Livewire\Livewire;

it('can access media settings page as admin', function () {
    $user = User::factory()->create([
        'role' => UserRole::ADMINISTRATEUR,
        'admin_access' => true,
    ]);

    $this->actingAs($user);

    $this->get(MediaSettings::getUrl())->assertSuccessful();
});

it('cannot access media settings page as non-admin', function () {
    $user = User::factory()->create([
        'role' => UserRole::CHERCHEUR,
        'admin_access' => false,
    ]);

    $this->actingAs($user);

    $this->get(MediaSettings::getUrl())->assertForbidden();
});

it('has all encoding fields on the media settings page', function () {
    $user = User::factory()->create([
        'role' => UserRole::ADMINISTRATEUR,
        'admin_access' => true,
    ]);

    $this->actingAs($user);

    Livewire::test(MediaSettings::class)
        ->assertFormFieldExists('scan_path')
        ->assertFormFieldExists('ffmpeg_path')
        ->assertFormFieldExists('ffprobe_path')
        ->assertFormFieldExists('audiowaveform_path')
        ->assertFormFieldExists('diffusion_disk')
        ->assertFormFieldExists('video_codec')
        ->assertFormFieldExists('video_preset')
        ->assertFormFieldExists('video_crf')
        ->assertFormFieldExists('video_audio_bitrate')
        ->assertFormFieldExists('video_hls_time')
        ->assertFormFieldExists('audio_codec')
        ->assertFormFieldExists('audio_bitrate')
        ->assertFormFieldExists('audio_channels')
        ->assertFormFieldExists('audio_hls_time')
        ->assertFormFieldExists('waveform_pixels_per_second')
        ->assertFormFieldExists('waveform_bits');
});
