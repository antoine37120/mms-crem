<?php

namespace App\Traits;

use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;

trait HasHierarchicalItems
{
    /**
     * Obtenir le modèle parent dans la hiérarchie
     */
    public function getParentModel()
    {
        return match (get_class($this)) {
            \App\Models\Corpus::class => $this->fond,
            \App\Models\Collection::class => $this->corpuses->first(),
            \App\Models\Item::class => $this->itemable->first(),
            default => null,
        };
    }


    /**
     * Relation polymorphique vers les items directement associés
     */
    public function items(): MorphMany
    {
        return $this->morphMany(Item::class, 'itemable');
    }

    /**
     * Items principaux (directement associés)
     */
    public function mainItems(): MorphMany
    {
        return $this->items()->where('is_sub', false);
    }

    /**
     * Items secondaires (directement associés)
     */
    public function secondaryItems(): MorphMany
    {
        return $this->items()->where('is_sub', true);
    }

    /**
     * Tous les items directs ET indirects (via recherche par code)
     */
    public function allItems(): Builder
    {
        return Item::where('code', 'LIKE', $this->getHierarchyPrefix() . '%')
            ->orderBy('code');
    }

    /**
     * Tous les items principaux directs ET indirects
     */
    public function allMainItems(): Builder
    {
        return $this->allItems()->where('is_sub', false);
    }

    /**
     * Tous les items secondaires directs ET indirects
     */
    public function allSecondaryItems(): Builder
    {
        return $this->allItems()->where('is_sub', true);
    }

