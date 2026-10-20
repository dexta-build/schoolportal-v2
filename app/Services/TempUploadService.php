<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Temporary files for upload → preview → confirm flows (student CSV import, bulk result PDFs).
 *
 * Each upload batch gets its own folder per school, so two admins (or two schools) uploading
 * a file with the same name can never overwrite each other. The confirm step sends the path
 * back from the browser, so resolve() only accepts paths inside the current school's folder
 * for that upload type — never "../" or another school's file.
 *
 * Abandoned files are removed daily by `storage:prune-temp`.
 */
class TempUploadService
{
    public const TYPE_STUDENT_IMPORT = 'imports';

    public const TYPE_BULK_RESULTS = 'bulk-results';

    /**
     * A new folder for one upload batch: "{type}/school_{id}/{uuid}".
     */
    public function newBatch(string $type, int $schoolId): string
    {
        return "{$type}/school_{$schoolId}/".Str::uuid()->toString();
    }

    /**
     * Store a file in a batch folder, keeping a sanitised version of its original name.
     *
     * @return string Path relative to the local disk, to send back on confirm.
     */
    public function store(UploadedFile $file, string $batch): string
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $safeName = trim((string) preg_replace('/[^A-Za-z0-9._-]/', '_', $name), '._') ?: 'file';

        return $file->storeAs($batch, "{$safeName}.{$extension}", 'local');
    }

    /**
     * Absolute path for a confirm-step path, or null if it isn't a file this school uploaded.
     */
    public function resolve(?string $path, string $type, int $schoolId): ?string
    {
        if ($path === null || ! $this->belongsTo($path, $type, $schoolId)) {
            return null;
        }

        $disk = Storage::disk('local');

        return $disk->exists($path) ? $disk->path($path) : null;
    }

    public function belongsTo(string $path, string $type, int $schoolId): bool
    {
        $pattern = '#^'.preg_quote($type, '#').'/school_'.$schoolId.'/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/[A-Za-z0-9_-][A-Za-z0-9._-]*$#';

        return preg_match($pattern, $path) === 1 && ! str_contains($path, '..');
    }
}
