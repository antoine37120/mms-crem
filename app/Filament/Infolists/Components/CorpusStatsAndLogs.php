<?php

namespace App\Filament\Infolists\Components;

use Filament\Infolists\Components\Entry;
use Illuminate\Support\HtmlString;
use App\Models\Corpus;
use App\Filament\Resources\Collections\CollectionResource;
use App\Filament\Resources\Items\ItemResource;


class CorpusStatsAndLogs extends Entry
{
    protected string $view = 'filament.infolists.components.corpus-stats-and-logs';


    public function getState(): mixed
    {
        $corpus = $this->getRecord();

        if (!$corpus instanceof Corpus) {
            return null;
        }

        // Calcul des statistiques
        $collectionsCount = $corpus->collections()->count();
        $itemsCount = $corpus->items()->count();
        $totalItemsCount = $itemsCount + $corpus->collections()
                ->withCount('items')
                ->get()
                ->sum('items_count');

        // Calcul de la taille totale
        $directItemsSize = $corpus->items()->sum('file_size') ?? 0;
        $collectionsItemsSize = $corpus->collections()
            ->with('items')
            ->get()
            ->sum(function ($collection) {
                return $collection->items->sum('file_size');
            });
        $totalSize = $directItemsSize + $collectionsItemsSize;

        // Détermination du statut
        $status = $this->getStatus($collectionsCount, $itemsCount);
        $statusColor = $this->getStatusColor($collectionsCount, $itemsCount);

        // Activité récente (30 derniers jours)
        $recentActivity = [];

        // Collections récentes avec liens
        $recentCollections = $corpus->collections()
            ->where('collections.created_at', '>=', now()->subDays(30))
            ->with('creator')
            ->latest()
            ->limit(5)
            ->get();

        foreach ($recentCollections as $collection) {
            $recentActivity[] = [
                'date' => $collection->created_at->format('d/m/Y à H:i'),
                'action' => "Nouvelle collection : {$collection->code}",
                'user' => $collection->creator->name ?? 'Utilisateur inconnu',
                'type' => 'collection',
                'url' => CollectionResource::getUrl('view', ['record' => $collection->id])
            ];
        }

        // Items récents avec liens
        $recentItems = $corpus->items()
            ->where('created_at', '>=', now()->subDays(30))
            ->with(['uploader'])
            ->latest()
            ->limit(5)
            ->get();

        foreach ($recentItems as $item) {
            $recentActivity[] = [
                'date' => $item->created_at->format('d/m/Y à H:i'),
                'action' => "Nouveau fichier : {$item->file_name}",
                'user' => $item->uploader->name ?? 'Utilisateur inconnu',
                'type' => 'item',
                'url' => ItemResource::getUrl('view', ['record' => $item->id])
            ];
        }

        // Tri par date (plus récent d'abord)
        usort($recentActivity, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        // Collections avec liens pour la vue
        $collectionsWithLinks = $corpus->collections()->limit(5)->get()->map(function($collection) {
            return [
                'id' => $collection->id,
                'code' => $collection->code,
                'title' => $collection->title,
                'items_count' => $collection->items()->count(),
                'url' => CollectionResource::getUrl('view', ['record' => $collection->id])
            ];
        });

        return [
            'collections_count' => $collectionsCount,
            'items_count' => $itemsCount,
            'total_items_count' => $totalItemsCount,
            'total_size' => $this->formatFileSize($totalSize),
            'status' => $status,
            'status_color' => $statusColor,
            'recent_activity' => array_slice($recentActivity, 0, 10), // Limiter à 10 activités
        ];

    }

    private function getStatus(int $collectionsCount, int $itemsCount): string
    {
        if ($itemsCount > 0 && $collectionsCount > 0) {
            return 'Actif';
        }

        if ($collectionsCount > 0) {
            return 'En cours';
        }

        return 'Vide';
    }

    private function getStatusColor(int $collectionsCount, int $itemsCount): string
    {
        if ($itemsCount > 0 || $collectionsCount > 0) {
            return 'success';
        }

        return 'warning';
    }

    private function formatFileSize(?int $bytes): string
    {
        if (!$bytes) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        $size = $bytes / pow(1024, $power);
        return round($size, 2) . ' ' . $units[$power];
    }
}
