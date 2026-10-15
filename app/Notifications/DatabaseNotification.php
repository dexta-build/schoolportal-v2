<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

abstract class DatabaseNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Build the data array for the database notification.
     *
     * @return array<string, mixed>
     */
    abstract public function toArray(object $notifiable): array;

    /**
     * Get the notification icon (Heroicon name).
     */
    abstract protected function icon(): string;

    /**
     * Get the notification message.
     */
    abstract protected function message(): string;

    /**
     * Get the action URL.
     */
    abstract protected function actionUrl(): string;

    /**
     * Get the notification type label for display.
     */
    abstract protected function typeLabel(): string;
}
