<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use App\Models\Assignment;
use App\Models\SchoolClass;
use App\Models\Term;
use App\Services\FileUploadService;
use App\Services\NotificationService;
use App\Services\RecentlyDeletedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $query = Assignment::with(['class:id,name', 'session:id,name', 'term:id,name', 'uploader:id,name']);

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->input('class_id'));
        }

        if ($request->filled('term_id')) {
            $query->where('term_id', $request->input('term_id'));
        }

        $assignments = $query->orderByDesc('created_at')->paginate(10)->withQueryString();
        $classes = SchoolClass::where('is_active', true)->orderBy('name')->get();
        $terms = Term::where('is_current', true)->orWhere('status', 'active')->get();

        return view('admin.assignments.index', compact('assignments', 'classes', 'terms'));
    }

    public function create(): View
    {
        $school = app('current.school');
        $currentSession = $school->currentSession();
        $currentTerm = $school->currentTerm();

        return view('admin.assignments.create', compact('currentSession', 'currentTerm'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'session_id' => ['required', 'exists:academic_sessions,id'],
            'term_id' => ['required', 'exists:terms,id'],
            'week_number' => ['required', 'integer', 'min:1', 'max:12'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'assignment_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png,gif,webp', 'max:10240'],
            'file_url' => ['nullable', 'url'],
        ]);

        if (! Assignment::claimWeek((int) $validated['class_id'], (int) $validated['session_id'], (int) $validated['term_id'], (int) $validated['week_number'])) {
            return back()->withInput()->withErrors(['week_number' => $this->weekTakenMessage((int) $validated['week_number'])]);
        }

        $fileUrl = $validated['file_url'] ?? null;
        $filePublicId = null;

        // File upload takes priority over URL
        if ($request->hasFile('assignment_file')) {
            $school = app('current.school');
            $upload = app(FileUploadService::class)->uploadAssignment($request->file('assignment_file'), $school->id);
            $fileUrl = $upload['url'];
            $filePublicId = $upload['public_id'];
        }

        $assignment = Assignment::create([
            'class_id' => $validated['class_id'],
            'session_id' => $validated['session_id'],
            'term_id' => $validated['term_id'],
            'week_number' => $validated['week_number'],
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'file_url' => $fileUrl,
            'file_public_id' => $filePublicId,
            'uploaded_by' => auth()->id(),
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'status' => 'approved',
        ]);

        try {
            app(NotificationService::class)->notifyAssignmentUploaded($assignment);
        } catch (\Throwable $e) {
            Log::warning('Assignment notification failed — assignment was saved successfully', [
                'assignment_id' => $assignment->id,
                'error' => $e->getMessage(),
            ]);
        }

        return redirect()->route('admin.assignments.index')
            ->with('success', __('Assignment created.'));
    }

    public function edit(Assignment $assignment): View
    {
        $classes = SchoolClass::where('is_active', true)->orderBy('name')->get();
        $sessions = AcademicSession::orderByDesc('start_date')->get();
        $terms = Term::where('session_id', $assignment->session_id)->get();

        return view('admin.assignments.edit', compact('assignment', 'classes', 'sessions', 'terms'));
    }

    public function update(Request $request, Assignment $assignment): RedirectResponse
    {
        $validated = $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'week_number' => ['required', 'integer', 'min:1', 'max:12'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'assignment_file' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png,gif,webp', 'max:10240'],
            'file_url' => ['nullable', 'url'],
        ]);

        if (! Assignment::claimWeek((int) $validated['class_id'], $assignment->session_id, $assignment->term_id, (int) $validated['week_number'], $assignment->id)) {
            return back()->withInput()->withErrors(['week_number' => $this->weekTakenMessage((int) $validated['week_number'])]);
        }

        $data = [
            'class_id' => $validated['class_id'],
            'week_number' => $validated['week_number'],
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
        ];

        if ($request->hasFile('assignment_file')) {
            // Delete old Cloudinary file if exists
            if ($assignment->file_public_id) {
                app(FileUploadService::class)->delete($assignment->file_public_id);
            }
            $school = app('current.school');
            $upload = app(FileUploadService::class)->uploadAssignment($request->file('assignment_file'), $school->id);
            $data['file_url'] = $upload['url'];
            $data['file_public_id'] = $upload['public_id'];
        } elseif ($request->filled('file_url') && $request->input('file_url') !== $assignment->getRawOriginal('file_url')) {
            // URL changed — clear old Cloudinary file
            if ($assignment->file_public_id) {
                app(FileUploadService::class)->delete($assignment->file_public_id);
            }
            $data['file_url'] = $validated['file_url'];
            $data['file_public_id'] = null;
        }

        $assignment->update($data);

        return redirect()->route('admin.assignments.index')
            ->with('success', __('Assignment updated.'));
    }

    /**
     * Deleted assignments are kept for Assignment::RESTORE_DAYS so the delete can be undone; the file stays until then.
     */
    public function destroy(Assignment $assignment): RedirectResponse
    {
        $assignment->delete();

        return redirect()->route('admin.assignments.index')
            ->with('undo', app(RecentlyDeletedService::class)->deleted($assignment, 'admin'));
    }

    private function weekTakenMessage(int $week): string
    {
        return __('This class already has an assignment for week :week this term. Edit that one instead.', ['week' => $week]);
    }
}
