<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Corpus;
use App\Models\Fond;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HierarchyController extends Controller
{
    public function getFonds(): JsonResponse
    {
        $fonds = Fond::withCount(['corpuses'])
            ->with(['creator:id,name'])
            ->withCount(['items as items_count' => function ($query) {
                $query->where('itemable_type', Fond::class);
            }])
            ->orderBy('code')
            ->get()
            ->map(function ($fond) {
                return [
                    'id' => $fond->id,
                    'code' => $fond->code,
                    'title' => $fond->title,
                    'corpuses_count' => $fond->corpuses_count,
                    'items_count' => $fond->items_count,
                    'created_by' => $fond->creator?->name,
                    'created_at' => $fond->created_at?->format('d/m/Y'),
                ];
            });

        return response()->json($fonds);
    }

    public function getCorpuses(Fond $fond): JsonResponse
    {
        $corpuses = $fond->corpuses()
            ->withCount(['collections'])
            ->withCount(['items as items_count' => function ($query) {
                $query->where('itemable_type', Corpus::class);
            }])
            ->with(['creator:id,name'])
            ->orderBy('code')
            ->get()
            ->map(function ($corpus) {
                return [
                    'id' => $corpus->id,
                    'code' => $corpus->code,
                    'title' => $corpus->title,
                    'collections_count' => $corpus->collections_count,
                    'items_count' => $corpus->items_count,
                    'created_by' => $corpus->creator?->name,
                    'created_at' => $corpus->created_at?->format('d/m/Y'),
                ];
            });

        return response()->json($corpuses);
    }

    public function getCollections(Corpus $corpus): JsonResponse
    {
        $collections = $corpus->collections()
            ->withCount(['items'])
            ->with(['creator:id,name'])
            ->orderBy('code')
            ->get()
            ->map(function ($collection) {
                return [
                    'id' => $collection->id,
                    'code' => $collection->code,
                    'title' => $collection->title,
                    'items_count' => $collection->items_count,
                    'created_by' => $collection->creator?->name,
                    'created_at' => $collection->created_at?->format('d/m/Y'),
                ];
            });

        return response()->json($collections);
    }

    public function getItems(Collection $collection): JsonResponse
    {
        $items = $collection->items()
            ->with(['creator:id,name', 'itemType:id,name'])
            ->orderBy('code')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'code' => $item->code,
                    'title' => $item->title,
                    'file_name' => $item->file_name,
                    'file_type' => $item->file_type,
                    'file_extension' => $item->file_extension,
                    'formatted_file_size' => $item->formatted_file_size,
                    'formatted_duration' => $item->formatted_duration,
                    'duration' => $item->duration,
                    'is_main' => $item->is_main,
                    'is_sub' => $item->is_sub,
                    'item_type' => $item->itemType?->name,
                    'created_by' => $item->creator?->name,
                    'created_at' => $item->created_at?->format('d/m/Y'),
                ];
            });

        return response()->json($items);
    }

    public function getDirectItems(Request $request, string $type, int $id): JsonResponse
    {
        $items = collect();

        switch ($type) {
            case 'fond':
                $fond = Fond::findOrFail($id);
                $items = Item::where('itemable_type', Fond::class)
                    ->where('itemable_id', $fond->id)
                    ->orderBy('code')
                    ->get();
                break;

            case 'corpus':
                $corpus = Corpus::findOrFail($id);
                $items = Item::where('itemable_type', Corpus::class)
                    ->where('itemable_id', $corpus->id)
                    ->orderBy('code')
                    ->get();
                break;

            case 'collection':
                $collection = Collection::findOrFail($id);
                $items = Item::where('itemable_type', Collection::class)
                    ->where('itemable_id', $collection->id)
                    ->orderBy('code')
                    ->get();
                break;

            default:
                return response()->json([]);
        }

        $formattedItems = $items->map(function ($item) {
            return [
                'id' => $item->id,
                'code' => $item->code,
                'title' => $item->title,
                'file_name' => $item->file_name,
                'file_type' => $item->file_type,
                'formatted_file_size' => $item->formatted_file_size,
                'formatted_duration' => $item->formatted_duration,
            ];
        });

        return response()->json($formattedItems);
    }

    public function search(Request $request): JsonResponse
    {
        $term = $request->get('q', '');
        $type = $request->get('type', 'all'); // all, fond, corpus, collection, item

        if (empty($term)) {
            return response()->json([]);
        }

        $results = [];

        if ($type === 'all' || $type === 'fond') {
            $fonds = Fond::where('code', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")
                ->limit(10)
                ->get()
                ->map(function ($fond) {
                    return [
                        'type' => 'fond',
                        'id' => $fond->id,
                        'code' => $fond->code,
                        'title' => $fond->title,
                        'label' => "{$fond->code}".($fond->title ? " - {$fond->title}" : ''),
                    ];
                });
            $results = array_merge($results, $fonds->toArray());
        }

        if ($type === 'all' || $type === 'corpus') {
            $corpuses = Corpus::where('code', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")
                ->with(['fond:id,code'])
                ->limit(10)
                ->get()
                ->map(function ($corpus) {
                    return [
                        'type' => 'corpus',
                        'id' => $corpus->id,
                        'code' => $corpus->code,
                        'title' => $corpus->title,
                        'parent' => $corpus->fond->code,
                        'label' => "{$corpus->fond->code}/{$corpus->code}".($corpus->title ? " - {$corpus->title}" : ''),
                    ];
                });
            $results = array_merge($results, $corpuses->toArray());
        }

        if ($type === 'all' || $type === 'collection') {
            $collections = Collection::where('code', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")
                ->with(['corpus.fond:id,code', 'corpus:id,code'])
                ->limit(10)
                ->get()
                ->map(function ($collection) {
                    return [
                        'type' => 'collection',
                        'id' => $collection->id,
                        'code' => $collection->code,
                        'title' => $collection->title,
                        'parent' => $collection->corpus->code,
                        'label' => "{$collection->corpus->fond->code}/{$collection->corpus->code}/{$collection->code}".($collection->title ? " - {$collection->title}" : ''),
                    ];
                });
            $results = array_merge($results, $collections->toArray());
        }

        if ($type === 'all' || $type === 'item') {
            $items = Item::where('code', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")
                ->orWhere('file_name', 'like', "%{$term}%")
                ->limit(10)
                ->get()
                ->map(function ($item) {
                    return [
                        'type' => 'item',
                        'id' => $item->id,
                        'code' => $item->code,
                        'title' => $item->title,
                        'file_name' => $item->file_name,
                        'label' => "{$item->code}".($item->title ? " - {$item->title}" : " - {$item->file_name}"),
                    ];
                });
            $results = array_merge($results, $items->toArray());
        }

        return response()->json($results);
    }

    public function getStats(): JsonResponse
    {
        $stats = [
            'fonds_count' => Fond::count(),
            'corpuses_count' => Corpus::count(),
            'collections_count' => Collection::count(),
            'items_count' => Item::count(),
            'total_size' => Item::sum('file_size'),
            'total_duration' => Item::sum('duration'),
        ];

        return response()->json($stats);
    }
}
