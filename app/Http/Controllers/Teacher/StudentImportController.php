<?php

declare(strict_types=1);

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Jobs\ImportStudentsCsvJob;
use App\Rules\SafeCsvFile;
use App\Services\CsvImportService;
use App\Services\TempUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    public function __construct(
        private readonly CsvImportService $csvImportService,
    ) {}

    /**
     * Show the CSV upload form (scoped to teacher's classes).
     */
    public function create(): View
    {
        $teacher = auth()->user();
        $classes = $teacher->assignedClasses()
            ->where('is_active', true)
            ->with('level:id,name')
            ->orderBy('name')
            ->get();

        return view('teacher.students.import', compact('classes'));
    }

    /**
     * Parse the uploaded CSV and show preview.
     */
    public function preview(Request $request): View|RedirectResponse
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:2048', new SafeCsvFile],
            'default_password' => ['required', 'string', 'min:6'],
        ]);

        $file = $request->file('csv_file');
        $school = app('current.school');

        // Get teacher's allowed class names for validation
        $teacher = auth()->user();
        $allowedClassIds = $teacher->assignedClasses()->pluck('id')->toArray();

        try {
            $result = $this->csvImportService->parseCsv($file->getRealPath(), $school->id);
        } catch (\Throwable $e) {
            return redirect()->route('teacher.students.import')
                ->with('error', __('Failed to parse CSV file: :message', ['message' => $e->getMessage()]));
        }

        // Additional validation: ensure all classes belong to teacher's assigned classes
        foreach ($result['rows'] as &$row) {
            if ($row['_valid'] && ! empty($row['_class_id']) && ! in_array($row['_class_id'], $allowedClassIds)) {
                $row['_valid'] = false;
                $result['errors'][] = [
                    'line' => $row['_line'],
                    'message' => __('Class ":class" is not assigned to you.', ['class' => $row['class'] ?? '']),
                ];
            }
        }
        unset($row);

        // Recalculate counts
        $result['valid_count'] = count(array_filter($result['rows'], fn ($r) => $r['_valid']));
        $result['error_count'] = count($result['rows']) - $result['valid_count'];

        // Store file temporarily for the import step, in this upload's own folder
        $tempUploads = app(TempUploadService::class);
        $tempPath = $tempUploads->store($file, $tempUploads->newBatch(TempUploadService::TYPE_STUDENT_IMPORT, $school->id));

        return view('teacher.students.import-preview', [
            'rows' => $result['rows'],
            'errors' => $result['errors'],
            'validCount' => $result['valid_count'],
            'errorCount' => $result['error_count'],
            'tempPath' => $tempPath,
            'defaultPassword' => $request->input('default_password'),
        ]);
    }

    /**
     * Import the validated students.
     *
     * Dispatches an async job so large imports do not block the HTTP request.
     * Teacher-scoped class restrictions are passed to the job so they are
     * re-enforced during processing (mirrors the preview-step validation).
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'temp_path' => ['required', 'string'],
            'default_password' => ['required', 'string', 'min:6'],
        ]);

        $school = app('current.school');

        // The path comes back from the browser — only accept this school's own preview file
        if (app(TempUploadService::class)->resolve($request->input('temp_path'), TempUploadService::TYPE_STUDENT_IMPORT, $school->id) === null) {
            return redirect()->route('teacher.students.import')
                ->with('error', __('Import file expired. Please upload again.'));
        }

        $teacher = auth()->user();
        $allowedClassIds = $teacher->assignedClasses()->pluck('id')->toArray();
        $importKey = Str::uuid()->toString();

        ImportStudentsCsvJob::dispatch(
            schoolId: $school->id,
            storagePath: $request->input('temp_path'),
            defaultPassword: $request->input('default_password'),
            importKey: $importKey,
            allowedClassIds: $allowedClassIds,
        );

        return redirect()->route('teacher.students.index')
            ->with('success', __('Your student import is processing in the background. New students will appear on this page shortly — refresh in a moment.'));
    }

    /**
     * Download a CSV template.
     */
    public function template(): StreamedResponse
    {
        $headers = ['name', 'username', 'gender', 'class', 'admission_number', 'date_of_birth', 'address', 'blood_group'];

        return response()->streamDownload(function () use ($headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            fputcsv($handle, ['John Doe', 'john.doe', 'male', 'Primary 1', 'ADM001', '2015-03-15', '123 Main Street', 'O+']);
            fclose($handle);
        }, 'student_import_template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
