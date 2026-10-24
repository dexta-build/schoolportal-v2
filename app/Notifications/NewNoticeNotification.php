<?php

declare(strict_types=1);

namespace App\Notifications;

class NewNoticeNotification extends DatabaseNotification
{
    public function __construct(
        private readonly string $noticeTitle,
        private readonly int $noticeId,
    ) {}

    protected function icon(): string
    {
        return 'megaphone';
    }

    protected function message(): string
    {
        return __('New notice: :title', ['title' => $this->noticeTitle]);
    }

    protected function actionUrl(): string
    {
        return url("/portal/student/notices/{$this->noticeId}");
    }

    protected function typeLabel(): string
    {
        return 'notice';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon' => $this->icon(),
            'message' => $this->message(),
            'action_url' => $this->resolveActionUrl($notifiable),
            'type_label' => $this->typeLabel(),
            'notice_id' => $this->noticeId,
            'notice_title' => $this->noticeTitle,
        ];
    }

    private function resolveActionUrl(object $notifiable): string
    {
        $role = $notifiable->role ?? 'student';

        return match ($role) {
            'parent' => url("/portal/parent/notices/{$this->noticeId}"),
            'teacher' => url("/portal/teacher/notices/{$this->noticeId}"),
            default => url("/portal/student/notices/{$this->noticeId}"),
        };
    }
}
