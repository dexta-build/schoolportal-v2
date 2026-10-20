<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent synchronously (not queued) so the alert still goes out when the
 * hosting account is too full for the queue to process jobs reliably.
 */
class FileUsageAlertNotification extends Notification
{
    /**
     * @param  array{total: int, limit: int, percent: float, folders: array<string, int>, scanned_at: string}  $usage
     */
    public function __construct(
        public readonly array $usage,
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
        $message = (new MailMessage)
            ->subject("⚠️ File usage at {$this->usage['percent']}% of hosting limit")
            ->error()
            ->greeting('Hosting File Usage Alert')
            ->line("The hosting account is using **{$this->usage['percent']}%** of its file (inode) limit: "
                .number_format($this->usage['total']).' / '.number_format($this->usage['limit']).' files.')
            ->line('When the limit is reached every write fails with "Disk quota exceeded", and students cannot log in or submit exams.')
            ->line('**Largest folders:**');

        foreach ($this->usage['folders'] as $folder => $count) {
            $message->line("{$folder}: ".number_format($count));
        }

        return $message
            ->line('Delete unneeded files (old mail, caches, tmp) to free up space.')
            ->action('View System Health', route('super-admin.system-health'))
            ->salutation('SchoolPortal Health Monitor');
    }
}
