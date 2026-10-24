<?php

declare(strict_types=1);

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\User;
use App\Support\RemovedItemPage;
use Illuminate\Http\Response;
use Illuminate\View\View;

class NoticeController extends Controller
{
    public function index(): View
    {
        $parent = auth()->user();
        $childIds = $parent->children()->pluck('student_id');

        $childrenProfiles = User::whereIn('id', $childIds)
            ->where('school_id', $parent->school_id)
            ->with('studentProfile.class:id,level_id')
            ->get()
            ->pluck('studentProfile')
            ->filter();

        $childLevelIds = $childrenProfiles->map(fn ($p) => $p->class?->level_id)->filter()->unique()->values()->toArray();
        $childClassIds = $childrenProfiles->pluck('class_id')->filter()->unique()->values()->toArray();

        $notices = Notice::where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('target_roles')
                    ->orWhereJsonContains('target_roles', 'parent');
            })
            ->where(function ($q) use ($childLevelIds) {
                $q->whereNull('target_levels');
                foreach ($childLevelIds as $levelId) {
                    $q->orWhereJsonContains('target_levels', $levelId);
                }
            })
            ->where(function ($q) use ($childClassIds) {
                $q->whereNull('target_classes');
                foreach ($childClassIds as $classId) {
                    $q->orWhereJsonContains('target_classes', $classId);
                }
            })
            ->with('creator:id,name')
            ->latest('published_at')
            ->paginate(10);

        return view('parent.notices.index', compact('notices'));
    }

    public function show(Notice $notice): View|Response
    {
        // Published, not expired, and addressed to this parent or one of their children's classes
        if (! $notice->isVisibleTo(auth()->user())) {
            return RemovedItemPage::response('notice', 'parent.notices.index');
        }

        $notice->load('creator:id,name');

        return view('parent.notices.show', compact('notice'));
    }
}
