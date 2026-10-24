<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Helpers\PortalUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetByAdmin extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $schoolName,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your Password Has Been Reset'))
            ->greeting(__('Hello, :name!', ['name' => $notifiable->name]))
            ->line(__('Your password on the :school portal has been reset by an administrator.', ['school' => $this->schoolName]))
            ->line(__('You will be asked to set a new password when you next log in.'))
            ->action(__('Log In to Portal'), PortalUrl::forRecipient($notifiable, '/portal/login'))
            ->line(__('If you did not expect this change, please contact your school administrator immediately.'))
            ->salutation(__('Regards, :school', ['school' => $this->schoolName]));
    }
}
