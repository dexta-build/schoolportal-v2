<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\PortalUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filter = $request->query('filter', 'all');

        $query = $user->notifications()->latest();

        if ($filter === 'unread') {
            $query = $user->unreadNotifications()->latest();
        }

        $notifications = $query->paginate(20)->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $actionUrl = $notification->data['action_url'] ?? null;

        if ($actionUrl) {
            // Stay on the domain the user is on (links are stored with the platform domain)
            return redirect(PortalUrl::local($actionUrl));
        }

        return back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back()->with('success', __('All notifications marked as read.'));
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($id)->delete();

        return back()->with('success', __('Notification deleted.'));
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        $request->user()->notifications()->delete();

        return back()->with('success', __('All notifications cleared.'));
    }
}
