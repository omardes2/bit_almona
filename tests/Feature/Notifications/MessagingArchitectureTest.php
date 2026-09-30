<?php

namespace Tests\Feature\Notifications;

use App\Actions\Checkout\PlaceOrder;
use App\Actions\Orders\ChangeOrderStatus;
use App\Enums\OrderStatus;
use App\Events\Orders\OrderConfirmed;
use App\Events\Orders\OrderEvent;
use App\Events\Orders\OrderPlaced;
use App\Messaging\Channels\WhatsAppChannel;
use App\Messaging\MessagingManager;
use App\Models\Order;
use App\Models\User;
use App\Notifications\Customer\OrderStatusMessage;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Feature\Checkout\CheckoutTestHelpers;
use Tests\TestCase;

class MessagingArchitectureTest extends TestCase
{
    use CheckoutTestHelpers, RefreshDatabase;

    private function placeOrder(): Order
    {
        $customer = $this->customer();
        $this->actingAs($customer);
        $this->addToCart($this->product());

        return app(PlaceOrder::class)->handle($customer, Str::random(40), $this->addressFor($customer), $this->zone()->id);
    }

    public function test_order_lifecycle_events_are_dispatched(): void
    {
        Event::fake(OrderEvent::all());

        $order = $this->placeOrder();
        app(ChangeOrderStatus::class)->handle($order, OrderStatus::Confirmed, User::factory()->admin()->create());

        Event::assertDispatched(OrderPlaced::class, fn ($e) => $e->order->is($order));
        Event::assertDispatched(OrderConfirmed::class);
        $this->assertInstanceOf(ShouldDispatchAfterCommit::class, new OrderPlaced($order));
    }

    public function test_no_external_message_without_a_configured_provider(): void
    {
        Notification::fake();

        $this->placeOrder();

        Notification::assertNothingSentTo(User::customers()->first(), OrderStatusMessage::class);
        Notification::assertCount(0 + User::admins()->count()); // only admin bell notifications (none here)
    }

    public function test_enabled_channels_follow_config_and_customer_preferences(): void
    {
        Notification::fake();
        config(['messaging.channels.whatsapp.driver' => 'log']);

        $order = $this->placeOrder();
        $customer = $order->user;

        Notification::assertSentTo($customer, OrderStatusMessage::class, function ($n) {
            return $n->channels === ['whatsapp']
                && $n->via(new \stdClass) === [WhatsAppChannel::class]
                && $n instanceof ShouldQueue
                && str_contains($n->toWhatsApp(new \stdClass)->text, $n->order->order_number);
        });

        // A customer who turned WhatsApp off gets nothing.
        $customer->customer->update(['notification_preferences' => ['whatsapp' => false]]);
        $this->assertSame([], app(MessagingManager::class)->channelsFor($customer->fresh('customer')));

        // SMS enabled in config but not wanted by default.
        config(['messaging.channels.sms.driver' => 'log']);
        $this->assertSame([], app(MessagingManager::class)->channelsFor($customer->fresh('customer')));
        $customer->customer->update(['notification_preferences' => ['sms' => true]]);
        $this->assertContains('sms', app(MessagingManager::class)->channelsFor($customer->fresh('customer')));
    }
}
