<?php

namespace App\Jobs;

use App\Models\ItemView;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordItemView implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $itemId,
        public ?int $userId,
        public bool $isAuthenticated,
        public ?string $ip,
        public ?string $userAgent,
        public ?string $referer
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ItemView::create([
            'item_id' => $this->itemId,
            'user_id' => $this->userId,
            'is_authenticated' => $this->isAuthenticated,
            'ip_address' => $this->ip,
            'user_agent' => $this->userAgent,
            'referer' => $this->referer,
        ]);
    }
}
