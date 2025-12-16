<?php

namespace App\Traits;

use App\Enums\ItemProcessingStatus;
use App\Enums\ItemProcessingType;
use App\Models\ItemProcessingState;

trait HasProcessingState
{
    /**
     * Update or create a processing state for the item.
     */
    public function updateProcessingState(
        ItemProcessingType $type,
        ItemProcessingStatus $status,
        ?string $message = null,
        ?string $label = null
    ): ItemProcessingState {
        $state = $this->processingStates()->firstOrNew([
            'process_type' => $type,
        ]);

        $state->status = $status;

        if ($label) {
            $state->label = $label;
        }

        if ($message) {
            $state->message = $message;
        }

        if ($status === ItemProcessingStatus::PROCESSING) {
            $state->started_at = now();
            // Reset finished_at if re-processing
            $state->finished_at = null;
        }

        if ($status === ItemProcessingStatus::COMPLETED || $status === ItemProcessingStatus::FAILED) {
            $state->finished_at = now();
        }

        $state->save();

        return $state;
    }
}
