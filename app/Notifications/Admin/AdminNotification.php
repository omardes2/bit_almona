<?php

namespace App\Notifications\Admin;

use Illuminate\Notifications\Notification;

/**
 * In-app notification for the admin bell (database channel only, sent
 * synchronously — it is a single INSERT and needs no queue worker).
 */
abstract class AdminNotification extends Notification
{
    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{kind: string, level: string, title: string, body: string, url: ?string}
     */
    abstract public function toArray(object $notifiable): array;
}
