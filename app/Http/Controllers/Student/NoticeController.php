<?php

declare(strict_types=1);

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Support\RemovedItemPage;
use Illuminate\Http\Response;
use Illuminate\View\View;

class NoticeController extends Controller
{
    public function index(): View
    {
        $student = auth()->user();
        $profile = $student->studentProfile;

        $notices = Notice::where('is_published', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('target_roles')
                    ->orWhereJsonContains('target_roles', 'student');
            })
            ->where(function ($q) use ($profile) {
                $q->whereNull('target_levels');
                if ($profile?->class?->level_id) {
                    $q->orWhereJsonContains('target_levels', $profile->class->level_id);
                }
            })
            ->where(function ($q) use ($profile) {
                $q->whereNull('target_classes');
                if ($profile?->class_id) {
                    $q->orWhereJsonContains('target_classes', $profile->class_id);
                }
            })
            ->with('creator:id,name')
            ->latest('published_at')
            ->paginate(10);

        return view('student.notices.index', compact('notices'));
    }

    public function show(Notice $notice): View|Response
    {
        // Published, not expired, and addressed to this student (role, level, class)
        if (! $notice->isVisibleTo(auth()->user())) {
            return RemovedItemPage::response('notice', 'student.notices.index');
        }

        $notice->load('creator:id,name');

        return view('student.notices.show', compact('notice'));
    }
}
