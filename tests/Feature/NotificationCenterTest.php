<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OperationalNotification;
use App\Services\OperationalNotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_order_confirmation_notifies_active_operational_roles(): void
    {
        $administrator = User::factory()->admin()->create();
        $supervisor = User::factory()->supervisor()->create();
        $warehouseUser = User::factory()->create();
        $salesperson = User::factory()->preventista()->create();
        $inactiveWarehouseUser = User::factory()->inactive()->create();
        $order = Order::factory()->confirmed()->create();

        app(OperationalNotificationService::class)->orderConfirmed($order);

        $this->assertSame(1, $administrator->unreadNotifications()->count());
        $this->assertSame(1, $supervisor->unreadNotifications()->count());
        $this->assertSame(1, $warehouseUser->unreadNotifications()->count());
        $this->assertSame(0, $salesperson->unreadNotifications()->count());
        $this->assertSame(0, $inactiveWarehouseUser->unreadNotifications()->count());
    }

    public function test_user_can_read_feed_and_mark_an_owned_notification_as_read(): void
    {
        $user = User::factory()->create();
        $user->notify($this->notification());
        $notification = $user->notifications()->firstOrFail();

        $this->actingAs($user)
            ->getJson(route('notifications.feed'))
            ->assertOk()
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('notifications.0.title', 'Trabajo pendiente');

        $this->actingAs($user)
            ->postJson(route('notifications.read', $notification))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->assertNotNull($notification->refresh()->read_at);
    }

    public function test_user_cannot_mark_another_users_notification_as_read(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $owner->notify($this->notification());
        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs($otherUser)
            ->postJson(route('notifications.read', $notification))
            ->assertNotFound();

        $this->assertNull($notification->refresh()->read_at);
    }

    private function notification(): OperationalNotification
    {
        return new OperationalNotification([
            'type' => 'test',
            'title' => 'Trabajo pendiente',
            'message' => 'Hay una novedad para revisar.',
            'severity' => 'important',
            'url' => route('dashboard', absolute: false),
        ]);
    }
}
