<?php

declare(strict_types=1);

namespace App\Notifications;

class DeadlineReminderNotification extends DatabaseNotification
{
    public function __construct(
        private readonly string $contentType,   // 'quiz' | 'exam'
        private readonly string $title,
        private readonly int $entityId,
        private readonly string $expiresAt,     // human-readable, e.g. "May 2 at 3:00 PM"
        private readonly string $recipientRole, // 'student' | 'parent'
    ) {}

    protected function icon(): string
    {
        return 'clock';
    }

    protected function message(): string
    {
        if ($this->recipientRole === 'parent') {
            return __("Reminder: your child's :type \":title\" closes :when.", [
                'type' => $this->contentType,
                'title' => $this->title,
                'when' => $this->expiresAt,
            ]);
        }

        return __('Reminder: :type ":title" closes :when. Complete it before the deadline!', [
            'type' => ucfirst($this->contentType),
            'title' => $this->title,
            'when' => $this->expiresAt,
        ]);
    }

    protected function actionUrl(): string
    {
        if ($this->recipientRole === 'parent') {
            return match ($this->contentType) {
                'quiz' => url('/portal/parent/quizzes'),
                'exam' => url('/portal/parent/cbt'),
                default => url('/portal/parent/dashboard'),
            };
        }

        return match ($this->contentType) {
            // Quizzes have no per-quiz page for students — the list is where they start one
            'quiz' => url('/portal/student/quizzes'),
            'exam' => url("/portal/student/exams/{$this->entityId}"),
            default => url('/portal/student/dashboard'),
        };
    }

    protected function typeLabel(): string
    {
        return 'deadline_reminder';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon' => $this->icon(),
            'message' => $this->message(),
            'action_url' => $this->actionUrl(),
            'type_label' => $this->typeLabel(),
            'content_type' => $this->contentType,
            'entity_id' => $this->entityId,
            'expires_at' => $this->expiresAt,
        ];
    }
}
