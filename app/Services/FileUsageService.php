<?php

declare(strict_types=1);

namespace App\Services;

use FilesystemIterator;
use Illuminate\Support\Facades\Cache;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Counts files + directories (inodes) under the hosting account's home directory.
 *
 * Shared hosts (cPanel/CloudLinux) cap the number of inodes per account. When the
 * cap is hit every write fails with "Disk quota exceeded" even if plenty of disk
 * space is left, so we track it separately from disk space.
 */
class FileUsageService
{
    public const CACHE_KEY = 'health:file_usage';

    /**
     * Scan the configured path and store the result in the cache.
     *
     * @return array{total: int, limit: int, percent: float, folders: array<string, int>, scanned_at: string}
     */
    public function scan(): array
    {
        $root = $this->scanPath();
        $folders = [];

        foreach (new FilesystemIterator($root, FilesystemIterator::SKIP_DOTS) as $entry) {
            $folders[$entry->getFilename()] = 1 + ($entry->isDir() && ! $entry->isLink() ? $this->countEntries($entry->getPathname()) : 0);
        }

        arsort($folders);

        $total = array_sum($folders);
        $limit = $this->limit();

        $result = [
            'total' => $total,
            'limit' => $limit,
            'percent' => $limit > 0 ? round(($total / $limit) * 100, 1) : 0.0,
            'folders' => array_slice($folders, 0, 10, true),
            'scanned_at' => now()->toIso8601String(),
        ];

        Cache::forever(self::CACHE_KEY, $result);

        return $result;
    }

    /**
     * The most recent scan result, or null if no scan has run yet.
     *
     * @return array{total: int, limit: int, percent: float, folders: array<string, int>, scanned_at: string}|null
     */
    public function latest(): ?array
    {
        try {
            return Cache::get(self::CACHE_KEY);
        } catch (Throwable) {
            return null;
        }
    }

    public function scanPath(): string
    {
        return (string) (config('services.platform.file_usage_path') ?: dirname(base_path()));
    }

    public function limit(): int
    {
        return (int) config('services.platform.file_limit', 600000);
    }

    private function countEntries(string $path): int
    {
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST,
                RecursiveIteratorIterator::CATCH_GET_CHILD,
            );

            return iterator_count($iterator);
        } catch (Throwable) {
            return 0;
        }
    }
}
