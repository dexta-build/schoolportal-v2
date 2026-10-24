<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Result;
use App\Models\SchoolClass;
use App\Models\StudentProfile;
use App\Models\TeacherAction;
use App\Models\Term;
use App\Services\FileUploadService;
use App\Traits\NotifiesAdminsOnSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ResultController extends Controller
{
    use NotifiesAdminsOnSubmission;

    public function index(Request $request): View
    {
        $teacher = auth()->user();
        $classIds = $teacher->assignedClasses()->pluck('id');

        $query = Result::with([
            'student:id,name,username',
            'class:id,name',
            'session:id,name',
            'term:id,name',
        ])->where('uploaded_by', $teacher->id);

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->input('class_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('student', fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('username', 'like', "%{$search}%"));
        }

        $results = $query->orderByDesc('created_at')->paginate(10)->withQueryString();

        $classes = SchoolClass::whereIn('id', $classIds)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('teacher.results.index', compact('results', 'classes'));
    }

    public function create(): View
    {
        $teacher = auth()->user();
        $school = app('current.school');
        $assignedClassIds = $teacher->assignedClasses()->pluck('id')->toArray();

        $currentSession = $school->currentSession();
        $currentTerm = $school->currentTerm();

        return view('teacher.results.create', compact('assignedClassIds', 'currentSession', 'currentTerm'));
    }

    public function store(Request $request): RedirectResponse
    {
        $teacher = auth()->user();
        $classIds = $teacher->assignedClasses()->pluck('id')->toArray();

        $validated = $request->validate([
            'student_id' => ['required', 'exists:users,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'session_id' => ['required', 'exists:academic_sessions,id'],
            'term_id' => ['required', 'exists:terms,id'],
            'result_file' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'notes' => ['nullable', 'string'],
        ]);

        // Ensure teacher can only upload to their assigned classes
        if (! in_array((int) $validated['class_id'], $classIds, true)) {
            abort(403, 'You can only upload results for your assigned classes.');
        }

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

        // One result sheet per student per term — checked before uploading to save a wasted Cloudinary call
        $alreadyExists = Result::where('student_id', $validated['student_id'])
            ->where('session_id', $validated['session_id'])
            ->where('term_id', $validated['term_id'])
            ->exists();

        if ($alreadyExists) {
            return redirect()->back()->withInput()->withErrors([
                'student_id' => __('This student already has a result sheet for that term. Edit it to replace the PDF.'),
            ]);
        }

        Result::releaseDeletedSlot((int) $validated['student_id'], (int) $validated['session_id'], (int) $validated['term_id']);

        $school = app('current.school');
        $upload = app(FileUploadService::class)->uploadResult($request->file('result_file'), $school->id);

        DB::transaction(function () use ($validated, $teacher, $upload) {
            $result = Result::create([
                'student_id' => $validated['student_id'],
                'class_id' => $validated['class_id'],
                'session_id' => $validated['session_id'],
                'term_id' => $validated['term_id'],
                'notes' => $validated['notes'] ?? null,
                'file_url' => $upload['url'],
                'file_public_id' => $upload['public_id'],
                'uploaded_by' => $teacher->id,
                'status' => 'pending',
            ]);

            $action = TeacherAction::create([
                'school_id' => $teacher->school_id,
                'teacher_id' => $teacher->id,
                'action_type' => 'upload_result',
                'entity_type' => 'result',
                'entity_id' => $result->id,
                'status' => 'pending',
            ]);

            $this->notifyAdminsOfPendingSubmission($action, $teacher);
        });

        return redirect()->route('teacher.results.index')
            ->with('success', __('Result uploaded and submitted for approval.'));
    }

    public function edit(Result $result): View
    {
        $teacher = auth()->user();

        abort_unless($result->uploaded_by === $teacher->id, 403);

        if ($result->status === 'approved') {
            return redirect()->route('teacher.results.index')
                ->with('error', __('Approved results cannot be edited.'));
        }

        $school = app('current.school');
        $assignedClassIds = $teacher->assignedClasses()->pluck('id')->toArray();
        $currentSession = $school->currentSession();
        $currentTerm = $school->currentTerm();

        $rejectionReason = TeacherAction::where('entity_type', 'result')
            ->where('entity_id', $result->id)
            ->where('status', 'rejected')
            ->latest('reviewed_at')
            ->value('rejection_reason');

        return view('teacher.results.edit', compact('result', 'assignedClassIds', 'currentSession', 'currentTerm', 'rejectionReason'));
    }

    public function update(Request $request, Result $result): RedirectResponse
    {
        $teacher = auth()->user();

        abort_unless($result->uploaded_by === $teacher->id, 403);

        if ($result->status === 'approved') {
            return redirect()->route('teacher.results.index')
                ->with('error', __('Approved results cannot be edited.'));
        }

        $classIds = $teacher->assignedClasses()->pluck('id')->toArray();

        $validated = $request->validate([
            'student_id' => ['required', 'exists:users,id'],
            'class_id' => ['required', 'exists:classes,id'],
            'result_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! in_array((int) $validated['class_id'], $classIds, true)) {
            abort(403, 'You can only upload results for your assigned classes.');
        }

        $data = [
            'student_id' => $validated['student_id'],
            'class_id' => $validated['class_id'],
            'notes' => $validated['notes'] ?? null,
        ];

        if ($request->hasFile('result_file')) {
            if ($result->file_public_id) {
                app(FileUploadService::class)->deleteRaw($result->file_public_id);
            }
            $school = app('current.school');
            $upload = app(FileUploadService::class)->uploadResult($request->file('result_file'), $school->id);
            $data['file_url'] = $upload['url'];
            $data['file_public_id'] = $upload['public_id'];
        }

        DB::transaction(function () use ($data, $result, $teacher) {
            $data['status'] = 'pending';
            $result->update($data);

            // Reset the existing TeacherAction to pending and re-notify admins
            $action = TeacherAction::where('entity_type', 'result')
                ->where('entity_id', $result->id)
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

        return redirect()->route('teacher.results.index')
            ->with('success', __('Result updated and resubmitted for approval.'));
    }
}
