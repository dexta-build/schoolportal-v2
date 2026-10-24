<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolAnnouncement;
use App\Services\RecentlyDeletedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AnnouncementController extends Controller
{
    public function index(): View
    {
        $announcements = SchoolAnnouncement::where('school_id', auth()->user()->school_id)
            ->with('creator')
            ->latest()
            ->paginate(15);

        return view('admin.announcements.index', compact('announcements'));
    }

    public function create(): View
    {
        return view('admin.announcements.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'priority' => ['required', 'in:info,warning,critical'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['in:teacher,student,parent'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        SchoolAnnouncement::create([
            ...$data,
            'school_id' => auth()->user()->school_id,
            'target_roles' => ! empty($data['target_roles']) ? $data['target_roles'] : null,
            'is_active' => true,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('admin.announcements.index')
            ->with('success', __('Announcement published.'));
    }

    public function edit(SchoolAnnouncement $announcement): View
    {
        if ($announcement->school_id !== auth()->user()->school_id) {
            abort(403);
        }

        return view('admin.announcements.edit', compact('announcement'));
    }

    public function update(Request $request, SchoolAnnouncement $announcement): RedirectResponse
    {
        if ($announcement->school_id !== auth()->user()->school_id) {
            abort(403);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'priority' => ['required', 'in:info,warning,critical'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['in:teacher,student,parent'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $announcement->update([
            ...$data,
            'target_roles' => ! empty($data['target_roles']) ? $data['target_roles'] : null,
        ]);

        return redirect()->route('admin.announcements.index')
            ->with('success', __('Announcement updated.'));
    }

    public function deactivate(SchoolAnnouncement $announcement): RedirectResponse
    {
        if ($announcement->school_id !== auth()->user()->school_id) {
            abort(403);
        }

        $announcement->update(['is_active' => false]);

        return back()->with('success', __('Announcement deactivated.'));
    }

    public function activate(SchoolAnnouncement $announcement): RedirectResponse
    {
        if ($announcement->school_id !== auth()->user()->school_id) {
            abort(403);
        }

        $announcement->update(['is_active' => true]);

        return back()->with('success', __('Announcement activated.'));
    }

    public function destroy(SchoolAnnouncement $announcement): RedirectResponse
    {
        if ($announcement->school_id !== auth()->user()->school_id) {
            abort(403);
        }

        $announcement->delete();

        return redirect()->route('admin.announcements.index')
            ->with('undo', app(RecentlyDeletedService::class)->deleted($announcement, 'admin'));
    }
}
