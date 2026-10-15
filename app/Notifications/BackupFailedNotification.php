<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BackupFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $reason,
        private readonly string $database,
    ) {}

    /**
     * @return array<string>
     */
    public function via(mixed $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(mixed $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('⚠️ Database Backup Failed — '.$this->database)
            ->error()
            ->greeting('Backup Alert')
            ->line('The scheduled database backup for **'.$this->database.'** has failed.')
            ->line('**Reason:**')
            ->line($this->reason)
            ->line('Please check the application logs for more details and take corrective action.')
            ->action('View Application Logs', url('/'))
            ->salutation('SchoolPortal Backup Monitor');
    }
}
