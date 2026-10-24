<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\FileUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Removes a permanently deleted school's files from Cloudinary. Runs on the queue because a large
 * school can take many API calls; takes the id only, since the school row is already gone.
 */
class DeleteSchoolFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    /** Cloudinary rate limits reset hourly, so later retries wait longer */
    public array $backoff = [60, 300, 900, 3600];

    public int $timeout = 240;

    public function __construct(public readonly int $schoolId, public readonly string $schoolName) {}

    public function handle(FileUploadService $uploads): void
    {
        $deleted = $uploads->deleteSchoolFolder($this->schoolId);

        Log::info('Deleted Cloudinary files of permanently deleted school', [
            'school_id' => $this->schoolId,
            'school' => $this->schoolName,
            'files_deleted' => $deleted,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Could not delete Cloudinary files of a deleted school — remove the folder by hand', [
            'school_id' => $this->schoolId,
            'school' => $this->schoolName,
            'folder' => "schoolportal/{$this->schoolId}",
            'error' => $e->getMessage(),
        ]);
    }
}
