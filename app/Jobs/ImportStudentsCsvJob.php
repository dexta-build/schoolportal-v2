<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\School;
use App\Services\CsvImportService;
use App\Services\TempUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Process a student CSV import in the background.
 *
 * The controller stores the uploaded file to disk, then dispatches this job.
 * The job re-parses and imports the rows inside the queue worker so the HTTP
 * response is not held open for potentially 10–30 seconds.
 *
 * Callers can poll the result via the cache key "import:{importKey}:result":
 *   ['status' => 'pending']
 *   ['status' => 'completed', 'imported' => int, 'skipped_count' => int]
 *   ['status' => 'failed',    'message'  => string]
 */
class ImportStudentsCsvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Only one attempt — CSV parsing failures are deterministic; retrying won't help. */
    public int $tries = 1;

    /** 5-minute ceiling; large CSVs with many DB round-trips shouldn't exceed this. */
    public int $timeout = 300;

    /**
     * @param  int  $schoolId  Tenant the import belongs to.
     * @param  string  $storagePath  Relative path on the local disk from TempUploadService (e.g. "imports/school_1/{uuid}/students.csv").
     * @param  string  $defaultPassword  Plain-text default password for new students (will be bcrypt'd per row).
     * @param  string  $importKey  UUID used as a cache key so the caller can poll for the result.
     * @param  array  $allowedClassIds  When non-empty, rows whose class_id is not in this list are skipped
     *                                  (used for the teacher-scoped import).
     */
    public function __construct(
        private readonly int $schoolId,
        private readonly string $storagePath,
        private readonly string $defaultPassword,
        private readonly string $importKey,
        private readonly array $allowedClassIds = [],
    ) {}

    public function handle(CsvImportService $csvService): void
    {
        $fullPath = app(TempUploadService::class)
            ->resolve($this->storagePath, TempUploadService::TYPE_STUDENT_IMPORT, $this->schoolId);

        if ($fullPath === null) {
            $this->cacheResult(['status' => 'failed', 'message' => 'Import file not found.']);

            return;
        }

        // The queue worker runs many jobs in one process — remember the tenant binding so it
        // can be restored, otherwise every later job would be scoped to this school
        $previousSchool = app()->bound('current.school') ? app('current.school') : null;

        try {
            // Establish the tenant context expected by CsvImportService and the
            // BelongsToTenant model trait (both use app('current.school')).
            $school = School::find($this->schoolId);
            if (! $school) {
                throw new \RuntimeException("School {$this->schoolId} not found.");
            }

            app()->instance('current.school', $school);

            $parseResult = $csvService->parseCsv($fullPath, $this->schoolId);

            // Teacher-scoped imports: invalidate rows that belong to a class the
            // teacher is not assigned to (mirrors the check in the preview step).
            if (! empty($this->allowedClassIds)) {
                foreach ($parseResult['rows'] as &$row) {
                    if ($row['_valid'] && ! empty($row['_class_id']) && ! in_array($row['_class_id'], $this->allowedClassIds, true)) {
                        $row['_valid'] = false;
                    }
                }
                unset($row);
            }

            $importResult = $csvService->importRows($parseResult['rows'], $this->defaultPassword);

            $this->cacheResult([
                'status' => 'completed',
                'imported' => $importResult['imported'],
                'skipped_count' => count($importResult['skipped']),
            ]);
        } catch (\Throwable $e) {
            Log::error('CSV student import job failed', [
                'school_id' => $this->schoolId,
                'import_key' => $this->importKey,
                'error' => $e->getMessage(),
            ]);

            $this->cacheResult([
                'status' => 'failed',
                'message' => $e->getMessage(),
            ]);
        } finally {
            // Always clean up the temporary file, success or failure.
            @unlink($fullPath);

            if ($previousSchool) {
                app()->instance('current.school', $previousSchool);
            } else {
                app()->forgetInstance('current.school');
            }
        }
    }

    /**
     * Called by Laravel when the job fails permanently — i.e. when the worker
     * process is killed before the try/catch in handle() could run (e.g. timeout
     * signal, OOM kill). Ensures the polling cache key is always resolved so the
     * UI never spins forever waiting for a result that will never arrive.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ImportStudentsCsvJob failed permanently', [
            'school_id' => $this->schoolId,
            'import_key' => $this->importKey,
            'error' => $exception->getMessage(),
        ]);

        Cache::put(
            "import:{$this->importKey}:result",
            [
                'status' => 'failed',
                'message' => 'Import failed unexpectedly. Please try again or contact support.',
            ],
            now()->addHour()
        );

        // Clean up the temp file if the worker was killed before finally{} in handle() ran.
        $fullPath = app(TempUploadService::class)
            ->resolve($this->storagePath, TempUploadService::TYPE_STUDENT_IMPORT, $this->schoolId);
        if ($fullPath !== null) {
            @unlink($fullPath);
        }
    }

    /** @param array<string, mixed> $data */
    private function cacheResult(array $data): void
    {
        Cache::put("import:{$this->importKey}:result", $data, now()->addHour());
    }
}
