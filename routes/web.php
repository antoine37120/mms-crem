<?php

use App\Http\Controllers\MediaController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

// Generic route for accessing media (HLS playlist or direct file)
Route::get('/media/{code}', [MediaController::class, 'master'])->name('media.master');
// HLS Segments
Route::get('/media/{code}/{segment}', [MediaController::class, 'segment'])->where('segment', '.*\.ts$')->name('media.segment');
// Waveform data
Route::get('/media/{code}/waveform.json', [MediaController::class, 'waveform'])->name('media.waveform');

// List media variations by type
Route::get('/media/{code}/{type}', [MediaController::class, 'variations'])
    ->name('media.variations');

// Serve a specific variation file by profile name
Route::get('/media/{code}/variation/{profile}', [MediaController::class, 'serve'])->name('media.variation');

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});

require __DIR__.'/auth.php';
