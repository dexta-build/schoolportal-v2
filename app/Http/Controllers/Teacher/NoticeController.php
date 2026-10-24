<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\SchoolLevel;
use App\Models\TeacherAction;
use App\Services\FileUploadService;
use App\Services\RecentlyDeletedService;
use App\Traits\NotifiesAdminsOnSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class NoticeController extends Controller
{
    use NotifiesAdminsOnSubmission;

    public function index(): View
    {
        $teacher = auth()->user();

        $notices = Notice::where('created_by', $teacher->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        // Load rejection reasons for rejected notices
        $rejectedNoticeIds = $notices->getCollection()->where('status', 'rejected')->pluck('id')->toArray();
        $rejectionReasons = [];
        if ($rejectedNoticeIds) {
            $rejectionReasons = TeacherAction::where('entity_type', 'notice')
                ->whereIn('entity_id', $rejectedNoticeIds)
                ->where('status', 'rejected')
                ->pluck('rejection_reason', 'entity_id')
                ->toArray();
        }

        return view('teacher.notices.index', compact('notices', 'rejectionReasons'));
    }

    /**
     * Read a school notice addressed to teachers (opened from a notification).
     */
    public function show(Notice $notice): View
    {
        $teacher = auth()->user();

        // Their own notice, or a school notice addressed to them
        abort_unless((int) $notice->created_by === (int) $teacher->id || $notice->isVisibleTo($teacher), 404);

        $notice->load('creator:id,name');

        return view('teacher.notices.show', compact('notice'));
    }

    public function create(): View
    {
        $teacher = auth()->user();
        $classes = $teacher->assignedClasses()->where('is_active', true)->orderBy('sort_order')->get();
        $assignedLevelIds = $classes->pluck('level_id')->unique()->values();
        $levels = SchoolLevel::whereIn('id', $assignedLevelIds)->where('is_active', true)->orderBy('sort_order')->get();

        return view('teacher.notices.create', compact('levels', 'classes'));
    }

    public function store(Request $request, FileUploadService $uploadService): RedirectResponse
    {
        $teacher = auth()->user();
        $assignedClasses = $teacher->assignedClasses()->get();
        $assignedClassIds = $assignedClasses->pluck('id')->toArray();
        $assignedLevelIds = $assignedClasses->pluck('level_id')->unique()->values()->toArray();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx'],
            'target_levels' => ['nullable', 'array'],
            'target_levels.*' => ['in:'.implode(',', $assignedLevelIds)],
            'target_classes' => ['nullable', 'array'],
            'target_classes.*' => ['in:'.implode(',', $assignedClassIds)],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['in:student,parent'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        unset($validated['file']);

        $fileData = [];
        $uploadedPublicId = null;

        if ($request->hasFile('file')) {
            $schoolId = $teacher->school_id;

            try {
                $uploaded = $uploadService->uploadNoticeFile($request->file('file'), $schoolId);
            } catch (\Throwable $e) {
                Log::error('Teacher notice file Cloudinary upload failed', [
                    'teacher_id' => $teacher->id,
                    'error' => $e->getMessage(),
                ]);

                return redirect()->back()->withInput()
                    ->with('error', __('File upload failed. Please try again.'));
            }

            $uploadedPublicId = $uploaded['public_id'];
            $fileData = [
                'file_url' => $uploaded['url'],
                'file_public_id' => $uploaded['public_id'],
                'file_name' => $request->file('file')->getClientOriginalName(),
            ];
        }

        try {
            DB::transaction(function () use ($validated, $fileData, $teacher) {
                $notice = Notice::create([
                    ...$validated,
                    ...$fileData,
                    'created_by' => $teacher->id,
                    'is_published' => false,
                    'status' => 'pending',
                ]);

                $action = TeacherAction::create([
                    'school_id' => $teacher->school_id,
                    'teacher_id' => $teacher->id,
                    'action_type' => 'post_notice',
                    'entity_type' => 'notice',
                    'entity_id' => $notice->id,
                    'status' => 'pending',
                ]);

                $this->notifyAdminsOfPendingSubmission($action, $teacher);
            });
        } catch (\Throwable $e) {
            if ($uploadedPublicId) {
                try {
                    $uploadService->delete($uploadedPublicId);
                } catch (\Throwable) {
                    // Best-effort cleanup
                }
            }

            Log::error('Teacher notice DB save failed after Cloudinary upload', [
                'teacher_id' => $teacher->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withInput()
                ->with('error', __('Failed to submit notice. The uploaded file has been removed. Please try again.'));
        }

        return redirect()->route('teacher.notices.index')
            ->with('success', __('Notice submitted for approval.'));
    }

    public function edit(Notice $notice): View
    {
        $teacher = auth()->user();

        abort_unless($notice->created_by === $teacher->id, 403);

        $classes = $teacher->assignedClasses()->where('is_active', true)->orderBy('sort_order')->get();
        $assignedLevelIds = $classes->pluck('level_id')->unique()->values();
        $levels = SchoolLevel::whereIn('id', $assignedLevelIds)->where('is_active', true)->orderBy('sort_order')->get();

        $rejectionReason = TeacherAction::where('entity_type', 'notice')
            ->where('entity_id', $notice->id)
            ->where('status', 'rejected')
            ->latest('reviewed_at')
            ->value('rejection_reason');

        return view('teacher.notices.edit', compact('notice', 'levels', 'classes', 'rejectionReason'));
    }

    public function update(Request $request, Notice $notice, FileUploadService $uploadService): RedirectResponse
    {
        $teacher = auth()->user();

        abort_unless($notice->created_by === $teacher->id, 403);

        $assignedClasses = $teacher->assignedClasses()->get();
        $assignedClassIds = $assignedClasses->pluck('id')->toArray();
        $assignedLevelIds = $assignedClasses->pluck('level_id')->unique()->values()->toArray();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx'],
            'target_levels' => ['nullable', 'array'],
            'target_levels.*' => ['in:'.implode(',', $assignedLevelIds)],
            'target_classes' => ['nullable', 'array'],
            'target_classes.*' => ['in:'.implode(',', $assignedClassIds)],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['in:student,parent'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        unset($validated['file'], $validated['remove_file']);

        $oldFilePublicId = null;
        $newFilePublicId = null;

        // Handle file upload: upload new file first, defer deletion of old until after DB succeeds
        if ($request->hasFile('file')) {
            try {
                $uploaded = $uploadService->uploadNoticeFile($request->file('file'), $teacher->school_id);
            } catch (\Throwable $e) {
                Log::error('Teacher notice file Cloudinary upload failed during update', [
                    'teacher_id' => $teacher->id,
                    'notice_id' => $notice->id,
                    'error' => $e->getMessage(),
                ]);

                return redirect()->back()->withInput()
                    ->with('error', __('File upload failed. Please try again.'));
            }

            $newFilePublicId = $uploaded['public_id'];
            $oldFilePublicId = $notice->file_public_id;
            $validated['file_url'] = $uploaded['url'];
            $validated['file_public_id'] = $uploaded['public_id'];
            $validated['file_name'] = $request->file('file')->getClientOriginalName();
        }

        // Handle file removal: defer Cloudinary deletion until after DB succeeds
        if ($request->boolean('remove_file') && $notice->file_public_id) {
            $oldFilePublicId = $notice->file_public_id;
            $validated['file_url'] = null;
            $validated['file_public_id'] = null;
            $validated['file_name'] = null;
        }

        try {
            DB::transaction(function () use ($validated, $notice, $teacher) {
                $notice->update([
                    ...$validated,
                    'status' => 'pending',
                    'is_published' => false,
                ]);

                // Reset the existing TeacherAction to pending
                $action = TeacherAction::where('entity_type', 'notice')
                    ->where('entity_id', $notice->id)
                    ->where('teacher_id', $teacher->id)
                    ->first();

                if ($action) {
                    $action->update([
                        'status' => 'pending',
                        'reviewed_by' => null,
                        'reviewed_at' => null,
                        'rejection_reason' => null,
                    ]);

                    $this->notifyAdminsOfPendingSubmission($action, $teacher);
                }
            });
        } catch (\Throwable $e) {
            // DB failed — clean up any newly uploaded file to avoid orphan
            if ($newFilePublicId) {
                try {
                    $uploadService->delete($newFilePublicId);
                } catch (\Throwable) {
                    // Best-effort cleanup
                }
            }

            Log::error('Teacher notice DB update failed', [
                'teacher_id' => $teacher->id,
                'notice_id' => $notice->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withInput()
                ->with('error', __('Failed to update notice. Please try again.'));
        }

        // DB succeeded — now safe to remove the old Cloudinary file
        if ($oldFilePublicId) {
            try {
                $uploadService->delete($oldFilePublicId);
            } catch (\Throwable $e) {
                Log::warning('Could not delete old notice file from Cloudinary', [
                    'public_id' => $oldFilePublicId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('teacher.notices.index')
            ->with('success', __('Notice updated and resubmitted for approval.'));
    }

    public function unpublish(Notice $notice): RedirectResponse
    {
        $teacher = auth()->user();
        abort_unless($notice->created_by === $teacher->id, 403);

        $notice->update([
            'is_published' => false,
        ]);

        return redirect()->route('teacher.notices.index')
            ->with('success', __('Notice unpublished.'));
    }

    public function publish(Notice $notice): RedirectResponse
    {
        $teacher = auth()->user();
        abort_unless($notice->created_by === $teacher->id, 403);
        abort_unless($notice->status === 'approved', 403);

        // Block publishing if the notice has expired
        if ($notice->expires_at && $notice->expires_at->isPast()) {
            return redirect()->route('teacher.notices.index')
                ->with('error', __('Cannot publish — this notice has passed its expiry date.'));
        }

        $notice->update([
            'is_published' => true,
            'published_at' => $notice->published_at ?? now(),
        ]);

        return redirect()->route('teacher.notices.index')
            ->with('success', __('Notice published.'));
    }

    /**
     * Deleted notices are kept for Notice::RESTORE_DAYS so the delete can be undone; files stay until then.
     */
    public function destroy(Notice $notice): RedirectResponse
    {
        abort_unless($notice->created_by === auth()->id(), 403);

        $notice->delete();

        return redirect()->route('teacher.notices.index')
            ->with('undo', app(RecentlyDeletedService::class)->deleted($notice, 'teacher'));
    }
}
