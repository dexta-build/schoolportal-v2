<?php

declare(strict_types=1);

namespace App\Notifications;

class NewExamNotification extends DatabaseNotification
{
    public function __construct(
        private readonly string $examTitle,
        private readonly string $className,
        private readonly string $category,
        private readonly int $examId,
        private readonly string $recipientRole = 'student',
    ) {}

    protected function icon(): string
    {
        return 'clipboard-document-list';
    }

    protected function message(): string
    {
        $label = match ($this->category) {
            'assessment' => __('assessment'),
            'assignment' => __('CBT assignment'),
            default => __('exam'),
        };

        if ($this->recipientRole === 'parent') {
            return __('New :label available for :class: :title', [
                'label' => $label,
                'class' => $this->className,
                'title' => $this->examTitle,
            ]);
        }

        return __('New :label available: :title', [
            'label' => $label,
            'title' => $this->examTitle,
        ]);
    }

    protected function actionUrl(): string
    {
        $prefix = match ($this->category) {
            'assessment' => 'assessments',
            'assignment' => 'cbt-assignments',
            default => 'exams',
        };

        // Parents have a single CBT page covering exams, assessments and assignments
        if ($this->recipientRole === 'parent') {
            return url('/portal/parent/cbt');
        }

        return url("/portal/student/{$prefix}");
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
            'class_name' => $this->className,
            'category' => $this->category,
        ];
    }
}
