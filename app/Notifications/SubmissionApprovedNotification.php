<?php

declare(strict_types=1);

namespace App\Notifications;

class SubmissionApprovedNotification extends DatabaseNotification
{
    public function __construct(
        private readonly string $entityType,
        private readonly string $entityTitle,
    ) {}

    protected function icon(): string
    {
        return 'check-circle';
    }

    protected function message(): string
    {
        $typeLabel = match ($this->entityType) {
            'result' => __('result upload'),
            'assignment' => __('assignment'),
            'notice' => __('notice'),
            'quiz' => __('quiz'),
            'game' => __('educational game'),
            'report_card' => __('report card'),
            'score_amendment' => __('score correction'),
            default => $this->entityType,
        };

        return __('Your :type ":title" has been approved!', [
            'type' => $typeLabel,
            'title' => $this->entityTitle,
        ]);
    }

    protected function actionUrl(): string
    {
        return url('/portal/teacher/submissions');
    }

    protected function typeLabel(): string
    {
        return 'approval';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon' => $this->icon(),
            'message' => $this->message(),
            'action_url' => $this->actionUrl(),
            'type_label' => $this->typeLabel(),
            'entity_type' => $this->entityType,
            'entity_title' => $this->entityTitle,
            'status' => 'approved',
        ];
    }
}
