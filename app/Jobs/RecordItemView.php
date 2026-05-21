<?php

namespace App\Jobs;

use App\Models\ItemView;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Stevebauman\Location\Facades\Location;

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
        // Deduplication: prevent multiple views from the same IP/User within 12 hours
        $twelveHoursAgo = now()->subHours(12);

        $existingViewQuery = ItemView::where('item_id', $this->itemId)
            ->where('created_at', '>=', $twelveHoursAgo);

        if ($this->userId) {
            $existingViewQuery->where(function ($query) {
                $query->where('user_id', $this->userId)
                    ->orWhere('ip_address', $this->ip);
            });
        } else {
            $existingViewQuery->where('ip_address', $this->ip);
        }

        if ($existingViewQuery->exists()) {
            return; // View already recorded recently, skip
        }

        $country = null;

        // Use a test IP if we are on localhost (IPv4 or IPv6)
        $ipToLocate = $this->ip;
        if (in_array($ipToLocate, ['127.0.0.1', '::1'])) {
            $ipToLocate = '8.8.8.8'; // Google USA IP for local testing
        }

        if ($ipToLocate) {
            try {
                if ($position = Location::get($ipToLocate)) {
                    $country = $position->countryName;
                }
            } catch (\Exception $e) {
                // Silently ignore if MaxMind DB is missing or lookup fails
            }
        }

        ItemView::create([
            'item_id' => $this->itemId,
            'user_id' => $this->userId,
            'is_authenticated' => $this->isAuthenticated,
            'ip_address' => $this->ip, // We still save the real IP
            'country' => $country,
            'user_agent' => $this->userAgent,
            'referer' => $this->referer,
        ]);
    }
}
