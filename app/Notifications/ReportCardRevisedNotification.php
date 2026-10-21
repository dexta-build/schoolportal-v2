<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Sent when a published report card is regenerated after a score correction changed its content.
 */
class ReportCardRevisedNotification extends DatabaseNotification
{
    public function __construct(
        private readonly string $studentName,
        private readonly string $sessionName,
        private readonly string $termName,
        private readonly int $reportId,
        private readonly string $recipientRole = 'student',
    ) {}

    protected function icon(): string
    {
        return 'academic-cap';
    }

    protected function message(): string
    {
        if ($this->recipientRole === 'parent') {
            return __(':student\'s report card for :term, :session has been corrected. Please view the updated version.', [
                'student' => $this->studentName,
                'term' => $this->termName,
                'session' => $this->sessionName,
            ]);
        }

        return __('Your report card for :term, :session has been corrected. Please view the updated version.', [
            'term' => $this->termName,
            'session' => $this->sessionName,
        ]);
    }

    protected function actionUrl(): string
    {
        if ($this->recipientRole === 'parent') {
            return url('/portal/parent/report-cards');
        }

        return url('/portal/student/report-cards');
    }

    protected function typeLabel(): string
    {
        return 'report_card';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon' => $this->icon(),
            'message' => $this->message(),
            'action_url' => $this->actionUrl(),
            'type_label' => $this->typeLabel(),
            'report_id' => $this->reportId,
            'student_name' => $this->studentName,
            'session_name' => $this->sessionName,
            'term_name' => $this->termName,
        ];
    }
}
