<?php

namespace App\Livewire\Admin\Notifications;

use Illuminate\Support\Facades\Auth;

trait OpensNotifications
{
    /** Mark as read and go to its target (orders/products), internal links only. */
    public function open(string $id)
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        return is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//')
            ? $this->redirect($url, navigate: true)
            : $this->redirectRoute('admin.notifications', navigate: true);
    }
}
