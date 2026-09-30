<?php

namespace App\Livewire\Admin\Notifications;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Header bell. Refreshes every 60 seconds (wire:poll, paused while the tab is
 * hidden) — one cheap COUNT query per admin per minute.
 */
class NotificationBell extends Component
{
    use OpensNotifications;

    public function render()
    {
        $user = Auth::user();

        return view('livewire.admin.notifications.bell', [
            'unread' => $user->unreadNotifications()->count(),
            'latest' => $user->notifications()->latest()->limit(6)->get(),
        ]);
    }
}
