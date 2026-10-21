<?php

declare(strict_types=1);

namespace App\Notifications;

class SubmissionPendingNotification extends DatabaseNotification
{
    public function __construct(
        private readonly string $entityType,
        private readonly string $teacherName,
        private readonly int $entityId,
    ) {}

    protected function icon(): string
    {
        return 'clock';
    }

    protected function message(): string
    {
        $typeLabel = match ($this->entityType) {
            'result' => __('result'),
            'assignment' => __('assignment'),
            'notice' => __('notice'),
            'quiz' => __('quiz'),
            'game' => __('educational game'),
            'report_card' => __('report card'),
            'score_amendment' => __('score correction'),
            default => $this->entityType,
        };

        return __(':teacher submitted a :type for approval.', [
            'teacher' => $this->teacherName,
            'type' => $typeLabel,
        ]);
    }

    protected function actionUrl(): string
    {
        return url('/portal/admin/approvals');
    }

    protected function typeLabel(): string
    {
        return 'pending_approval';
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
            'entity_id' => $this->entityId,
            'teacher_name' => $this->teacherName,
        ];
    }
}
