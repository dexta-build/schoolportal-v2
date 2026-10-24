<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Phar;
use PharData;

/**
 * A complete copy of one school's records, handed over before the school is permanently deleted:
 * one CSV per table (every table with a school_id column), a list of its files with working links,
 * and a README. Built with PharData, which writes ZIP files without the zip extension.
 */
class SchoolDataExportService
{
    public const AUDIT_ACTION = 'school.data_exported';

    /** Folder on the local disk; storage:prune-temp clears anything left behind */
    public const TEMP_DIRECTORY = 'school-exports';

    /** Never leave the platform, even in an export for the school itself */
    private const SECRET_COLUMNS = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    /** Framework tables that happen to carry a school_id but hold no school records */
    private const SKIPPED_TABLES = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    /**
     * Builds the ZIP and records the export on the platform audit trail (the school's own trail is
     * deleted with it). Returns the path of a temporary file the caller sends and then removes.
     */
    public function export(School $school, User $exportedBy): string
    {
        $workDir = Storage::disk('local')->path(self::TEMP_DIRECTORY.'/'.Str::uuid());
        File::ensureDirectoryExists($workDir);

        try {
            $zipPath = $workDir.'/'.Str::slug($school->name).'-data-'.now()->format('Y-m-d').'.zip';
            $zip = new PharData($zipPath, 0, null, Phar::ZIP);

            $counts = [];
            $files = [];

            $schoolRow = (array) DB::table('schools')->where('id', $school->id)->first();
            $zip->addFile($this->writeCsv($workDir, 'school', [$schoolRow]), 'school.csv');

            foreach ($this->schoolTables() as $table) {
                $csv = $this->writeTableCsv($workDir, $table, $school->id, $files);
                $counts[$table] = $csv['rows'];
                $zip->addFile($csv['path'], "tables/{$table}.csv");
            }

            $zip->addFile($this->writeCsv($workDir, 'files', $files), 'files.csv');
            $zip->addFromString('README.txt', $this->readme($school, $exportedBy, $counts, count($files)));

            unset($zip); // flush the archive to disk

            $this->recordExport($school, $exportedBy, array_sum($counts), count($files));

            // Only the ZIP is kept; the caller deletes it after sending
            foreach (File::files($workDir) as $file) {
                if ($file->getFilename() !== basename($zipPath)) {
                    File::delete($file->getPathname());
                }
            }

            return $zipPath;
        } catch (\Throwable $e) {
            File::deleteDirectory($workDir);

            throw $e;
        }
    }

    /**
     * The latest export since the school was deactivated — what permanent deletion requires.
     */
    public function exportSinceDeactivation(School $school): ?AuditLog
    {
        if (! $school->deactivated_at) {
            return null;
        }

        return AuditLog::withoutGlobalScopes()
            ->where('action', self::AUDIT_ACTION)
            ->where('entity_type', 'school')
            ->where('entity_id', $school->id)
            ->where('created_at', '>=', $school->deactivated_at)
            ->latest('id')
            ->first();
    }

    /**
     * @return list<string>
     */
    private function schoolTables(): array
    {
        return collect(Schema::getTableListing(schemaQualified: false))
            ->reject(fn (string $table) => in_array($table, self::SKIPPED_TABLES, true))
            ->filter(fn (string $table) => Schema::hasColumn($table, 'school_id'))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, string>>  $files  collects every file link found in the table
     * @return array{path: string, rows: int}
     */
    private function writeTableCsv(string $dir, string $table, int $schoolId, array &$files): array
    {
        $columns = array_values(array_diff(Schema::getColumnListing($table), self::SECRET_COLUMNS));
        $urlColumns = array_filter($columns, fn (string $c) => str_ends_with($c, '_url'));

        $path = "{$dir}/{$table}.csv";
        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF"); // so Excel reads names with accents correctly
        fputcsv($handle, $columns);

        $rows = 0;
        $query = DB::table($table)->where('school_id', $schoolId)->select($columns);
        $query = in_array('id', $columns, true) ? $query->orderBy('id') : $query->orderBy($columns[0]);

        foreach ($query->lazy(500) as $row) {
            $row = (array) $row;
            fputcsv($handle, array_map(fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value), $row));
            $rows++;

            foreach ($urlColumns as $column) {
                if (! empty($row[$column])) {
                    $files[] = [
                        'table' => $table,
                        'record_id' => $row['id'] ?? '',
                        'column' => $column,
                        'link' => $this->downloadLink((string) $row[$column], $row[Str::replaceLast('_url', '_public_id', $column)] ?? null),
                    ];
                }
            }
        }

        fclose($handle);

        return ['path' => $path, 'rows' => $rows];
    }

    /**
     * Private documents (raw uploads) need a signed link to open.
     */
    private function downloadLink(string $url, ?string $publicId): string
    {
        if ($publicId && str_contains($url, '/raw/')) {
            try {
                return FileUploadService::signedRawUrl($publicId);
            } catch (\Throwable) {
                return $url;
            }
        }

        return $url;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeCsv(string $dir, string $name, array $rows): string
    {
        $path = "{$dir}/{$name}.csv";
        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF");

        if ($rows !== []) {
            $columns = array_values(array_diff(array_keys($rows[0]), self::SECRET_COLUMNS));
            fputcsv($handle, $columns);
            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($c) => $row[$c] ?? null, $columns));
            }
        }

        fclose($handle);

        return $path;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private function readme(School $school, User $exportedBy, array $counts, int $fileCount): string
    {
        $lines = [
            "Data export for {$school->name}",
            str_repeat('=', 40),
            '',
            'Exported: '.now()->format('j M Y, g:i A T'),
            'Exported by: '.($exportedBy->email ?? $exportedBy->username),
            '',
            'school.csv       The school\'s details and settings.',
            'tables/*.csv     Every record the school holds, one file per table (students, scores,',
            '                 report cards, CBT answers, notices, audit logs, ...). Passwords and',
            '                 two-factor secrets are never exported.',
            "files.csv        {$fileCount} uploaded files (result sheets, photos, attachments) with links.",
            '',
            'IMPORTANT: the file links stop working once the school is permanently deleted.',
            'Download any files you want to keep before then.',
            '',
            'Records per table:',
        ];

        foreach ($counts as $table => $count) {
            $lines[] = sprintf('  %-34s %d', $table, $count);
        }

        return implode("\r\n", $lines)."\r\n";
    }

    private function recordExport(School $school, User $exportedBy, int $records, int $files): void
    {
        $platformSchool = School::where('slug', School::PLATFORM_SLUG)->first();

        // On the platform trail (outlives the school) and on the school's own trail (its admins see who exported)
        $schoolIds = array_unique(array_filter([$platformSchool?->id, $school->id]));

        foreach ($schoolIds as $schoolId) {
            AuditLog::withoutGlobalScopes()->create([
                'school_id' => $schoolId,
                'user_id' => $exportedBy->id,
                'action' => self::AUDIT_ACTION,
                'entity_type' => 'school',
                'entity_id' => $school->id,
                'new_values' => ['school' => $school->name, 'records' => $records, 'files' => $files, 'exported_by_role' => $exportedBy->role],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        }
    }
}
