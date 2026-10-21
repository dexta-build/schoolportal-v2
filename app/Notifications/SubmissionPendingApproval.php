<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Helpers\PortalUrl;
use App\Models\TeacherAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubmissionPendingApproval extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly TeacherAction $action,
        private readonly string $teacherName,
    ) {}

    /** @return array<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $typeLabel = match ($this->action->entity_type) {
            'result' => 'Result Upload',
            'assignment' => 'Assignment',
            'notice' => 'Notice',
            'quiz' => 'Quiz',
            'game' => 'Educational Game',
            'report_card' => 'Report Card',
            'score_amendment' => 'Score Correction',
            default => ucfirst($this->action->entity_type),
        };

        return (new MailMessage)
            ->subject(__('Pending Approval: :type by :teacher', ['type' => $typeLabel, 'teacher' => $this->teacherName]))
            ->greeting(__('New Submission Awaiting Review'))
            ->line(__(':teacher has submitted a **:type** that requires your approval.', [
                'teacher' => $this->teacherName,
                'type' => strtolower($typeLabel),
            ]))
            ->action(__('Review Submissions'), PortalUrl::forRecipient($notifiable, '/portal/admin/approvals'))
            ->line(__('Please review and approve or reject this submission.'))
            ->salutation(__('Regards, DX-SchoolPortal'));
    }
}
