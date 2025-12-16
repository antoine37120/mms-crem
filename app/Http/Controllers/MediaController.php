<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\MediaVariation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    /**
     * Serve the master playlist (HLS .m3u8).
     */
    public function playlist(string $code)
    {
        $item = Item::where('code', $code)->firstOrFail();

        // Find the HLS variation
        $variation = MediaVariation::where('item_id', $item->id)
            ->where('is_streaming', true)
            ->firstOrFail();

        // The file_path in DB is like "items/CODE/diffusion/CODE.m3u8"
        // We serve it from the disk configured in settings (default: diffusion_medias)
        // Since we don't have access to dynamic settings here easily without querying DB or file,
        // we assume the variation->disk field is correct.

        $disk = $variation->disk;
        $path = $variation->file_path;

        if (!Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($path, null, [
            'Content-Type' => 'application/x-mpegURL',
            'Access-Control-Allow-Origin' => '*', // Adjust for CORS
        ]);
    }

    /**
     * Serve HLS segments (.ts files).
     */
    public function segment(string $code, string $segment)
    {
        // Security check: ensure segment belongs to the code
        if (!str_starts_with($segment, $code . '_')) {
            abort(403);
        }

        $item = Item::where('code', $code)->firstOrFail();

        // We need to know the disk. We can look up any streaming variation for this item.
        $variation = MediaVariation::where('item_id', $item->id)
            ->where('is_streaming', true)
            ->firstOrFail();

        $disk = $variation->disk;
        // Construct path: items/CODE/diffusion/SEGMENT
        // Note: The structure in GenerateDiffusionMedia was 'items/CODE/diffusion/CODE_xxx.ts'
        $path = 'items/' . $code . '/diffusion/' . $segment;

        if (!Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($path, null, [
            'Content-Type' => 'video/MP2T',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Serve Waveform JSON.
     */
    public function waveform(string $code)
    {
        $item = Item::where('code', $code)->firstOrFail();

        $variation = MediaVariation::where('item_id', $item->id)
            ->where('profile_name', 'waveform_json')
            ->firstOrFail();

        $disk = $variation->disk;
        $path = $variation->file_path;

        if (!Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($path, null, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }
}
