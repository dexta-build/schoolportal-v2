<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Helpers\PortalUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNewUser extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $role,
        private readonly string $username,
        private readonly string $schoolName,
        private readonly ?string $temporaryPassword = null,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $roleLabel = match ($this->role) {
            'school_admin' => 'School Administrator',
            'teacher' => 'Teacher',
            'student' => 'Student',
            'parent' => 'Parent',
            default => ucfirst($this->role),
        };

        $message = (new MailMessage)
            ->subject(__('Welcome to :school — Your Account is Ready', ['school' => $this->schoolName]))
            ->greeting(__('Welcome to :school!', ['school' => $this->schoolName]))
            ->line(__('Your :role account has been created on the school portal.', ['role' => strtolower($roleLabel)]))
            ->line(__('**Username:** :username', ['username' => $this->username]));

        if ($this->temporaryPassword) {
            $message->line(__('**Temporary Password:** :password', ['password' => $this->temporaryPassword]))
                ->line(__('You will be asked to change your password when you first log in.'));
        }

        return $message
            ->action(__('Log In to Portal'), PortalUrl::forRecipient($notifiable, '/portal/login'))
            ->line(__('If you have any questions, please contact your school administrator.'))
            ->salutation(__('Regards, :school', ['school' => $this->schoolName]));
    }
}
