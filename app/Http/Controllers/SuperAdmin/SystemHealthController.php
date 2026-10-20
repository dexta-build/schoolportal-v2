<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\User;
use App\Services\FileUsageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class SystemHealthController extends Controller
{
    public function __invoke(FileUsageService $fileUsageService): View
    {
        // ── Database ──────────────────────────────────────────────────────────
        $dbStatus = 'ok';
        $dbLatencyMs = null;

        try {
            $start = microtime(true);
            DB::statement('SELECT 1');
            $dbLatencyMs = round((microtime(true) - $start) * 1000, 2);
        } catch (Throwable) {
            $dbStatus = 'error';
        }

        // ── Queues ────────────────────────────────────────────────────────────
        $pendingJobs = 0;
        $failedJobs = 0;

        try {
            $pendingJobs = DB::table('jobs')->count();
        } catch (Throwable) {
        }

        try {
            $failedJobs = DB::table('failed_jobs')->count();
        } catch (Throwable) {
        }

        // ── Cache ─────────────────────────────────────────────────────────────
        $cacheStatus = 'ok';

        try {
            Cache::put('_health_check_probe', true, 10);
            $cacheStatus = Cache::get('_health_check_probe') === true ? 'ok' : 'error';
            Cache::forget('_health_check_probe');
        } catch (Throwable) {
            $cacheStatus = 'error';
        }

        // ── Storage ───────────────────────────────────────────────────────────
        $storagePath = storage_path();
        $diskTotal = (int) @disk_total_space($storagePath);
        $diskFree = (int) @disk_free_space($storagePath);
        $diskUsed = max(0, $diskTotal - $diskFree);
        $diskUsedPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 1) : 0;

        // ── File Usage (hosting inode limit) ──────────────────────────────────
        // Scanned hourly by `health:file-usage` — counting on every page load is too slow
        $fileUsage = $fileUsageService->latest();

        // ── Versions ──────────────────────────────────────────────────────────
        $phpVersion = PHP_VERSION;
        $laravelVersion = app()->version();

        // ── Environment & Config ──────────────────────────────────────────────
        $env = app()->environment();
        $debugMode = (bool) config('app.debug');
        $timezone = config('app.timezone');
        $cacheDriver = config('cache.default');
        $queueDriver = config('queue.default');
        $sessionDriver = config('session.driver');

        // ── Platform Stats ────────────────────────────────────────────────────
        $totalSchools = School::tenants()->count();
        $activeSchools = School::tenants()->where('is_active', true)->count();
        $totalUsers = User::withoutGlobalScopes()->whereNot('role', 'super_admin')->count();
        $activeUsers = User::withoutGlobalScopes()->whereNot('role', 'super_admin')->where('is_active', true)->count();

        $recentLogins = User::withoutGlobalScopes()
            ->whereNotNull('last_login_at')
            ->where('last_login_at', '>=', now()->subDay())
            ->count();

        // ── PHP Extensions ────────────────────────────────────────────────────
        $extensions = [
            'pdo' => extension_loaded('pdo'),
            'pdo_mysql' => extension_loaded('pdo_mysql'),
            'mbstring' => extension_loaded('mbstring'),
            'openssl' => extension_loaded('openssl'),
            'tokenizer' => extension_loaded('tokenizer'),
            'json' => extension_loaded('json'),
            'bcmath' => extension_loaded('bcmath'),
            'ctype' => extension_loaded('ctype'),
            'fileinfo' => extension_loaded('fileinfo'),
            'gd' => extension_loaded('gd'),
            'curl' => extension_loaded('curl'),
            'zip' => extension_loaded('zip'),
        ];

        // ── Writable Paths ────────────────────────────────────────────────────
        $paths = [
            'storage/app' => ['path' => storage_path('app'), 'writable' => is_writable(storage_path('app'))],
            'storage/framework' => ['path' => storage_path('framework'), 'writable' => is_writable(storage_path('framework'))],
            'storage/logs' => ['path' => storage_path('logs'), 'writable' => is_writable(storage_path('logs'))],
            'bootstrap/cache' => ['path' => base_path('bootstrap/cache'), 'writable' => is_writable(base_path('bootstrap/cache'))],
        ];

        // ── Overall Health Score ──────────────────────────────────────────────
        $checks = [
            $dbStatus === 'ok',
            $cacheStatus === 'ok',
            $failedJobs === 0,
            ! $debugMode || $env !== 'production',
            $diskUsedPercent < 85,
            $fileUsage === null || $fileUsage['percent'] < 85,
            collect($paths)->every(fn ($p) => $p['writable']),
        ];

        $passedChecks = count(array_filter($checks));
        $totalChecks = count($checks);
        $healthScore = (int) round(($passedChecks / $totalChecks) * 100);

        $healthStatus = match (true) {
            $healthScore >= 90 => 'healthy',
            $healthScore >= 60 => 'degraded',
            default => 'critical',
        };

        return view('super-admin.system-health', compact(
            'dbStatus', 'dbLatencyMs',
            'pendingJobs', 'failedJobs',
            'cacheStatus',
            'diskTotal', 'diskFree', 'diskUsed', 'diskUsedPercent',
            'fileUsage',
            'phpVersion', 'laravelVersion',
            'env', 'debugMode', 'timezone',
            'cacheDriver', 'queueDriver', 'sessionDriver',
            'totalSchools', 'activeSchools', 'totalUsers', 'activeUsers', 'recentLogins',
            'extensions', 'paths',
            'healthScore', 'healthStatus', 'passedChecks', 'totalChecks',
        ));
    }
}
