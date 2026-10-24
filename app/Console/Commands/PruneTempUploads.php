<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SchoolDataExportService;
use App\Services\TempUploadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes temporary upload files left behind when an admin uploads a student CSV or
 * bulk result PDFs but never confirms the preview. Confirmed imports delete their own
 * files; abandoned ones would otherwise stay forever and count against the hosting
 * file limit.
 */
class PruneTempUploads extends Command
{
    /** Folders on the local disk that only hold short-lived files (upload previews, school data exports). */
    public const TEMP_DIRECTORIES = [TempUploadService::TYPE_STUDENT_IMPORT, TempUploadService::TYPE_BULK_RESULTS, SchoolDataExportService::TEMP_DIRECTORY];

    protected $signature = 'storage:prune-temp {--hours=24 : Delete files older than this many hours}';

    protected $description = 'Delete abandoned temporary upload files (student CSV imports, bulk result PDFs)';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subHours(max(1, (int) $this->option('hours')))->getTimestamp();
        $deleted = 0;

        foreach (self::TEMP_DIRECTORIES as $directory) {
            foreach ($disk->allFiles($directory) as $file) {
                if ($disk->lastModified($file) < $cutoff && $disk->delete($file)) {
                    $deleted++;
                }
            }

            // Remove now-empty subfolders so they don't count against the file limit either
            foreach (array_reverse($disk->allDirectories($directory)) as $subdirectory) {
                if ($disk->files($subdirectory) === [] && $disk->directories($subdirectory) === []) {
                    $disk->deleteDirectory($subdirectory);
                }
            }
        }

        $this->info("Deleted {$deleted} abandoned temporary upload file(s).");

        return self::SUCCESS;
    }
}
