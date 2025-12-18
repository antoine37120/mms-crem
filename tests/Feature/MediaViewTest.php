<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\ItemView;
use App\Models\User;
use App\Jobs\RecordItemView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MediaViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_accessing_master_media_route_dispatches_job()
    {
        Queue::fake();

        $user = User::factory()->create();
        $item = Item::factory()->create(['code' => 'test_item']);

        $response = $this->actingAs($user)->get(route('media.master', ['code' => 'test_item']));

        // Assert job was dispatched
        Queue::assertPushed(RecordItemView::class, function ($job) use ($item, $user) {
            return $job->itemId === $item->id
                && $job->userId === $user->id
                && $job->isAuthenticated === true;
        });
    }

    public function test_accessing_master_media_route_dispatches_job_guest()
    {
        Queue::fake();

        $item = Item::factory()->create(['code' => 'test_item_guest']);

        $response = $this->get(route('media.master', ['code' => 'test_item_guest']));

        // Assert job was dispatched for guest
        Queue::assertPushed(RecordItemView::class, function ($job) use ($item) {
            return $job->itemId === $item->id
                && $job->userId === null
                && $job->isAuthenticated === false;
        });
    }

    public function test_job_creates_item_view_record()
    {
        $item = Item::factory()->create();
        $user = User::factory()->create();

        $job = new RecordItemView(
            itemId: $item->id,
            userId: $user->id,
            isAuthenticated: true,
            ip: '127.0.0.1',
            userAgent: 'TestAgent',
            referer: 'http://example.com'
        );

        $job->handle();

        $this->assertDatabaseHas('item_views', [
            'item_id' => $item->id,
            'user_id' => $user->id,
            'is_authenticated' => true,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestAgent',
            'referer' => 'http://example.com'
        ]);
    }
}
