<?php

namespace Tests\Feature\Account;

use App\Livewire\Account\NotificationPreferences;
use App\Messaging\MessagingManager;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_open_the_page(): void
    {
        $this->get('/account/notifications')->assertRedirect(route('login'));
    }

    public function test_unconfigured_channels_are_shown_as_unavailable(): void
    {
        config(['messaging.channels.whatsapp.driver' => null, 'messaging.channels.sms.driver' => null]);
        $this->actingAs(User::factory()->create());

        $this->get('/account/notifications')
            ->assertOk()
            ->assertSee('إعدادات التنبيهات')
            ->assertSee('غير متاح حاليًا')
            ->assertDontSee('wire:model="preferences.whatsapp"', false);
    }

    public function test_available_channels_can_be_toggled_and_unavailable_ones_cannot(): void
    {
        config(['messaging.channels.whatsapp.driver' => 'log', 'messaging.channels.sms.driver' => null]);
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(NotificationPreferences::class)
            ->assertSet('preferences.whatsapp', true) // store default
            ->set('preferences.whatsapp', false)
            ->set('preferences.sms', true)            // tampered: SMS is not available
            ->set('preferences.hacked', true)         // unknown channel
            ->call('save')
            ->assertSee('تم حفظ إعدادات التنبيهات');

        $saved = $user->fresh()->customer->notification_preferences;
        $this->assertSame(['whatsapp' => false, 'sms' => false, 'email' => false, 'push' => false], $saved);
        $this->assertSame([], app(MessagingManager::class)->channelsFor($user->fresh()));

        Livewire::test(NotificationPreferences::class)->set('preferences.whatsapp', true)->call('save');
        $this->assertSame(['whatsapp'], app(MessagingManager::class)->channelsFor($user->fresh()));
    }

    public function test_account_navigation_links_to_preferences(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/account')->assertSee(route('account.notifications'))->assertSee(route('account.phone'));
    }
}
