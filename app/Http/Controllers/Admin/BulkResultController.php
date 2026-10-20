<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Result;
use App\Models\SchoolClass;
use App\Models\StudentProfile;
use App\Models\Term;
use App\Models\User;
use App\Services\FileUploadService;
use App\Services\TempUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BulkResultController extends Controller
{
    /**
     * Show the bulk upload form.
     */
    public function create(): View
    {
        $school = app('current.school');
        $classes = SchoolClass::where('is_active', true)->orderBy('name')->get();
        $currentSession = $school->currentSession();
        $currentTerm = $school->currentTerm();

        return view('admin.results.bulk-upload', compact('classes', 'currentSession', 'currentTerm'));
    }

    /**
     * Process and preview the bulk upload. Files are matched by username.
     */
    public function preview(Request $request): View
    {
        $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'session_id' => ['required', 'exists:academic_sessions,id'],
            'term_id' => ['required', 'exists:terms,id'],
            'result_files' => ['required', 'array', 'min:1'],
            'result_files.*' => ['file', 'mimes:pdf', 'max:10240'],
        ]);

        $classId = $request->input('class_id');
        $sessionId = $request->input('session_id');
        $termId = $request->input('term_id');
        $term = Term::with('session')->findOrFail($termId);

        // Get students in the selected class
        $students = User::where('role', 'student')
            ->whereHas('studentProfile', fn ($q) => $q->where('class_id', $classId)->enrolledByTerm($term))
            ->get()
            ->keyBy(fn ($s) => strtolower($s->username));

        $matches = [];
        $unmatched = [];
        $tempUploads = app(TempUploadService::class);
        $batch = $tempUploads->newBatch(TempUploadService::TYPE_BULK_RESULTS, app('current.school')->id);

        foreach ($request->file('result_files') as $file) {
            $filename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $normalizedName = strtolower(trim($filename));

            if ($students->has($normalizedName)) {
                $student = $students->get($normalizedName);

                // Check if result already exists for this student/session/term
                $existing = Result::where('student_id', $student->id)
                    ->where('session_id', $sessionId)
                    ->where('term_id', $termId)
                    ->exists();

                // Held until the admin confirms — in this upload's own folder, so it can't clash with anyone else's
                $tempPath = $tempUploads->store($file, $batch);

                $matches[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'username' => $student->username,
                    'filename' => $file->getClientOriginalName(),
                    'temp_path' => $tempPath,
                    'already_exists' => $existing,
                ];
            } else {
                $unmatched[] = $file->getClientOriginalName();
            }
        }

        $class = SchoolClass::find($classId);

        return view('admin.results.bulk-preview', compact(
            'matches',
            'unmatched',
            'classId',
            'sessionId',
            'termId',
            'class',
            'term',
        ));
    }

    /**
     * Import the matched results.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'class_id' => ['required', 'exists:classes,id'],
            'session_id' => ['required', 'exists:academic_sessions,id'],
            'term_id' => ['required', 'exists:terms,id'],
            'imports' => ['required', 'array', 'min:1'],
            'imports.*.student_id' => ['required', 'exists:users,id'],
            'imports.*.temp_path' => ['required', 'string'],
        ]);

        $imported = 0;
        $school = app('current.school');
        $uploadService = app(FileUploadService::class);
        $tempUploads = app(TempUploadService::class);
        $term = Term::with('session')->findOrFail($request->input('term_id'));
        $eligibleStudentIds = StudentProfile::where('class_id', $request->input('class_id'))
            ->enrolledByTerm($term)
            ->pluck('user_id')
            ->map(fn ($id) => (string) $id);

        if (collect($request->input('imports'))->pluck('student_id')->map(fn ($id) => (string) $id)->diff($eligibleStudentIds)->isNotEmpty()) {
            return redirect()->back()->withErrors([
                'imports' => __('Results cannot be uploaded for a student before their enrollment term.'),
            ]);
        }

        DB::transaction(function () use ($request, &$imported, $school, $uploadService, $tempUploads) {
            foreach ($request->input('imports') as $item) {
                // The path comes back from the browser — only accept this school's own preview files
                $fullPath = $tempUploads->resolve($item['temp_path'], TempUploadService::TYPE_BULK_RESULTS, $school->id);

                if ($fullPath === null) {
                    continue;
                }

                // Upload to Cloudinary
                $upload = $uploadService->uploadResultFromPath($fullPath, $school->id);

                // Delete old Cloudinary file if replacing an existing result
                $existing = Result::where('student_id', $item['student_id'])
                    ->where('session_id', $request->input('session_id'))
                    ->where('term_id', $request->input('term_id'))
                    ->first();

                if ($existing?->file_public_id) {
                    $uploadService->delete($existing->file_public_id);
                }

                Result::updateOrCreate(
                    [
                        'student_id' => $item['student_id'],
                        'session_id' => $request->input('session_id'),
                        'term_id' => $request->input('term_id'),
                    ],
                    [
                        'class_id' => $request->input('class_id'),
                        'file_url' => $upload['url'],
                        'file_public_id' => $upload['public_id'],
                        'uploaded_by' => auth()->id(),
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                        'status' => 'approved',
                    ],
                );

                // Clean up temp file
                @unlink($fullPath);
                $imported++;
            }
        });

        return redirect()->route('admin.results.index')
            ->with('success', __(':count results uploaded successfully.', ['count' => $imported]));
    }
}
