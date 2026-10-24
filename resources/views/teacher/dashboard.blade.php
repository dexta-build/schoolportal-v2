<x-layouts::app :title="__('Teacher Dashboard')">
    @php
        $hour = (int) now()->format('G');
        $timeGreeting = match (true) {
            $hour >= 5 && $hour < 12  => __('Good morning'),
            $hour >= 12 && $hour < 17 => __('Good afternoon'),
            default                    => __('Good evening'),
        };
    @endphp
    @include('partials.dashboard-styles')

    @include('partials.announcement-banners')

    <div class="space-y-6">
        {{-- ── Welcome Banner ─────────────────────────────────────── --}}
        <div class="dash-welcome dash-welcome-teacher dash-animate" role="banner">
            <div class="relative z-10">
                <h1 class="text-xl sm:text-2xl font-bold text-white">
                    {{ $timeGreeting }}, Teacher {{ $teacher->name }}
                </h1>
                @if ($currentSession && $currentTerm)
                    <p class="mt-1 text-sm text-white/70">
                        {{ $currentSession->name }} &mdash; {{ $currentTerm->name }}
                    </p>
                @else
                    <p class="mt-1 text-sm text-amber-300">
                        {{ __('No active session/term set by the school admin.') }}
                    </p>
                @endif
            </div>
        </div>

        {{-- ── Session/Term Filter ─────────────────────────────────── --}}
        @include('partials.session-term-filter', [
            'route' => 'teacher.dashboard',
            'allSessions' => $allSessions,
            'sessionTerms' => $sessionTerms,
            'currentSession' => $currentSession,
            'currentTerm' => $currentTerm,
        ])

        {{-- ── Rejected Submissions Alert ─────────────────────────── --}}
        @if ($rejectedSubmissions->isNotEmpty())
            <div class="dash-alert dash-alert-red dash-animate dash-animate-delay-1" role="alert" aria-label="{{ __('Rejected submissions alert') }}">
                <flux:icon.x-circle class="w-5 h-5 mt-0.5 shrink-0 text-red-600 dark:text-red-400" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-red-800 dark:text-red-200">
                        {{ trans_choice(':count submission was rejected|:count submissions were rejected', $rejectedSubmissions->count(), ['count' => $rejectedSubmissions->count()]) }}
                    </p>
                    <ul class="mt-1 space-y-0.5">
                        @foreach ($rejectedSubmissions as $rej)
                            <li class="text-xs text-red-700 dark:text-red-300">
                                <span class="capitalize">{{ str_replace('_', ' ', $rej->action_type) }}</span>
                                @if ($rej->rejection_reason)
                                    &mdash; "{{ Str::limit($rej->rejection_reason, 80) }}"
                                @endif
                                <span class="text-red-500 dark:text-red-400">({{ $rej->created_at->diffForHumans() }})</span>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('teacher.submissions.index') }}" wire:navigate class="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold text-red-800 dark:text-red-200 hover:underline">
                        {{ __('View submissions') }}
                        <flux:icon.arrow-right class="w-3 h-3" />
                    </a>
                </div>
            </div>
        @endif

        {{-- ── Primary Stats ──────────────────────────────────────── --}}
        <section aria-label="{{ __('Teaching statistics') }}">
            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <div class="stat-card stat-card-blue dash-animate dash-animate-delay-1" data-stat-loading
                     x-data x-init="setTimeout(() => $el.removeAttribute('data-stat-loading'), 700)">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon bg-blue-500/15">
                            <flux:icon.academic-cap class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('My Students') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($totalStudents) }}</p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ trans_choice(':count class|:count classes', $assignedClasses->count(), ['count' => $assignedClasses->count()]) }}
                    </p>
                </div>

                <div class="stat-card stat-card-emerald dash-animate dash-animate-delay-2" data-stat-loading
                     x-data x-init="setTimeout(() => $el.removeAttribute('data-stat-loading'), 850)">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon bg-emerald-500/15">
                            <flux:icon.document-text class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Result Sheets (PDF)') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($totalResults) }}</p>
                        </div>
                    </div>
                </div>

                <div class="stat-card stat-card-purple dash-animate dash-animate-delay-3" data-stat-loading
                     x-data x-init="setTimeout(() => $el.removeAttribute('data-stat-loading'), 1000)">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon bg-purple-500/15">
                            <flux:icon.clipboard-document-list class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Assignments') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($totalAssignments) }}</p>
                        </div>
                    </div>
                </div>

                <div class="stat-card stat-card-amber dash-animate dash-animate-delay-4" data-stat-loading
                     x-data x-init="setTimeout(() => $el.removeAttribute('data-stat-loading'), 1150)">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon bg-amber-500/15">
                            <flux:icon.clock class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Pending') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($pendingCount) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- ── Quiz/Game Stats + AI Credits ───────────────────────── --}}
        <section aria-label="{{ __('Quiz, game and AI credit stats') }}">
            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <div class="stat-card stat-card-cyan dash-animate dash-animate-delay-2">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon bg-cyan-500/15">
                            <flux:icon.academic-cap class="w-5 h-5 text-cyan-600 dark:text-cyan-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Quizzes') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($publishedQuizzes) }}</p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __(':count attempts', ['count' => number_format($quizAttempts)]) }}</p>
                </div>

                <div class="stat-card stat-card-pink dash-animate dash-animate-delay-3">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon bg-pink-500/15">
                            <flux:icon.puzzle-piece class="w-5 h-5 text-pink-600 dark:text-pink-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Games') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($publishedGames) }}</p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Published') }}</p>
                </div>

                <div class="stat-card stat-card-teal dash-animate dash-animate-delay-4">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon bg-teal-500/15">
                            <flux:icon.chart-bar class="w-5 h-5 text-teal-600 dark:text-teal-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Avg Score') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ $avgQuizScore !== null ? number_format((float) $avgQuizScore, 0) . '%' : '—' }}</p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Quiz average') }}</p>
                </div>
            </div>
        </section>

        {{-- ── CBT Stats + AI Credits ────────────────────────────── --}}
        <section aria-label="{{ __('CBT statistics') }}">
            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
                <a href="{{ route('teacher.exams.index') }}" wire:navigate class="stat-card dash-animate dash-animate-delay-3 block" style="border-left-color: #6366f1;">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon bg-indigo-500/15">
                            <flux:icon.computer-desktop class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('CBT') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($publishedCbtExams + $publishedAssessments + $publishedCbtAssignments) }}</p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Published') }}</p>
                </a>

                <div class="stat-card dash-animate dash-animate-delay-5" style="border-left-color: #14b8a6;">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon bg-teal-500/15">
                            <flux:icon.chart-bar class="w-5 h-5 text-teal-600 dark:text-teal-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('CBT Avg Score') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ $avgCbtScore !== null ? number_format((float) $avgCbtScore, 0) . '%' : '—' }}</p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __(':count attempts', ['count' => number_format($cbtAttempts)]) }}</p>
                </div>

                <div class="stat-card dash-animate dash-animate-delay-5" style="border-left-color: #6366f1;">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon bg-indigo-500/15">
                            <flux:icon.sparkles class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('AI Credits') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($aiCreditsRemaining) }}</p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ $aiCreditsLabel }}</p>
                </div>
            </div>
        </section>

        {{-- ── Results Progress + Assignments Coverage ────────────── --}}
        @if ($resultsProgress->isNotEmpty() || $assignmentsCoverage->isNotEmpty())
            <div class="grid gap-4 sm:gap-6 lg:grid-cols-2">
                @if ($resultsProgress->isNotEmpty())
                    <div class="dash-panel dash-animate dash-animate-delay-3">
                        <div class="dash-panel-header">
                            <div>
                                <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Results Upload Progress') }}</h2>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __('Approved results per class this term') }}</p>
                            </div>
                        </div>
                        <div class="dash-panel-body space-y-3">
                            @foreach ($resultsProgress as $rp)
                                @php $rpPercent = $rp['total'] > 0 ? round(($rp['uploaded'] / $rp['total']) * 100) : 0; @endphp
                                <div>
                                    <div class="flex items-center justify-between text-sm mb-1">
                                        <span class="text-zinc-700 dark:text-zinc-300 font-medium truncate">{{ $rp['name'] }}</span>
                                        <span class="font-bold text-zinc-900 dark:text-white shrink-0 ml-2">{{ $rp['uploaded'] }}/{{ $rp['total'] }}</span>
                                    </div>
                                    <div class="progress-track bg-zinc-200 dark:bg-zinc-700" role="progressbar" aria-valuenow="{{ $rpPercent }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $rp['name'] }} {{ __('results progress') }}">
                                        <div class="progress-fill {{ $rpPercent >= 80 ? 'bg-emerald-500' : ($rpPercent >= 50 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ $rpPercent }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($assignmentsCoverage->isNotEmpty())
                    <div class="dash-panel dash-animate dash-animate-delay-4">
                        <div class="dash-panel-header">
                            <div>
                                <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Assignments Coverage') }}</h2>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __(':total weeks per term', ['total' => $weeksPerTerm]) }}</p>
                            </div>
                        </div>
                        <div class="dash-panel-body space-y-3">
                            @foreach ($assignmentsCoverage as $ac)
                                @php $acPercent = $ac['total'] > 0 ? round(($ac['uploaded'] / $ac['total']) * 100) : 0; @endphp
                                <div>
                                    <div class="flex items-center justify-between text-sm mb-1">
                                        <span class="text-zinc-700 dark:text-zinc-300 font-medium truncate">{{ $ac['name'] }}</span>
                                        <span class="font-bold text-zinc-900 dark:text-white shrink-0 ml-2">{{ $ac['uploaded'] }}/{{ $ac['total'] }}</span>
                                    </div>
                                    <div class="progress-track bg-zinc-200 dark:bg-zinc-700" role="progressbar" aria-valuenow="{{ $acPercent }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $ac['name'] }} {{ __('assignment coverage') }}">
                                        <div class="progress-fill {{ $acPercent >= 80 ? 'bg-emerald-500' : ($acPercent >= 50 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ $acPercent }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- ── Student Performance Insights (lazy-loaded) ─────────── --}}
        <div class="dash-animate dash-animate-delay-4">
            <livewire:teacher.student-insights
                :session-id="$currentSession?->id"
                :term-id="$currentTerm?->id"
                :compact="true"
                lazy />
        </div>

        {{-- ── Upcoming Deadlines + My Classes ────────────────────── --}}
        <div class="grid gap-4 sm:gap-6 lg:grid-cols-2">
            <div class="dash-panel dash-animate dash-animate-delay-4">
                <div class="dash-panel-header">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Upcoming Deadlines') }}</h2>
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @forelse ($upcomingDeadlines as $deadline)
                        @php
                            $daysLeft = now()->diffInDays($deadline->due_date, false);
                            $urgent = $daysLeft <= 2;
                        @endphp
                        <div class="activity-item">
                            <div class="activity-dot {{ $urgent ? 'bg-red-100 dark:bg-red-900/30' : 'bg-blue-100 dark:bg-blue-900/30' }}">
                                <flux:icon.calendar class="w-4 h-4 {{ $urgent ? 'text-red-600 dark:text-red-400' : 'text-blue-600 dark:text-blue-400' }}" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-zinc-900 dark:text-white truncate">
                                    {{ $deadline->title ?? __('Week :week', ['week' => $deadline->week_number]) }}
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $deadline->class?->name }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-semibold {{ $urgent ? 'text-red-600 dark:text-red-400' : 'text-zinc-700 dark:text-zinc-300' }}">
                                    {{ $deadline->due_date->format('M j') }}
                                </p>
                                <p class="text-xs {{ $urgent ? 'text-red-500 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                    @if ($daysLeft == 0) {{ __('Today') }}
                                    @elseif ($daysLeft == 1) {{ __('Tomorrow') }}
                                    @else {{ __(':days days', ['days' => $daysLeft]) }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center">
                            <div class="w-12 h-12 mx-auto rounded-full bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center mb-3">
                                <flux:icon.calendar class="w-6 h-6 text-zinc-400" />
                            </div>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('All clear — no upcoming assignment deadlines for your classes.') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="dash-panel dash-animate dash-animate-delay-5">
                <div class="dash-panel-header">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('My Classes') }}</h2>
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @forelse ($assignedClasses as $class)
                        <div class="activity-item">
                            <div class="activity-dot bg-blue-100 dark:bg-blue-900/30">
                                <flux:icon.building-library class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $class->name }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $class->level?->name }}</p>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300">
                                    {{ trans_choice(':count student|:count students', $class->students_count, ['count' => $class->students_count]) }}
                                </span>
                                <a href="{{ route('teacher.students.index', ['class_id' => $class->id]) }}" wire:navigate class="text-blue-600 dark:text-blue-400 hover:text-blue-700">
                                    <flux:icon.eye class="w-4 h-4" />
                                    <span class="sr-only">{{ __('View students') }}</span>
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center">
                            <div class="w-12 h-12 mx-auto rounded-full bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center mb-3">
                                <flux:icon.building-library class="w-6 h-6 text-zinc-400" />
                            </div>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No classes assigned yet. Ask your school admin to assign you.') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ── Recent Submissions ─────────────────────────────────── --}}
        <div x-data="{ open: window.innerWidth >= 768 }" x-init="window.addEventListener('resize', () => { if (window.innerWidth >= 768) open = true })" class="dash-animate dash-animate-delay-5">
            <button @click="open = !open" class="flex items-center justify-between w-full text-left md:hidden mb-3">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Recent Submissions') }}</h2>
                    @if ($pendingCount > 0)
                        <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-amber-500 rounded-full">{{ $pendingCount }}</span>
                    @endif
                </div>
                <flux:icon.chevron-down class="w-4 h-4 text-zinc-500 transition-transform" ::class="open ? 'rotate-180' : ''" />
            </button>
            <div x-show="open" x-collapse>
        <div class="dash-panel">
            <div class="dash-panel-header hidden md:flex">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Recent Submissions') }}</h2>
                    @if ($pendingCount > 0)
                        <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-amber-500 rounded-full badge-pulse">{{ $pendingCount }}</span>
                    @endif
                </div>
                <a href="{{ route('teacher.submissions.index') }}" wire:navigate class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">{{ __('View all') }}</a>
            </div>
            <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                @forelse ($recentSubmissions as $submission)
                    <div class="activity-item">
                        <div class="activity-dot
                            @if ($submission->status === 'approved') bg-emerald-100 dark:bg-emerald-900/30
                            @elseif ($submission->status === 'pending') bg-amber-100 dark:bg-amber-900/30
                            @else bg-red-100 dark:bg-red-900/30 @endif">
                            @if ($submission->status === 'approved')
                                <flux:icon.check class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                            @elseif ($submission->status === 'pending')
                                <flux:icon.clock class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                            @else
                                <flux:icon.x-mark class="w-4 h-4 text-red-600 dark:text-red-400" />
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-zinc-700 dark:text-zinc-300">
                                <span class="capitalize font-medium text-zinc-900 dark:text-white">{{ str_replace('_', ' ', $submission->action_type) }}</span>
                                &mdash;
                                @if ($submission->status === 'approved')
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">{{ __('Approved') }}</span>
                                @elseif ($submission->status === 'pending')
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">{{ __('Pending') }}</span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">{{ __('Rejected') }}</span>
                                @endif
                            </p>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $submission->created_at->diffForHumans() }}
                                @if ($submission->status === 'rejected' && $submission->rejection_reason)
                                    &mdash; {{ Str::limit($submission->rejection_reason, 60) }}
                                @endif
                            </p>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center">
                        <div class="w-12 h-12 mx-auto rounded-full bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center mb-3">
                            <flux:icon.inbox class="w-6 h-6 text-zinc-400" />
                        </div>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No submissions yet. Upload a result, assignment, or post a notice to get started.') }}</p>
                    </div>
                @endforelse
            </div>
        </div>
            </div>
        </div>

        {{-- ── Quick Actions ──────────────────────────────────────── --}}
        @if ($assignedClasses->isNotEmpty())
            <section aria-labelledby="teacher-quick-actions" class="dash-animate dash-animate-delay-6">
                <h2 id="teacher-quick-actions" class="text-sm font-semibold text-zinc-900 dark:text-white mb-3">{{ __('Quick Actions') }}</h2>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-5">
                    <a href="{{ route('teacher.results.create') }}" wire:navigate class="quick-action">
                        <div class="quick-action-icon bg-emerald-100 dark:bg-emerald-900/30">
                            <flux:icon.arrow-up-tray class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300 text-center">{{ __('Result') }}</span>
                    </a>
                    <a href="{{ route('teacher.assignments.create') }}" wire:navigate class="quick-action">
                        <div class="quick-action-icon bg-purple-100 dark:bg-purple-900/30">
                            <flux:icon.clipboard-document-list class="w-4 h-4 text-purple-600 dark:text-purple-400" />
                        </div>
                        <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300 text-center">{{ __('Assignment') }}</span>
                    </a>
                    <a href="{{ route('teacher.notices.create') }}" wire:navigate class="quick-action">
                        <div class="quick-action-icon bg-pink-100 dark:bg-pink-900/30">
                            <flux:icon.megaphone class="w-4 h-4 text-pink-600 dark:text-pink-400" />
                        </div>
                        <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300 text-center">{{ __('Notice') }}</span>
                    </a>
                    <a href="{{ route('teacher.quizzes.create') }}" wire:navigate class="quick-action">
                        <div class="quick-action-icon bg-cyan-100 dark:bg-cyan-900/30">
                            <flux:icon.academic-cap class="w-4 h-4 text-cyan-600 dark:text-cyan-400" />
                        </div>
                        <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300 text-center">{{ __('Quiz') }}</span>
                    </a>
                    <a href="{{ route('teacher.games.create') }}" wire:navigate class="quick-action">
                        <div class="quick-action-icon bg-amber-100 dark:bg-amber-900/30">
                            <flux:icon.puzzle-piece class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                        </div>
                        <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300 text-center">{{ __('Game') }}</span>
                    </a>
                </div>
            </section>
        @endif
    </div>
</x-layouts::app>
