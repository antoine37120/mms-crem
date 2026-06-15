<?php

namespace App\Http\Controllers;

use App\Jobs\RecordItemView;
use App\Models\Collection;
use App\Models\Corpus;
use App\Models\Fond;
use App\Models\Item;
use App\Models\MediaVariation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /**
     * Détermine si une entité est accessible publiquement selon son public_access
     * et celui de sa hiérarchie (pour les items non-sub).
     */
    private function isPubliclyAccessible(\Illuminate\Database\Eloquent\Model $entity): bool
    {
        return is_publicly_accessible($entity);
    }

    /**
     * Autorise l'accès par token HMAC obligatoire, puis crée une session PHP.
     */
    private function authorizeAccess(Request $request, \Illuminate\Database\Eloquent\Model $entity)
    {
        // 1. TOUJOURS vérifier le token d'abord
        $token = $request->query('token');
        if (! $token) {
            abort(403, "Token d'accès requis");
        }

        $code = $entity->code ?? null;
        if (! $code || ! verify_media_token($token, $code)) {
            abort(403, "Token d'accès invalide ou expiré");
        }

        // 2. Déduire le client depuis le payload du token
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode($parts[0] ?? ''), true);
        $client = $payload ? \App\Models\MediaClient::where('app_id', $payload['app'] ?? '')->where('is_active', true)->first() : null;

        // 3. Si l'entité n'est PAS publique, vérifier que le client a le droit
        if (! $this->isPubliclyAccessible($entity)) {
            if (! $client || ! $client->can_access_not_public) {
                abort(403, 'Accès restreint — client non autorisé pour ce contenu');
            }
        }

        // 4. Token valide + droits OK → créer la session pour les segments HLS
        session([
            'media_access_code' => $code,
            'media_access_at' => time(),
        ]);
    }

    /**
     * Vérifie la session PHP (pour les segments HLS sans ?token=).
     */
    private function authorizeSession(Request $request, \Illuminate\Database\Eloquent\Model $entity)
    {
        $code = $entity->code ?? null;

        if (session('media_access_code') !== $code) {
            abort(403, 'Session invalide');
        }

        if (session('media_access_at') < time() - 900) {
            abort(403, 'Session expirée');
        }

        session(['media_access_at' => time()]);
    }

    /**
     * Serve the master media file.
     * Logic:
     * 1. If HLS streaming variation exists -> Serve .m3u8 playlist.
     * 2. Else -> Serve original file (PDF, Image, MP3, etc.).
     */
    public function master(Request $request, string $code)
    {
        $item = Item::where('code', $code)->firstOrFail();
        $this->authorizeAccess($request, $item);

        // Record View
        RecordItemView::dispatch(
            $item->id,
            auth()->id(),
            auth()->check(),
            $request->ip(),
            $request->userAgent(),
            $request->header('referer')
        );

        // 1. Try to find a Streaming Variation (HLS)
        $streamingVariation = MediaVariation::where('item_id', $item->id)
            ->where('is_streaming', true)
            ->orderBy('created_at', 'desc')
            ->first();

        if ($streamingVariation) {
            $disk = $streamingVariation->disk;
            $path = $streamingVariation->file_path;

            if (Storage::disk($disk)->exists($path)) {
                $playlistContent = Storage::disk($disk)->get($path);
                $token = $request->query('token');

                if ($token) {
                    $playlistContent = preg_replace(
                        '/^([a-zA-Z0-9_][^#].*?)\.ts$/m',
                        '$1.ts?token=' . urlencode($token),
                        $playlistContent
                    );
                }

                return response($playlistContent, 200, [
                    'Content-Type' => 'application/x-mpegURL',
                    'Access-Control-Allow-Origin' => '*',
                ]);
            }
        }

        // 2. Fallback: Serve Original File (PDF, Image, non-transcoded Audio/Video)
        // Note: Assuming 'original_medias' disk contains the source.
        $disk = 'original_medias';
        $path = $item->file_path;

        if (! $path || ! Storage::disk($disk)->exists($path)) {
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
    public function segment(Request $request, string $code, string $segment)
    {
        // Security check: ensure segment belongs to the code
        if (! str_starts_with($segment, $code.'_')) {
            abort(403);
        }

        $item = Item::where('code', $code)->firstOrFail();

        // Essayer la session d'abord (même domaine), puis le token en fallback (cross-domaine)
        if (session('media_access_code') !== $item->code) {
            $token = $request->query('token');
            if (! $token || ! verify_media_token($token, $item->code)) {
                abort(403, "Token d'accès requis");
            }
        } else {
            if (session('media_access_at') < time() - 900) {
                abort(403, 'Session expirée');
            }
            session(['media_access_at' => time()]);
        }

        // We need to know the disk. We can look up any streaming variation for this item.
        $variation = MediaVariation::where('item_id', $item->id)
            ->where('is_streaming', true)
            ->firstOrFail();

        $disk = $variation->disk;
        // Construct path: items/CODE/diffusion/SEGMENT
        $resolver = app(\App\Services\MediaVariationPathResolver::class);
        $path = $resolver->segmentDir($item).'/'.$segment;

        if (! Storage::disk($disk)->exists($path)) {
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
    public function waveform(Request $request, string $code)
    {
        $item = Item::where('code', $code)->firstOrFail();
        $this->authorizeAccess($request, $item);

        $variation = MediaVariation::where('item_id', $item->id)
            ->where('profile_name', 'waveform_json')
            ->firstOrFail();

        $disk = $variation->disk;
        $path = $variation->file_path;

        if (! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($path, null, [
            'Content-Type' => 'application/json',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * List all media variations for a specific item, filtered by item type suffix.
     */
    public function variations(Request $request, string $code, string $type)
    {
        // 0. Find the parent object by code (can be Item, Collection, Corpus or Fond)
        $parent = Item::where('code', $code)->first()
            ?? Collection::where('code', $code)->first()
            ?? Corpus::where('code', $code)->first()
            ?? Fond::where('code', $code)->first();

        if (! $parent) {
            abort(404, 'Parent not found with code: '.$code);
        }

        // Pas de vérification de token : cette route liste les fichiers disponibles
        // (métadonnées, pas de contenu sensible). Le token est ajouté côté client
        // par l'appelant (Omeka) sur les URLs individuelles retournées.
        $variationsList = [];

        // 1. Find all items associated with this parent that match the suffix
        $matchedItems = Item::with(['itemType', 'creator', 'uploader', 'mediaVariations'])
            ->where(function ($query) use ($parent) {
                if ($parent instanceof Item) {
                    $query->where('id', $parent->id);
                }
                $query->orWhere(function ($q) use ($parent) {
                    $q->where('itemable_id', $parent->id)
                        ->where('itemable_type', get_class($parent));
                });
            })
            ->whereHas('itemType', function ($query) use ($type) {
                $query->where('suffix', $type);
            })
            ->get();

        foreach ($matchedItems as $item) {
            // Include the item itself (original)
            $variationsList[] = $this->formatMediaEntry($item, null);

            // Include its technical variations (HLS, etc.)
            foreach ($item->mediaVariations as $variation) {
                if ($variation->status->value === 'ready') {
                    $variationsList[] = $this->formatMediaEntry($item, $variation);
                }
            }
        }

        return response()->json($variationsList, 200, [
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Serve a specific variation file by its profile name.
     */
    public function serve(Request $request, string $code, string $profile)
    {
        $item = Item::where('code', $code)->firstOrFail();
        $this->authorizeAccess($request, $item);

        $variation = MediaVariation::where('item_id', $item->id)
            ->where('profile_name', $profile)
            ->where('status', 'ready')
            ->firstOrFail();

        $disk = $variation->disk;
        $path = $variation->file_path;

        if (! Storage::disk($disk)->exists($path)) {
            abort(404, 'File not found on disk');
        }

        return Storage::disk($disk)->response($path, null, [
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /**
     * Format a media entry (original or variation) for JSON response.
     */
    protected function formatMediaEntry(Item $item, ?MediaVariation $variation): array
    {
        $isOriginal = is_null($variation);

        return [
            'code' => $item->code,
            'title' => $item->title,
            'language_code' => $item->language_code,
            'language_label' => $item->language_code ? \Locale::getDisplayLanguage($item->language_code, $item->language_code) : null,
            'file_name' => $isOriginal ? $item->file_name : basename($variation->file_path),
            'file_size' => $isOriginal ? $item->file_size : $variation->file_size,
            'file_type' => $isOriginal ? $item->file_type : $variation->mime_type,
            'file_extension' => $isOriginal ? $item->file_extension : pathinfo($variation->file_path, PATHINFO_EXTENSION),
            'duration' => $item->duration,
            'upload_date' => $item->upload_date?->toIso8601String() ?? $item->created_at?->toIso8601String(),
            'uploaded_by' => $item->uploader?->name,
            'created_by' => $item->creator?->name,
            'md5' => $isOriginal ? $item->md5 : null, // MD5 typically for original
            'url' => $this->determineUrl($item, $variation),
        ];
    }

    /**
     * Determine the URL for a media entry.
     */
    protected function determineUrl(Item $item, ?MediaVariation $variation): string
    {
        if (is_null($variation)) {
            return route('media.master', ['code' => $item->code]);
        }

        if ($variation->is_streaming) {
            return route('media.master', ['code' => $item->code]);
        }

        if ($variation->profile_name === 'waveform_json') {
            return route('media.waveform', ['code' => $item->code]);
        }

        return route('media.variation', ['code' => $item->code, 'profile' => $variation->profile_name]);
    }
}
