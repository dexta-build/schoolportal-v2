<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Helpers\PortalUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubmissionReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $entityType,
        private readonly string $status,
        private readonly ?string $rejectionReason = null,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $typeLabel = match ($this->entityType) {
            'result' => 'result upload',
            'assignment' => 'assignment',
            'notice' => 'notice',
            'quiz' => 'quiz',
            'game' => 'educational game',
            'report_card' => 'report card',
            'score_amendment' => 'score correction',
            default => $this->entityType,
        };

        $approved = $this->status === 'approved';

        $message = (new MailMessage)
            ->subject(__('Your :type has been :status', ['type' => $typeLabel, 'status' => $this->status]))
            ->greeting($approved ? __('Good news!') : __('Submission Update'));

        if ($approved) {
            $message->line(__('Your **:type** has been approved and is now visible to students.', ['type' => $typeLabel]));
        } else {
            $message->line(__('Your **:type** has been rejected.', ['type' => $typeLabel]));

            if ($this->rejectionReason) {
                $message->line(__('**Reason:** :reason', ['reason' => $this->rejectionReason]));
            }

            $message->line(__('You can edit and resubmit your :type.', ['type' => $typeLabel]));
        }

        return $message
            ->action(__('View My Submissions'), PortalUrl::forRecipient($notifiable, '/portal/teacher/submissions'))
            ->salutation(__('Regards, DX-SchoolPortal'));
    }
}