    /**
     * Statistiques détaillées de l'entité
     */
    public function getStatsAttribute(): array
    {
        $cacheKey = $this->getStatsCacheKey();

        return Cache::remember($cacheKey, 300, function () {
            // ✅ CORRECTION : Utiliser fromSub() pour éviter le conflit ORDER BY + agrégation
            $subQuery = $this->allItems()
                ->select([
                    'id',
                    'is_sub',
                    'file_size',
                    'file_type',
                    'duration'
                ]);

            $stats = \DB::query()
                ->fromSub($subQuery, 'items_subset')
                ->selectRaw('
                    COUNT(*) as total_items,
                    SUM(CASE WHEN is_sub = 0 THEN 1 ELSE 0 END) as main_items,
                    SUM(CASE WHEN is_sub = 1 THEN 1 ELSE 0 END) as secondary_items,
                    COALESCE(SUM(file_size), 0) as total_size,
                    SUM(CASE WHEN file_type LIKE "audio%" THEN 1 ELSE 0 END) as audio_count,
                    SUM(CASE WHEN file_type LIKE "video%" THEN 1 ELSE 0 END) as video_count,
                    SUM(CASE WHEN file_type LIKE "image%" THEN 1 ELSE 0 END) as image_count,
                    SUM(CASE WHEN file_type LIKE "%pdf%" THEN 1 ELSE 0 END) as pdf_count,
                    AVG(CASE WHEN duration > 0 THEN duration ELSE NULL END) as avg_duration
                ')
                ->first();

            return [
                'total_items' => (int) ($stats->total_items ?? 0),
                'main_items' => (int) ($stats->main_items ?? 0),
                'secondary_items' => (int) ($stats->secondary_items ?? 0),
                'total_size' => (int) ($stats->total_size ?? 0),
                'audio_count' => (int) ($stats->audio_count ?? 0),
                'video_count' => (int) ($stats->video_count ?? 0),
                'image_count' => (int) ($stats->image_count ?? 0),
                'pdf_count' => (int) ($stats->pdf_count ?? 0),
                'avg_duration' => $stats->avg_duration ? round($stats->avg_duration, 2) : null,
            ];
        });
    }


    /**
     * Taille totale des fichiers (formatée)
     */
    public function getTotalFileSizeAttribute(): int
    {
        return $this->stats['total_size'];
    }

    /**
     * Taille formatée lisible
     */
    public function getFormattedTotalSizeAttribute(): string
    {
        return $this->formatFileSize($this->total_file_size);
    }

    /**
     * Items par type de fichier
     */
    public function getItemsByTypeAttribute(): array
    {
        $stats = $this->stats;

        return [
            'audio' => $stats['audio_count'],
            'video' => $stats['video_count'],
            'image' => $stats['image_count'],
            'pdf' => $stats['pdf_count'],
            'other' => $stats['total_items'] - $stats['audio_count'] - $stats['video_count'] - $stats['image_count'] - $stats['pdf_count'],
        ];
    }

    /**
     * Export des items avec informations détaillées
     */
    public function exportItems(array $columns = []): \Illuminate\Support\Collection
    {
        $defaultColumns = ['code', 'title', 'file_type', 'file_size', 'upload_date', 'uploaded_by'];
        $selectedColumns = empty($columns) ? $defaultColumns : $columns;

        return $this->allItems()
            ->with(['uploader:id,name'])
            ->get($selectedColumns)
            ->map(function ($item) {
                return [
                    'Code' => $item->code,
                    'Titre' => $item->title ?: 'Sans titre',
                    'Type' => $item->file_type,
                    'Taille' => $this->formatFileSize($item->file_size),
                    'Date upload' => $item->upload_date?->format('d/m/Y'),
                    'Uploadé par' => $item->uploader?->name ?: 'Inconnu',
                    'Durée' => $item->formatted_duration,
                ];
            });
    }

    /**
     * Recherche d'items dans cette hiérarchie
     */
    public function searchItems(string $query, array $filters = []): Builder
    {
        $builder = $this->allItems();

        // Recherche textuelle
        if (!empty($query)) {
            $builder->where(function ($q) use ($query) {
                $q->where('code', 'LIKE', "%{$query}%")
                    ->orWhere('title', 'LIKE', "%{$query}%")
                    ->orWhere('file_name', 'LIKE', "%{$query}%");
            });
        }

        // Filtres additionnels
        if (!empty($filters['file_type'])) {
            $builder->where('file_type', 'LIKE', $filters['file_type'] . '%');
        }

        if (!empty($filters['is_sub'])) {
            $builder->where('is_sub', $filters['is_sub']);
        }

        if (!empty($filters['uploaded_by'])) {
            $builder->where('uploaded_by', $filters['uploaded_by']);
        }

        if (!empty($filters['date_from'])) {
            $builder->whereDate('upload_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $builder->whereDate('upload_date', '<=', $filters['date_to']);
        }

        return $builder;
    }

    /**
     * ✅ NOUVELLE VERSION : Invalidation automatique du cache avec parents
     */
    public function clearStatsCache(): void
    {
        // Invalider son propre cache
        $cacheKeys = [
            $this->getStatsCacheKey(),
            $this->getStatsCacheKey() . '_simple'
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }

        // Invalider le cache de tous les parents automatiquement
        $this->getParentModel()?->clearStatsCache();
    }


    /**
     * Observer pour invalider le cache quand des items changent
     */
    public static function bootHasHierarchicalItems(): void
    {
        static::saved(function ($model) {
            $model->clearStatsCache();
        });

        static::deleted(function ($model) {
            $model->clearStatsCache();
        });
    }

    /**
     * Scope pour inclure les statistiques dans les requêtes
     */
    public function scopeWithItemStats($query): Builder
    {
        return $query->withCount([
            'items',
            'items as main_items_count' => function ($q) {
                $q->where('is_sub', false);
            },
            'items as secondary_items_count' => function ($q) {
                $q->where('is_sub', true);
            }
        ]);
    }

    /**
     * Obtenir le préfixe de hiérarchie pour la recherche LIKE
     * Doit être implémentée par chaque modèle
     */
    abstract protected function getHierarchyPrefix(): string;

    /**
     * Clé de cache pour les statistiques
     */
    protected function getStatsCacheKey(): string
    {
        return sprintf(
            '%s.%d.stats.%s',
            strtolower(class_basename($this)),
            $this->id,
            $this->updated_at?->timestamp ?? time()
        );
    }

    /**
     * Formatage des tailles de fichier
     */
    protected function formatFileSize(?int $bytes): string
    {
        if (!$bytes || $bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        $size = $bytes / pow(1024, $power);
        return round($size, 2) . ' ' . $units[$power];
    }
}
