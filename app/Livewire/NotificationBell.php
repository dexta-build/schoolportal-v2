<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Helpers\PortalUrl;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationBell extends Component
{
    public int $unreadCount = 0;

    /** @var array<int, array<string, mixed>> */
    public array $recentNotifications = [];

    public bool $showDropdown = false;

    public function mount(): void
    {
        $this->loadNotifications();
    }

    public function loadNotifications(): void
    {
        $user = auth()->user();
        if (! $user) {
            $this->unreadCount = 0;
            $this->recentNotifications = [];

            return;
        }

        $this->unreadCount = $user->unreadNotifications()->count();

        $this->recentNotifications = $user->notifications()
            ->latest()
            ->take(10)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'message' => $n->data['message'] ?? $n->data['title'] ?? $n->data['body'] ?? __('New notification'),
                'icon' => $n->data['icon'] ?? 'bell',
                'action_url' => $n->data['action_url'] ?? '#',
                'type_label' => $n->data['type_label'] ?? '',
                'read' => $n->read_at !== null,
                'time' => $n->created_at->diffForHumans(),
            ])
            ->toArray();
    }

    #[On('refresh-notifications')]
    public function refreshNotifications(): void
    {
        $this->loadNotifications();
    }

    public function toggleDropdown(): void
    {
        $this->showDropdown = ! $this->showDropdown;

        if ($this->showDropdown) {
            $this->loadNotifications();
        }
    }

    public function markAsRead(string $notificationId): string
    {
        $user = auth()->user();
        $notification = $user?->notifications()->find($notificationId);

        if ($notification) {
            $notification->markAsRead();
            $this->loadNotifications();

            // Stay on the domain the user is on (links are stored with the platform domain)
            return PortalUrl::local($notification->data['action_url'] ?? null);
        }

        return '#';
    }

    public function markAllAsRead(): void
    {
        auth()->user()?->unreadNotifications->markAsRead();
        $this->loadNotifications();
    }

    public function placeholder(): string
    {
        return <<<'HTML'
        <span></span>
        HTML;
    }

    public function render(): View
    {
        return view('livewire.notification-bell');
    }
}
