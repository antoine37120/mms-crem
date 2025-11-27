<?php

namespace App\Observers;

use App\Models\Item;
use App\Models\Collection;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class ItemObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Handle the Item "created" event.
     */
    public function created(Item $item): void
    {
        $this->updateCollectionCounter($item, 1);
    }

    /**
     * Handle the Item "updated" event.
     */
    public function updated(Item $item): void
    {
        // Si is_sub a changé, on doit recalculer
        if ($item->isDirty('is_sub')) {
            $this->decrementCounter($item, $item->getOriginal('is_sub'));
            $this->incrementCounter($item, $item->is_sub);
        }

        // Si itemable a changé (changement de collection)
        if ($item->isDirty('itemable_id') || $item->isDirty('itemable_type')) {
            // Décrémenter l'ancienne collection
            $originalType = $item->getOriginal('itemable_type');
            $originalId = $item->getOriginal('itemable_id');

            if ($originalType === Collection::class) {
                $oldCollection = Collection::find($originalId);
                if ($oldCollection) {
                    $this->updateCounter($oldCollection, $item->getOriginal('is_sub') ?? false, -1);
                }
            }

            // Incrémenter la nouvelle collection
            $this->updateCollectionCounter($item, 1);
        }
    }

    /**
     * Handle the Item "deleted" event.
     */
    public function deleted(Item $item): void
    {
        $this->updateCollectionCounter($item, -1);
    }

    /**
     * Handle the Item "restored" event.
     */
    public function restored(Item $item): void
    {
        $this->updateCollectionCounter($item, 1);
    }

    /**
     * Handle the Item "force deleted" event.
     */
    public function forceDeleted(Item $item): void
    {
        // Si l'item n'était pas déjà soft deleted, on décrémente
        if ($item->deleted_at === null) {
            $this->updateCollectionCounter($item, -1);
        }
    }

    /**
     * Met à jour le compteur de la collection associée
     */
    private function updateCollectionCounter(Item $item, int $delta): void
    {
        if ($item->itemable_type !== Collection::class) {
            return;
        }

        $collection = $item->itemable;
        if (!$collection instanceof Collection) {
            return;
        }

        $this->updateCounter($collection, $item->is_sub ?? false, $delta);
    }

    /**
     * Met à jour le compteur approprié selon is_sub
     */
    private function updateCounter(Collection $collection, bool $isSub, int $delta): void
    {
        if ($isSub) {
            $collection->increment('secondary_items_count', $delta);
        } else {
            $collection->increment('main_items_count', $delta);
        }

        // S'assurer que les compteurs ne sont jamais négatifs
        if ($collection->main_items_count < 0) {
            $collection->update(['main_items_count' => 0]);
        }
        if ($collection->secondary_items_count < 0) {
            $collection->update(['secondary_items_count' => 0]);
        }
    }

    /**
     * Incrémente le compteur approprié
     */
    private function incrementCounter(Item $item, ?bool $isSub): void
    {
        if ($item->itemable_type !== Collection::class) {
            return;
        }

        $collection = $item->itemable;
        if ($collection instanceof Collection) {
            $this->updateCounter($collection, $isSub ?? false, 1);
        }
    }

    /**
     * Décrémente le compteur approprié
     */
    private function decrementCounter(Item $item, ?bool $isSub): void
    {
        if ($item->itemable_type !== Collection::class) {
            return;
        }

        $collection = $item->itemable;
        if ($collection instanceof Collection) {
            $this->updateCounter($collection, $isSub ?? false, -1);
        }
    }
}
