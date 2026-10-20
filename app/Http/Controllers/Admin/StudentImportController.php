<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

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
     * Show the CSV upload form.
     */
    public function create(): View
    {
        return view('admin.students.import');
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

        try {
            $result = $this->csvImportService->parseCsv($file->getRealPath(), $school->id);
        } catch (\Throwable $e) {
            return redirect()->route('admin.students.import')
                ->with('error', __('Failed to parse CSV file: :message', ['message' => $e->getMessage()]));
        }

        // Store file temporarily for the import step, in this upload's own folder
        $tempUploads = app(TempUploadService::class);
        $tempPath = $tempUploads->store($file, $tempUploads->newBatch(TempUploadService::TYPE_STUDENT_IMPORT, $school->id));

        return view('admin.students.import-preview', [
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
     * The job processes the stored temp file in the queue worker.
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
            return redirect()->route('admin.students.import')
                ->with('error', __('Import file expired. Please upload again.'));
        }

        $importKey = Str::uuid()->toString();

        ImportStudentsCsvJob::dispatch(
            schoolId: $school->id,
            storagePath: $request->input('temp_path'),
            defaultPassword: $request->input('default_password'),
            importKey: $importKey,
        );

        return redirect()->route('admin.students.index')
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
            // Example row
            fputcsv($handle, ['John Doe', 'john.doe', 'male', 'Primary 1', 'ADM001', '2015-03-15', '123 Main Street', 'O+']);
            fclose($handle);
        }, 'student_import_template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }
}
