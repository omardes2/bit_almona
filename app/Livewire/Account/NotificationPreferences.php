<?php

namespace App\Livewire\Account;

use App\Messaging\MessagingManager;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Which channels the customer wants order updates on. A channel whose
 * provider is not configured is shown as "غير متاح حاليًا" and cannot be
 * changed; its saved choice is kept for when it becomes available.
 */
#[Layout('layouts.app', ['noindex' => true])]
#[Title('إعدادات التنبيهات')]
class NotificationPreferences extends Component
{
    public const CHANNELS = [
        'whatsapp' => ['واتساب', 'تحديثات حالة الطلب على واتساب.'],
        'sms' => ['رسائل SMS', 'رسالة نصية عند تغيّر حالة الطلب.'],
        'email' => ['البريد الإلكتروني', 'يتطلب بريدًا إلكترونيًا في حسابك.'],
        'push' => ['إشعارات التطبيق', 'على هاتفك عند توفر التطبيق.'],
    ];

    /** @var array<string, bool> */
    public array $preferences = [];

    public function mount(): void
    {
        $this->preferences = $this->current();
    }

    #[Computed]
    public function user(): User
    {
        return Auth::user()->loadMissing('customer');
    }

    /** @return array<string, bool> */
    private function current(): array
    {
        $saved = (array) ($this->user->customer?->notification_preferences ?? []);
        $defaults = (array) config('messaging.customer_defaults', []);

        return collect(self::CHANNELS)->mapWithKeys(fn ($_, string $channel) => [
            $channel => (bool) ($saved[$channel] ?? $defaults[$channel] ?? false),
        ])->all();
    }

    public function save(MessagingManager $messaging): void
    {
        $current = $this->current();
        $clean = [];

        // Only known channels, only booleans; unavailable channels keep their saved value.
        foreach (array_keys(self::CHANNELS) as $channel) {
            $clean[$channel] = $messaging->canReach($this->user, $channel)
                ? filter_var($this->preferences[$channel] ?? false, FILTER_VALIDATE_BOOLEAN)
                : $current[$channel];
        }

        $this->user->customer()->updateOrCreate([], ['notification_preferences' => $clean]);
        $this->preferences = $clean;

        session()->flash('status', 'تم حفظ إعدادات التنبيهات.');
    }

    public function render(MessagingManager $messaging)
    {
        return view('livewire.account.notification-preferences', [
            'available' => collect(self::CHANNELS)->mapWithKeys(fn ($_, string $channel) => [
                $channel => $messaging->canReach($this->user, $channel),
            ])->all(),
        ]);
    }
}
