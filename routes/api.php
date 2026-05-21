<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Routes pour l'explorateur hiérarchique
Route::prefix('hierarchy')->group(function () {
    Route::get('/fonds', [App\Http\Controllers\Api\HierarchyController::class, 'getFonds']);
    Route::get('/fonds/{fond}/corpuses', [App\Http\Controllers\Api\HierarchyController::class, 'getCorpuses']);
    Route::get('/corpuses/{corpus}/collections', [App\Http\Controllers\Api\HierarchyController::class, 'getCollections']);
    Route::get('/collections/{collection}/items', [App\Http\Controllers\Api\HierarchyController::class, 'getItems']);
    Route::get('/{type}/{id}/direct-items', [App\Http\Controllers\Api\HierarchyController::class, 'getDirectItems']);
    Route::get('/search', [App\Http\Controllers\Api\HierarchyController::class, 'search']);
    Route::get('/stats', [App\Http\Controllers\Api\HierarchyController::class, 'getStats']);
});

Route::get('/items/{item}/download', function (App\Models\Item $item) {
    if (! $item->file_path || ! Storage::exists($item->file_path)) {
        abort(404, 'Fichier non trouvé');
    }

    return Storage::download($item->file_path, $item->file_name);
})->name('api.items.download');
