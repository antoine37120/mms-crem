<?php

namespace App\Filament\Infolists\Components;

use App\Filament\Resources\Collections\CollectionResource;
use App\Filament\Resources\Items\ItemResource;
use App\Models\Collection;
use Filament\Infolists\Components\Entry;

class CollectionStatsAndLogs extends Entry
{
    protected string $view = 'filament.infolists.components.collection-stats-and-logs';



    public function getState(): mixed
    {
        $collection = $this->getRecord();

        if (!$collection instanceof Collection) {
            return null;
        }

        // Calcul des statistiques
        $itemsCount = $collection->mainItems()->count();
        $totalItemsCount = $collection->items()->count();

        // Calcul de la taille totale
        $totalSize = $collection->allItems()->sum('file_size') ?? 0;

        // Détermination du statut
        $status = $this->getStatus($totalItemsCount);
        $statusColor = $this->getStatusColor($totalItemsCount);

        // Activité récente (30 derniers jours)
        $recentActivity = [];

        // Collections récentes avec liens
        $recentitems = $collection->allItems()
            ->where('created_at', '>=', now()->subDays(30))
            ->with('creator')
            ->latest()
            ->limit(5)
            ->get();

        foreach ($recentitems as $recentitem) {
            $recentActivity[] = [
                'date' => $recentitem->created_at->format('d/m/Y à H:i'),
                'action' => "Nouveau Item : {$recentitem->code}",
                'user' => $recentitem->creator->name ?? 'Utilisateur inconnu',
                'type' => 'item',
                'url' => ItemResource::getUrl('view', ['record' => $recentitem->id])
            ];
        }


        return [
            'items_count' => $itemsCount,
            'total_items_count' => $totalItemsCount,
            'total_size' => $this->formatFileSize($totalSize),
            'status' => $status,
            'status_color' => $statusColor,
            'recent_activity' => array_slice($recentActivity, 0, 10), // Limiter à 10 activités
        ];

    }

    private function getStatus(int $itemsCount): string
    {
        if ($itemsCount > 0) {
            return 'Actif';
        }

        return 'Vide';
    }

    private function getStatusColor(int $itemsCount): string
    {
        if ($itemsCount > 0) {
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
