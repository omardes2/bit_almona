<?php

namespace App\Livewire\Admin\Notifications;

use App\Livewire\Admin\Concerns\Toasts;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('الإشعارات')]
class NotificationCenter extends Component
{
    use OpensNotifications, Toasts, WithPagination;

    #[Url(except: 'all')]
    public string $filter = 'all';

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    public function markRead(string $id): void
    {
        Auth::user()->notifications()->findOrFail($id)->markAsRead();
    }

    public function markUnread(string $id): void
    {
        Auth::user()->notifications()->findOrFail($id)->markAsUnread();
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);
        $this->toast('تم تعليم كل الإشعارات كمقروءة.');
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.admin.notifications.center', [
            'notifications' => ($this->filter === 'unread' ? $user->unreadNotifications() : $user->notifications())->latest()->paginate(20),
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }
}
