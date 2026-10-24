<?php

declare(strict_types=1);

namespace App\Notifications;

use Carbon\CarbonInterface;

class ExamAccessResetNotification extends DatabaseNotification
{
    public function __construct(
        private readonly string $examTitle,
        private readonly string $category,
        private readonly int $examId,
        private readonly CarbonInterface $availableUntil,
    ) {}

    protected function icon(): string
    {
        return 'arrow-path';
    }

    protected function message(): string
    {
        $label = match ($this->category) {
            'assessment' => __('assessment'),
            'assignment' => __('CBT assignment'),
            default => __('exam'),
        };

        return __('Your access to :title has been reset. You can take this :label until :date.', [
            'title' => $this->examTitle,
            'label' => $label,
            'date' => $this->availableUntil->format('M j, Y g:i A'),
        ]);
    }

    protected function actionUrl(): string
    {
        return url("/portal/student/exams/{$this->examId}");
    }

    protected function typeLabel(): string
    {
        return 'exam';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon' => $this->icon(),
            'message' => $this->message(),
            'action_url' => $this->actionUrl(),
            'type_label' => $this->typeLabel(),
            'exam_id' => $this->examId,
            'exam_title' => $this->examTitle,
            'category' => $this->category,
            'available_until' => $this->availableUntil->toIso8601String(),
        ];
    }
}
