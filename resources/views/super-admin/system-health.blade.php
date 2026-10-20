<x-layouts::app :title="__('System Health')">
    <div class="space-y-6">
        {{-- ── Header ────────────────────────────────────────────────────────── --}}
        <x-admin-header
            :title="__('System Health')"
            :description="__('Live status of platform infrastructure, services, and configuration')"
        >
            <flux:button
                variant="subtle" size="sm" icon="arrow-path"
                onclick="window.location.reload()"
                aria-label="{{ __('Refresh') }}"
            >
                {{ __('Refresh') }}
            </flux:button>
        </x-admin-header>

        {{-- ── Overall Health Score ────────────────────────────────────────── --}}
        @php
            $scoreColor = match ($healthStatus) {
                'healthy'  => 'emerald',
                'degraded' => 'amber',
                default    => 'red',
            };
            $scoreIcon = match ($healthStatus) {
                'healthy'  => 'check-circle',
                'degraded' => 'exclamation-triangle',
                default    => 'x-circle',
            };
            $scoreLabel = match ($healthStatus) {
                'healthy'  => __('All systems operational'),
                'degraded' => __('Some issues detected — review below'),
                default    => __('Critical issues require attention'),
            };
            $ringBg = match ($healthStatus) {
                'healthy'  => 'text-emerald-200 dark:text-emerald-900',
                'degraded' => 'text-amber-200 dark:text-amber-900',
                default    => 'text-red-200 dark:text-red-900',
            };
            $ringFg = match ($healthStatus) {
                'healthy'  => 'text-emerald-500',
                'degraded' => 'text-amber-500',
                default    => 'text-red-500',
            };
            $scoreNumClass = match ($healthStatus) {
                'healthy'  => 'text-emerald-700 dark:text-emerald-300',
                'degraded' => 'text-amber-700 dark:text-amber-300',
                default    => 'text-red-700 dark:text-red-300',
            };
            $scoreLabelClass = match ($healthStatus) {
                'healthy'  => 'text-emerald-600 dark:text-emerald-400',
                'degraded' => 'text-amber-600 dark:text-amber-400',
                default    => 'text-red-600 dark:text-red-400',
            };
            $scoreHeadingClass = match ($healthStatus) {
                'healthy'  => 'text-emerald-900 dark:text-emerald-100',
                'degraded' => 'text-amber-900 dark:text-amber-100',
                default    => 'text-red-900 dark:text-red-100',
            };
            $scoreBodyClass = match ($healthStatus) {
                'healthy'  => 'text-emerald-700 dark:text-emerald-300',
                'degraded' => 'text-amber-700 dark:text-amber-300',
                default    => 'text-red-700 dark:text-red-300',
            };
            $decorBgClass = match ($healthStatus) {
                'healthy'  => 'bg-emerald-500',
                'degraded' => 'bg-amber-500',
                default    => 'bg-red-500',
            };
        @endphp

        <div @class([
            'relative overflow-hidden rounded-2xl border p-6',
            'border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30' => $healthStatus === 'healthy',
            'border-amber-200 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30'         => $healthStatus === 'degraded',
            'border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-950/30'                 => $healthStatus === 'critical',
        ])>
            {{-- Background decorative arc --}}
            <div class="pointer-events-none absolute -right-16 -top-16 h-64 w-64 rounded-full opacity-10 {{ $decorBgClass }}"></div>

            <div class="relative flex flex-col sm:flex-row sm:items-center gap-6">
                {{-- Score ring --}}
                <div class="shrink-0 flex flex-col items-center gap-1.5">
                    <div class="relative h-24 w-24">
                        <svg class="h-24 w-24 -rotate-90" viewBox="0 0 96 96">
                            <circle cx="48" cy="48" r="40" fill="none" stroke="currentColor" stroke-width="8"
                                class="{{ $ringBg }}" />
                            <circle cx="48" cy="48" r="40" fill="none" stroke="currentColor" stroke-width="8"
                                stroke-linecap="round"
                                stroke-dasharray="{{ round(2 * M_PI * 40, 2) }}"
                                stroke-dashoffset="{{ round(2 * M_PI * 40 * (1 - $healthScore / 100), 2) }}"
                                class="transition-all duration-1000 {{ $ringFg }}" />
                        </svg>
                        <div class="absolute inset-0 flex flex-col items-center justify-center">
                            <span class="text-2xl font-bold {{ $scoreNumClass }}">
                                {{ $healthScore }}%
                            </span>
                        </div>
                    </div>
                    <span class="text-xs font-medium {{ $scoreLabelClass }}">
                        {{ $passedChecks }}/{{ $totalChecks }} {{ __('checks') }}
                    </span>
                </div>

                {{-- Status text --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <flux:icon :icon="$scoreIcon" class="w-5 h-5 shrink-0 {{ $scoreLabelClass }}" />
                        <h2 class="text-base font-semibold {{ $scoreHeadingClass }}">
                            {{ $scoreLabel }}
                        </h2>
                    </div>
                    <p class="mt-1 text-sm {{ $scoreBodyClass }}">
                        {{ __('Last checked:') }} {{ now()->format('d M Y, H:i:s') }} ({{ config('app.timezone') }})
                    </p>
                    <div class="mt-3 flex flex-wrap gap-4 text-sm">
                        <div class="flex items-center gap-1.5 @if($healthStatus==='healthy') text-emerald-800 dark:text-emerald-200 @elseif($healthStatus==='degraded') text-amber-800 dark:text-amber-200 @else text-red-800 dark:text-red-200 @endif">
                            <flux:icon.building-office-2 class="w-4 h-4 opacity-70" />
                            <span>{{ $activeSchools }}/{{ $totalSchools }} {{ __('schools active') }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 @if($healthStatus==='healthy') text-emerald-800 dark:text-emerald-200 @elseif($healthStatus==='degraded') text-amber-800 dark:text-amber-200 @else text-red-800 dark:text-red-200 @endif">
                            <flux:icon.users class="w-4 h-4 opacity-70" />
                            <span>{{ number_format($activeUsers) }}/{{ number_format($totalUsers) }} {{ __('users active') }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 @if($healthStatus==='healthy') text-emerald-800 dark:text-emerald-200 @elseif($healthStatus==='degraded') text-amber-800 dark:text-amber-200 @else text-red-800 dark:text-red-200 @endif">
                            <flux:icon.arrow-right-end-on-rectangle class="w-4 h-4 opacity-70" />
                            <span>{{ number_format($recentLogins) }} {{ __('logins in last 24h') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Core Services Grid ───────────────────────────────────────────── --}}
        <section aria-label="{{ __('Core services') }}">
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                {{ __('Core Services') }}
            </h3>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

                {{-- Database --}}
                <div @class([
                    'group relative overflow-hidden rounded-xl border p-4 transition-shadow hover:shadow-md',
                    'border-emerald-200 bg-white dark:border-emerald-800 dark:bg-zinc-900' => $dbStatus === 'ok',
                    'border-red-200 bg-white dark:border-red-800 dark:bg-zinc-900'         => $dbStatus !== 'ok',
                ])>
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <div @class([
                                'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                                'bg-emerald-100 dark:bg-emerald-900/40' => $dbStatus === 'ok',
                                'bg-red-100 dark:bg-red-900/40'         => $dbStatus !== 'ok',
                            ])>
                                <flux:icon.circle-stack @class(['w-5 h-5', 'text-emerald-600 dark:text-emerald-400' => $dbStatus==='ok', 'text-red-600 dark:text-red-400' => $dbStatus!=='ok']) />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Database') }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">MySQL</p>
                            </div>
                        </div>
                        <x-health-badge :status="$dbStatus === 'ok'" />
                    </div>
                    @if ($dbLatencyMs !== null)
                        <div class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Latency:') }} <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $dbLatencyMs }}ms</span>
                        </div>
                    @endif
                </div>

                {{-- Cache --}}
                <div @class([
                    'group relative overflow-hidden rounded-xl border p-4 transition-shadow hover:shadow-md',
                    'border-emerald-200 bg-white dark:border-emerald-800 dark:bg-zinc-900' => $cacheStatus === 'ok',
                    'border-red-200 bg-white dark:border-red-800 dark:bg-zinc-900'         => $cacheStatus !== 'ok',
                ])>
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <div @class([
                                'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                                'bg-emerald-100 dark:bg-emerald-900/40' => $cacheStatus === 'ok',
                                'bg-red-100 dark:bg-red-900/40'         => $cacheStatus !== 'ok',
                            ])>
                                <flux:icon.bolt @class(['w-5 h-5', 'text-emerald-600 dark:text-emerald-400' => $cacheStatus==='ok', 'text-red-600 dark:text-red-400' => $cacheStatus!=='ok']) />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Cache') }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ ucfirst($cacheDriver) }}</p>
                            </div>
                        </div>
                        <x-health-badge :status="$cacheStatus === 'ok'" />
                    </div>
                    <div class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Driver:') }} <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $cacheDriver }}</span>
                    </div>
                </div>

                {{-- Queue --}}
                @php $queueOk = $failedJobs === 0; @endphp
                <div @class([
                    'group relative overflow-hidden rounded-xl border p-4 transition-shadow hover:shadow-md',
                    'border-emerald-200 bg-white dark:border-emerald-800 dark:bg-zinc-900' => $queueOk,
                    'border-amber-200 bg-white dark:border-amber-800 dark:bg-zinc-900'     => !$queueOk,
                ])>
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <div @class([
                                'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                                'bg-emerald-100 dark:bg-emerald-900/40' => $queueOk,
                                'bg-amber-100 dark:bg-amber-900/40'     => !$queueOk,
                            ])>
                                <flux:icon.queue-list @class(['w-5 h-5', 'text-emerald-600 dark:text-emerald-400' => $queueOk, 'text-amber-600 dark:text-amber-400' => !$queueOk]) />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Queue') }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ ucfirst($queueDriver) }}</p>
                            </div>
                        </div>
                        @if ($queueOk)
                            <x-health-badge :status="true" />
                        @else
                            <flux:badge color="amber" size="sm">{{ __('Warning') }}</flux:badge>
                        @endif
                    </div>
                    <div class="mt-3 flex items-center gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ __('Pending:') }} <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ number_format($pendingJobs) }}</span></span>
                        <span @class(['font-medium', 'text-red-600 dark:text-red-400' => $failedJobs > 0, 'text-zinc-700 dark:text-zinc-200' => $failedJobs === 0])>
                            {{ __('Failed:') }} {{ number_format($failedJobs) }}
                        </span>
                    </div>
                </div>

                {{-- Storage --}}
                @php $storageOk = $diskUsedPercent < 85; @endphp
                <div @class([
                    'group relative overflow-hidden rounded-xl border p-4 transition-shadow hover:shadow-md',
                    'border-emerald-200 bg-white dark:border-emerald-800 dark:bg-zinc-900' => $storageOk && $diskUsedPercent < 70,
                    'border-amber-200 bg-white dark:border-amber-800 dark:bg-zinc-900'     => $diskUsedPercent >= 70 && $storageOk,
                    'border-red-200 bg-white dark:border-red-800 dark:bg-zinc-900'         => !$storageOk,
                ])>
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex items-center gap-2.5">
                            <div @class([
                                'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                                'bg-emerald-100 dark:bg-emerald-900/40' => $diskUsedPercent < 70,
                                'bg-amber-100 dark:bg-amber-900/40'     => $diskUsedPercent >= 70 && $storageOk,
                                'bg-red-100 dark:bg-red-900/40'         => !$storageOk,
                            ])>
                                <flux:icon.server @class([
                                    'w-5 h-5',
                                    'text-emerald-600 dark:text-emerald-400' => $diskUsedPercent < 70,
                                    'text-amber-600 dark:text-amber-400'     => $diskUsedPercent >= 70 && $storageOk,
                                    'text-red-600 dark:text-red-400'         => !$storageOk,
                                ]) />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Storage') }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Server disk') }}</p>
                            </div>
                        </div>
                        @if (!$storageOk)
                            <flux:badge color="red" size="sm">{{ __('Critical') }}</flux:badge>
                        @elseif ($diskUsedPercent >= 70)
                            <flux:badge color="amber" size="sm">{{ __('Warning') }}</flux:badge>
                        @else
                            <x-health-badge :status="true" />
                        @endif
                    </div>
                    <div class="mt-3">
                        <div class="flex justify-between text-xs text-zinc-500 dark:text-zinc-400 mb-1">
                            <span>{{ number_format($diskUsedPercent, 1) }}% {{ __('used') }}</span>
                            <span>{{ \App\Helpers\FormatBytes::format($diskFree) }} {{ __('free') }}</span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div
                                class="h-1.5 rounded-full transition-all duration-700 @if($diskUsedPercent >= 85) bg-red-500 @elseif($diskUsedPercent >= 70) bg-amber-500 @else bg-emerald-500 @endif"
                                style="width: {{ min(100, $diskUsedPercent) }}%"
                            ></div>
                        </div>
                        <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                            {{ \App\Helpers\FormatBytes::format($diskUsed) }} / {{ \App\Helpers\FormatBytes::format($diskTotal) }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        {{-- ── Hosting File Usage (inode limit) ─────────────────────────────── --}}
        @php
            $filePercent = $fileUsage['percent'] ?? 0;
            $fileLevel = match (true) {
                $fileUsage === null => 'unknown',
                $filePercent >= 85  => 'critical',
                $filePercent >= 70  => 'warning',
                default             => 'ok',
            };
        @endphp
        <section aria-label="{{ __('Hosting file usage') }}" @class([
            'rounded-xl border bg-white dark:bg-zinc-900',
            'border-zinc-200 dark:border-zinc-700'   => in_array($fileLevel, ['ok', 'unknown'], true),
            'border-amber-200 dark:border-amber-800' => $fileLevel === 'warning',
            'border-red-200 dark:border-red-800'     => $fileLevel === 'critical',
        ])>
            <div class="flex items-start justify-between gap-2 border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
                <div>
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Hosting File Usage') }}</h3>
                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Files and folders on the hosting account. When the limit is reached every write fails with "Disk quota exceeded".') }}
                    </p>
                </div>
                @if ($fileLevel === 'critical')
                    <flux:badge color="red" size="sm">{{ __('Critical') }}</flux:badge>
                @elseif ($fileLevel === 'warning')
                    <flux:badge color="amber" size="sm">{{ __('Warning') }}</flux:badge>
                @elseif ($fileLevel === 'ok')
                    <x-health-badge :status="true" />
                @endif
            </div>

            @if ($fileUsage === null)
                <p class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Not scanned yet. It runs hourly via the scheduler, or run') }}
                    <code class="rounded bg-zinc-100 px-1 py-0.5 font-mono text-xs dark:bg-zinc-800">php artisan health:file-usage</code>.
                </p>
            @else
                <div class="grid gap-6 p-4 md:grid-cols-2">
                    <div>
                        <div class="mb-1 flex justify-between text-xs text-zinc-500 dark:text-zinc-400">
                            <span>{{ number_format($filePercent, 1) }}% {{ __('used') }}</span>
                            <span>{{ number_format($fileUsage['total']) }} / {{ number_format($fileUsage['limit']) }} {{ __('files') }}</span>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div
                                class="h-1.5 rounded-full transition-all duration-700 @if($fileLevel === 'critical') bg-red-500 @elseif($fileLevel === 'warning') bg-amber-500 @else bg-emerald-500 @endif"
                                style="width: {{ min(100, $filePercent) }}%"
                            ></div>
                        </div>
                        <p class="mt-2 text-xs text-zinc-400 dark:text-zinc-500">
                            {{ __('Last scanned') }} {{ \Illuminate\Support\Carbon::parse($fileUsage['scanned_at'])->diffForHumans() }}
                        </p>
                    </div>

                    <div>
                        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Largest folders') }}</p>
                        <ul class="divide-y divide-zinc-50 text-sm dark:divide-zinc-800">
                            @foreach ($fileUsage['folders'] as $folder => $count)
                                <li class="flex items-center justify-between py-1.5">
                                    <span class="truncate font-mono text-xs text-zinc-600 dark:text-zinc-400">{{ $folder }}</span>
                                    <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ number_format($count) }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
        </section>

        {{-- ── Environment & Runtime ────────────────────────────────────────── --}}
        <div class="grid gap-4 lg:grid-cols-2">

            {{-- Environment Info --}}
            <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Runtime & Environment') }}</h3>
                </div>
                <div class="divide-y divide-zinc-50 dark:divide-zinc-800">
                    @foreach ([
                        ['label' => __('PHP Version'),     'value' => $phpVersion,     'ok' => version_compare($phpVersion, '8.3', '>=')],
                        ['label' => __('Laravel Version'), 'value' => $laravelVersion, 'ok' => true],
                        ['label' => __('Environment'),     'value' => $env,            'ok' => $env === 'production', 'warn' => $env !== 'production'],
                        ['label' => __('Debug Mode'),      'value' => $debugMode ? __('ON') : __('OFF'), 'ok' => !$debugMode, 'warn' => $debugMode],
                        ['label' => __('Timezone'),        'value' => $timezone,       'ok' => true],
                        ['label' => __('Cache Driver'),    'value' => $cacheDriver,    'ok' => true],
                        ['label' => __('Queue Driver'),    'value' => $queueDriver,    'ok' => true],
                        ['label' => __('Session Driver'),  'value' => $sessionDriver,  'ok' => true],
                    ] as $row)
                        <div class="flex items-center justify-between px-4 py-2.5">
                            <span class="text-sm text-zinc-600 dark:text-zinc-400">{{ $row['label'] }}</span>
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $row['value'] }}</span>
                                @if ($row['warn'] ?? false)
                                    <flux:icon.exclamation-triangle class="w-4 h-4 text-amber-500" />
                                @elseif ($row['ok'] ?? true)
                                    <flux:icon.check-circle class="w-4 h-4 text-emerald-500" />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Writable Directories --}}
            <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Writable Directories') }}</h3>
                </div>
                <div class="divide-y divide-zinc-50 dark:divide-zinc-800">
                    @foreach ($paths as $name => $info)
                        <div class="flex items-center justify-between px-4 py-2.5">
                            <span class="font-mono text-xs text-zinc-600 dark:text-zinc-400">{{ $name }}</span>
                            <x-health-badge :status="$info['writable']" />
                        </div>
                    @endforeach
                </div>

                {{-- PHP Extensions --}}
                <div class="border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
                    <h3 class="mb-3 text-sm font-semibold text-zinc-900 dark:text-white">{{ __('PHP Extensions') }}</h3>
                    <div class="grid grid-cols-2 gap-1.5">
                        @foreach ($extensions as $ext => $loaded)
                            <div class="flex items-center gap-2 text-xs">
                                @if ($loaded)
                                    <flux:icon.check-circle class="w-3.5 h-3.5 shrink-0 text-emerald-500" />
                                    <span class="font-mono text-zinc-700 dark:text-zinc-300">{{ $ext }}</span>
                                @else
                                    <flux:icon.x-circle class="w-3.5 h-3.5 shrink-0 text-red-500" />
                                    <span class="font-mono text-red-600 dark:text-red-400 font-medium">{{ $ext }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Checklist ─────────────────────────────────────────────────────── --}}
        <div class="rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
                <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Production Readiness Checklist') }}</h3>
            </div>
            <div class="grid gap-px bg-zinc-100 dark:bg-zinc-800 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['label' => __('Database reachable'),      'ok' => $dbStatus === 'ok',              'msg' => __('DB connection failed')],
                    ['label' => __('Cache working'),           'ok' => $cacheStatus === 'ok',           'msg' => __('Cache read/write failed')],
                    ['label' => __('No failed jobs'),          'ok' => $failedJobs === 0,               'msg' => $failedJobs . __(' failed job(s) in queue')],
                    ['label' => __('Debug mode off (prod)'),   'ok' => !$debugMode || $env !== 'production', 'msg' => __('APP_DEBUG is ON in production')],
                    ['label' => __('Disk space OK'),           'ok' => $diskUsedPercent < 85,           'msg' => $diskUsedPercent . __('% disk used — critically high')],
                    ['label' => __('File usage OK'),           'ok' => $fileUsage === null || $fileUsage['percent'] < 85, 'msg' => ($fileUsage['percent'] ?? 0) . __('% of hosting file limit used — clean up now')],
                    ['label' => __('All paths writable'),      'ok' => collect($paths)->every(fn($p) => $p['writable']), 'msg' => __('Some directories are not writable')],
                ] as $check)
                    <div class="flex items-start gap-3 bg-white p-4 dark:bg-zinc-900">
                        @if ($check['ok'])
                            <flux:icon.check-circle class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" />
                        @else
                            <flux:icon.x-circle class="mt-0.5 h-4 w-4 shrink-0 text-red-500" />
                        @endif
                        <div>
                            <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $check['label'] }}</p>
                            @unless ($check['ok'])
                                <p class="mt-0.5 text-xs text-red-600 dark:text-red-400">{{ $check['msg'] }}</p>
                            @endunless
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-layouts::app>
