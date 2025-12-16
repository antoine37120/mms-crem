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
     * Serve the master media file.
     * Logic:
     * 1. If HLS streaming variation exists -> Serve .m3u8 playlist.
     * 2. Else -> Serve original file (PDF, Image, MP3, etc.).
     */
    public function master(string $code)
    {
        $item = Item::where('code', $code)->firstOrFail();

        // 1. Try to find a Streaming Variation (HLS)
        $streamingVariation = MediaVariation::where('item_id', $item->id)
            ->where('is_streaming', true)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($streamingVariation) {
            $disk = $streamingVariation->disk;
            $path = $streamingVariation->file_path;

            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->response($path, null, [
                    'Content-Type' => 'application/x-mpegURL',
                    'Access-Control-Allow-Origin' => '*',
                ]);
            }
        }

        // 2. Fallback: Serve Original File (PDF, Image, non-transcoded Audio/Video)
        // Note: Assuming 'original_medias' disk contains the source.
        $disk = 'original_medias';
        $path = $item->file_path;

        if (!$path || !Storage::disk($disk)->exists($path)) {
            abort(404, 'File not found');
        }

        // Determine MIME type if possible, or let Laravel/Storage detect it
        // Basic CORS support
        return Storage::disk($disk)->response($path, null, [
            'Access-Control-Allow-Origin' => '*',
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
