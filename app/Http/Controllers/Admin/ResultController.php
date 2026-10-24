<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Result;
use App\Models\SchoolClass;
use App\Models\StudentProfile;
use App\Models\Term;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use App\Services\RecentlyDeletedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ResultController extends Controller
{
    public function index(Request $request): View
    {
        $query = Result::with([
            'student:id,name,username',
            'class:id,name',
            'session:id,name',
            'term:id,name',
            'uploader:id,name',
        ]);

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->input('class_id'));
        }

        if ($request->filled('term_id')) {
            $query->where('term_id', $request->input('term_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('student', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%"));
        }

        $results = $query->orderByDesc('created_at')->paginate(10)->withQueryString();
        $classes = SchoolClass::where('is_active', true)->orderBy('name')->get();
        $terms = Term::orderByDesc('id')->take(6)->get();

        return view('admin.results.index', compact('results', 'classes', 'terms'));
    }

    public function create(): View
    {
        $school = app('current.school');
        $currentSession = $school->currentSession();
        $currentTerm = $school->currentTerm();

        return view('admin.results.create', compact('currentSession', 'currentTerm'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'exists:users,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'session_id' => ['required', 'exists:academic_sessions,id'],
            'term_id' => ['required', 'exists:terms,id'],
            'result_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'notes' => ['nullable', 'string'],
        ]);

        $school = app('current.school');
        $term = Term::with('session')->findOrFail($validated['term_id']);
        $eligible = StudentProfile::where('user_id', $validated['student_id'])
            ->where('class_id', $validated['class_id'])
            ->enrolledByTerm($term)
            ->exists();

        if (! $eligible) {
            return redirect()->back()->withInput()->withErrors([
                'student_id' => __('Results cannot be uploaded for a student before their enrollment term.'),
            ]);
        }

        // Check for duplicate before uploading — saves a wasted Cloudinary call.
        $alreadyExists = Result::where('student_id', $validated['student_id'])
            ->where('session_id', $validated['session_id'])
            ->where('term_id', $validated['term_id'])
            ->exists();

        if ($alreadyExists) {
            return redirect()->back()->withInput()
                ->with('error', __('A result already exists for this student in the selected term. Please delete the existing result first, then re-upload.'));
        }

        try {
            $upload = app(FileUploadService::class)->uploadResult($request->file('result_file'), $school->id);
        } catch (\Throwable $e) {
            Log::error('Result Cloudinary upload failed', [
                'school_id' => $school->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withInput()
                ->with('error', __('File upload failed. Please try again.'));
        }

        Result::releaseDeletedSlot((int) $validated['student_id'], (int) $validated['session_id'], (int) $validated['term_id']);

        try {
            $result = DB::transaction(function () use ($validated, $upload) {
                return Result::create([
                    'student_id' => $validated['student_id'],
                    'class_id' => $validated['class_id'],
                    'session_id' => $validated['session_id'],
                    'term_id' => $validated['term_id'],
                    'notes' => $validated['notes'] ?? null,
                    'file_url' => $upload['url'],
                    'file_public_id' => $upload['public_id'],
                    'uploaded_by' => auth()->id(),
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'status' => 'approved',
                ]);
            });
        } catch (\Throwable $e) {
            try {
                app(FileUploadService::class)->deleteRaw($upload['public_id']);
            } catch (\Throwable) {
                // Best-effort cleanup
            }

            Log::error('Result DB save failed after Cloudinary upload', [
                'school_id' => $school->id,
                'public_id' => $upload['public_id'],
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withInput()
                ->with('error', __('Failed to save result. The uploaded file has been removed. Please try again.'));
        }

        try {
            app(NotificationService::class)->notifyResultUploaded($result);
        } catch (\Throwable $e) {
            Log::warning('Result notification failed — result was saved successfully', [
                'result_id' => $result->id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('admin.results.index')
            ->with('success', __('Result uploaded.'));
    }

    public function edit(Result $result): View
    {
        $result->load(['student', 'class', 'session', 'term']);

        return view('admin.results.edit', compact('result'));
    }

    public function update(Request $request, Result $result): RedirectResponse
    {
        $request->validate([
            'result_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'replacement_reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $school = app('current.school');

        // Upload the replacement file first — roll back is easy if DB fails.
        try {
            $upload = app(FileUploadService::class)->uploadResult($request->file('result_file'), $school->id);
        } catch (\Throwable $e) {
            Log::error('Result replacement Cloudinary upload failed', [
                'school_id' => $school->id,
                'result_id' => $result->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withInput()
                ->with('error', __('File upload failed. Please try again.'));
        }

        $oldPublicId = $result->file_public_id;

        try {
            DB::transaction(function () use ($result, $upload, $request, $school) {
                $oldValues = [
                    'file_url' => $result->file_url,
                    'file_public_id' => $result->file_public_id,
                    'notes' => $result->notes,
                ];

                $result->update([
                    'file_url' => $upload['url'],
                    'file_public_id' => $upload['public_id'],
                    'notes' => $request->input('replacement_reason'),
                    'approved_by' => auth()->id(),
                    'approved_at' => now(),
                    'status' => 'approved',
                ]);

                AuditLog::create([
                    'school_id' => $school->id,
                    'user_id' => auth()->id(),
                    'action' => 'result.replaced',
                    'entity_type' => 'result',
                    'entity_id' => $result->id,
                    'old_values' => json_encode($oldValues),
                    'new_values' => json_encode([
                        'file_url' => $upload['url'],
                        'file_public_id' => $upload['public_id'],
                        'replacement_reason' => $request->input('replacement_reason'),
                    ]),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            });
        } catch (\Throwable $e) {
            // DB failed — orphan-cleanup the newly uploaded file.
            try {
                app(FileUploadService::class)->deleteRaw($upload['public_id']);
            } catch (\Throwable) {
                // Best-effort cleanup.
            }

            Log::error('Result replacement DB save failed', [
                'school_id' => $school->id,
                'result_id' => $result->id,
                'public_id' => $upload['public_id'],
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->withInput()
                ->with('error', __('Failed to save the replacement. The uploaded file has been removed. Please try again.'));
        }

        // DB succeeded — now safe to remove the old file from Cloudinary.
        if ($oldPublicId) {
            try {
                app(FileUploadService::class)->deleteRaw($oldPublicId);
            } catch (\Throwable $e) {
                Log::warning('Could not delete old result file from Cloudinary after replacement', [
                    'public_id' => $oldPublicId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('admin.results.show', $result)
            ->with('success', __('Result replaced successfully.'));
    }

    public function show(Result $result): View
    {
        $result->load(['student', 'class', 'session', 'term', 'uploader', 'approver']);

        return view('admin.results.show', compact('result'));
    }

    /**
     * Kept for Result::RESTORE_DAYS so the delete can be undone; the PDF stays on Cloudinary until then.
     */
    public function destroy(Result $result): RedirectResponse
    {
        $result->delete();

        return redirect()->route('admin.results.index')
            ->with('undo', app(RecentlyDeletedService::class)->deleted($result, 'admin'));
    }
}
