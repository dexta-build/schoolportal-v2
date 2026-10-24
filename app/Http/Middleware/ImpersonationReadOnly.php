<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * While a super admin is viewing a school ("impersonating"), the school's data is read-only.
 *
 * A school's records belong to the school: the platform can look, for support, but never change
 * anything — so no school can say its records were altered by the platform.
 *
 *  - Ordinary form submissions (POST/PUT/PATCH/DELETE) are refused with a message.
 *  - Livewire sends every interaction as a POST, including harmless ones (picking a class), so
 *    those are let through but any database write during the request is refused instead.
 *  - Stopping impersonation and logging out always work.
 */
class ImpersonationReadOnly
{
    public const SESSION_KEY = 'impersonating_original_id';

    /** Tables the framework itself writes to on every request — never school data. */
    private const FRAMEWORK_TABLES = ['sessions', 'cache', 'cache_locks'];

    private const ALWAYS_ALLOWED_ROUTES = ['impersonate.stop', 'logout'];

    private const READ_ONLY_FLAG = 'impersonation_read_only';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->session()->has(self::SESSION_KEY)) {
            return $next($request);
        }

        if ($request->isMethodSafe() || in_array($request->route()?->getName(), self::ALWAYS_ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        if ($this->isLivewireRequest($request)) {
            $request->attributes->set(self::READ_ONLY_FLAG, true);
            $this->registerWriteGuard();

            return $next($request);
        }

        $message = __('You are viewing this school in read-only mode. Changes can only be made by the school\'s own staff.');

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403, ['X-Impersonation-Read-Only' => '1']);
        }

        // Shown in the read-only banner, which is on every page while viewing a school
        return redirect()->back()->with('impersonation_blocked', $message);
    }

    private function isLivewireRequest(Request $request): bool
    {
        return $request->hasHeader('X-Livewire') || str_ends_with($request->path(), 'livewire/update') || str_contains($request->path(), 'livewire-');
    }

    /**
     * Refuse any insert/update/delete during a flagged request — at the database level, so no
     * write path (models, bulk queries, raw SQL) can get around it.
     *
     * The listener lives on the connection for the whole process, so it is registered once and
     * only acts on the request that was flagged (a worker or test may serve many requests).
     */
    private function registerWriteGuard(): void
    {
        // Once per application (and so per database connection)
        if (app()->bound('impersonation.write_guard')) {
            return;
        }
        app()->instance('impersonation.write_guard', true);

        DB::beforeExecuting(function (string $query): void {
            if (! app()->bound('request') || ! request()->attributes->get(self::READ_ONLY_FLAG)) {
                return;
            }

            // INSERT INTO t / REPLACE INTO t / UPDATE t / DELETE FROM t / TRUNCATE t
            if (! preg_match('/^\s*(?:insert\s+(?:ignore\s+)?into|replace\s+into|update|delete\s+from|truncate(?:\s+table)?)\s+[`"]?(\w+)[`"]?/i', $query, $table)) {
                return;
            }

            if (in_array(strtolower($table[1]), self::FRAMEWORK_TABLES, true)) {
                return;
            }

            throw new HttpException(
                403,
                __('You are viewing this school in read-only mode. Changes can only be made by the school\'s own staff.'),
                null,
                ['X-Impersonation-Read-Only' => '1'],
            );
        });
    }
}
