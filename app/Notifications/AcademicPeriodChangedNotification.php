<?php

declare(strict_types=1);

namespace App\Notifications;

class AcademicPeriodChangedNotification extends DatabaseNotification
{
    public function __construct(
        private readonly string $periodType, // 'term' | 'session'
        private readonly string $name,
    ) {}

    protected function icon(): string
    {
        return 'calendar';
    }

    protected function message(): string
    {
        if ($this->periodType === 'session') {
            return __('A new academic session has started: :name.', ['name' => $this->name]);
        }

        return __('A new term is now active: :name.', ['name' => $this->name]);
    }

    protected function actionUrl(): string
    {
        return url('/portal/student/dashboard');
    }

    protected function typeLabel(): string
    {
        return 'academic_period_changed';
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'icon' => $this->icon(),
            'message' => $this->message(),
            // Sent to every role — each goes to their own dashboard
            'action_url' => match ($notifiable->role ?? 'student') {
                'school_admin' => url('/portal/admin/dashboard'),
                'teacher' => url('/portal/teacher/dashboard'),
                'parent' => url('/portal/parent/dashboard'),
                default => $this->actionUrl(),
            },
            'type_label' => $this->typeLabel(),
            'period_type' => $this->periodType,
            'period_name' => $this->name,
        ];
    }
}
