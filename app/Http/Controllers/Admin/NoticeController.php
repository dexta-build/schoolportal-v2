<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\SchoolClass;
use App\Models\SchoolLevel;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use App\Services\RecentlyDeletedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class NoticeController extends Controller
{
    public function index(): View
    {
        $notices = Notice::with('creator:id,name')
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('admin.notices.index', compact('notices'));
    }

    public function create(): View
    {
        $levels = SchoolLevel::where('is_active', true)->orderBy('sort_order')->get();
        $classes = SchoolClass::where('is_active', true)->orderBy('level_id')->orderBy('sort_order')->get();

        return view('admin.notices.create', compact('levels', 'classes'));
    }

    public function store(Request $request, FileUploadService $uploadService): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx'],
            'target_levels' => ['nullable', 'array'],
            'target_levels.*' => ['exists:school_levels,id'],
            'target_classes' => ['nullable', 'array'],
            'target_classes.*' => ['exists:classes,id'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['in:student,parent,teacher'],
            'is_published' => ['boolean'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        unset($validated['file']);

        $fileData = [];
        $uploadedPublicId = null;

        if ($request->hasFile('file')) {
            $schoolId = app('current.school')->id;

            try {
                $uploaded = $uploadService->uploadNoticeFile($request->file('file'), $schoolId);
            } catch (\Throwable $e) {
                Log::error('Notice file Cloudinary upload failed', [
                    'school_id' => $schoolId,
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
            $notice = DB::transaction(function () use ($validated, $fileData) {
                return Notice::create([
                    ...$validated,
                    ...$fileData,
                    'created_by' => auth()->id(),
                    'published_at' => ($validated['is_published'] ?? false) ? now() : null,
                    'status' => 'approved',
                ]);
            });
        } catch (\Throwable $e) {
            if ($uploadedPublicId) {
                try {
                    $uploadService->delete($uploadedPublicId);
                } catch (\Throwable) {
                    // Best-effort cleanup
                }
            }

            Log::error('Notice DB save failed after Cloudinary upload', [
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withInput()
                ->with('error', __('Failed to save notice. The uploaded file has been removed. Please try again.'));
        }

        if ($notice->is_published) {
            app(NotificationService::class)->notifyNoticePublished($notice);
        }

        return redirect()->route('admin.notices.index')
            ->with('success', __('Notice created.'));
    }

    public function edit(Notice $notice): View
    {
        abort_unless($notice->created_by === auth()->id(), 403);

        $levels = SchoolLevel::where('is_active', true)->orderBy('sort_order')->get();
        $classes = SchoolClass::where('is_active', true)->orderBy('level_id')->orderBy('sort_order')->get();

        return view('admin.notices.edit', compact('notice', 'levels', 'classes'));
    }

    public function update(Request $request, Notice $notice, FileUploadService $uploadService): RedirectResponse
    {
        abort_unless($notice->created_by === auth()->id(), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'file' => ['nullable', 'file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx'],
            'target_levels' => ['nullable', 'array'],
            'target_levels.*' => ['exists:school_levels,id'],
            'target_classes' => ['nullable', 'array'],
            'target_classes.*' => ['exists:classes,id'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['in:student,parent,teacher'],
            'is_published' => ['boolean'],
            'expires_at' => ['nullable', 'date'],
        ]);

        unset($validated['file'], $validated['remove_file']);

        if (($validated['is_published'] ?? false) && ! $notice->published_at) {
            $validated['published_at'] = now();
        }

        $oldFilePublicId = null;
        $newFilePublicId = null;

        // Handle file upload: upload new file first, defer old-file deletion until after DB succeeds
        if ($request->hasFile('file')) {
            $schoolId = app('current.school')->id;

            try {
                $uploaded = $uploadService->uploadNoticeFile($request->file('file'), $schoolId);
            } catch (\Throwable $e) {
                Log::error('Notice file Cloudinary upload failed during update', [
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

        $wasPublished = $notice->is_published;

        try {
            $notice->update($validated);
        } catch (\Throwable $e) {
            // DB failed — clean up any newly uploaded file to avoid orphan
            if ($newFilePublicId) {
                try {
                    $uploadService->delete($newFilePublicId);
                } catch (\Throwable) {
                    // Best-effort cleanup
                }
            }

            Log::error('Notice DB update failed', [
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

        // Notify if notice just became published
        if (! $wasPublished && $notice->is_published) {
            app(NotificationService::class)->notifyNoticePublished($notice);
        }

        return redirect()->route('admin.notices.index')
            ->with('success', __('Notice updated.'));
    }

    /**
     * Deleted notices are kept for Notice::RESTORE_DAYS so the delete can be undone; files stay until then.
     */
    public function destroy(Notice $notice): RedirectResponse
    {
        abort_unless($notice->created_by === auth()->id(), 403);

        $notice->delete();

        return redirect()->route('admin.notices.index')
            ->with('undo', app(RecentlyDeletedService::class)->deleted($notice, 'admin'));
    }

    public function unpublish(Notice $notice): RedirectResponse
    {
        abort_unless($notice->created_by === auth()->id(), 403);

        $notice->update([
            'is_published' => false,
        ]);

        return redirect()->route('admin.notices.index')
            ->with('success', __('Notice unpublished.'));
    }

    public function publish(Notice $notice): RedirectResponse
    {
        abort_unless($notice->created_by === auth()->id(), 403);
        abort_unless($notice->status === 'approved', 403);

        // Block publishing if the notice has expired
        if ($notice->expires_at && $notice->expires_at->isPast()) {
            return redirect()->route('admin.notices.index')
                ->with('error', __('Cannot publish — this notice has passed its expiry date.'));
        }

        $notice->update([
            'is_published' => true,
            'published_at' => $notice->published_at ?? now(),
        ]);

        app(NotificationService::class)->notifyNoticePublished($notice);

        return redirect()->route('admin.notices.index')
            ->with('success', __('Notice published.'));
    }
}
