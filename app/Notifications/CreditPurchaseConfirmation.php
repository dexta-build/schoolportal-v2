<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Helpers\PortalUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CreditPurchaseConfirmation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $schoolName,
        private readonly int $credits,
        private readonly string $amount,
        private readonly int $newBalance,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return $notifiable->email ? ['mail'] : [];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('AI Credit Purchase Confirmation — :credits credits', ['credits' => $this->credits]))
            ->greeting(__('Purchase Confirmed!'))
            ->line(__('Your AI credit purchase for **:school** has been completed.', [
                'school' => $this->schoolName,
            ]))
            ->line(__('**Credits purchased:** :credits', ['credits' => $this->credits]))
            ->line(__('**Amount paid:** :amount', ['amount' => $this->amount]))
            ->line(__('**New balance:** :balance credits', ['balance' => $this->newBalance]))
            ->action(__('View AI Credits'), PortalUrl::forRecipient($notifiable, '/portal/admin/credits'))
            ->line(__('Thank you for your purchase!'));
    }
}
