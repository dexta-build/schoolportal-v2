<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\ExamAttempt;

class ExamGradingFailedNotification extends DatabaseNotification
{
    public function __construct(
        private readonly ExamAttempt $attempt,
    ) {}

    protected function icon(): string
    {
        return 'exclamation-circle';
    }

    protected function message(): string
    {
        $studentName = $this->attempt->student?->name ?? __('A student');
        $examTitle = $this->attempt->exam?->title ?? __('an exam');

        return __(':student\'s attempt for ":exam" could not be graded automatically. Please grade it manually.', [
            'student' => $studentName,
            'exam' => $examTitle,
        ]);
    }

    protected function actionUrl(): string
    {
        return url("/portal/admin/exams/{$this->attempt->exam_id}/results/{$this->attempt->id}/grade");
    }

    protected function typeLabel(): string
    {
        return 'grading_failed';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon' => $this->icon(),
            'message' => $this->message(),
            'action_url' => $this->actionUrl(),
            'type_label' => $this->typeLabel(),
            'exam_attempt_id' => $this->attempt->id,
            'student_id' => $this->attempt->student_id,
            'exam_id' => $this->attempt->exam_id,
        ];
    }
}
