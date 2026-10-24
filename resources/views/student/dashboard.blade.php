<x-layouts::app :title="__('Student Dashboard')">
    @php
        $hour = (int) now()->format('G');
        $timeGreeting = match (true) {
            $hour >= 5 && $hour < 12  => __('Good morning'),
            $hour >= 12 && $hour < 17 => __('Good afternoon'),
            default                    => __('Good evening'),
        };

        // Achievement toast data
        $newAchievementKeys = session('new_achievements', []);
        $achievementToasts = [];
        if (! empty($newAchievementKeys)) {
            $defs = \App\Services\AchievementService::definitions();
            foreach ($newAchievementKeys as $key) {
                if (isset($defs[$key])) {
                    $achievementToasts[] = $defs[$key];
                }
            }
        }
    @endphp
    @include('partials.dashboard-styles')

    @include('partials.announcement-banners')

    <div class="space-y-6">
        {{-- ── Welcome Banner ─────────────────────────────────────── --}}
        <div class="dash-welcome dash-welcome-student dash-animate" role="banner">
            <div class="relative z-10">
                <h1 class="text-xl sm:text-2xl font-bold text-white">
                    {{ $timeGreeting }}, {{ $student->name }}
                </h1>
                @if ($currentSession && $currentTerm)
                    <p class="mt-1 text-sm text-white/70">
                        {{ $currentSession->name }} &mdash; {{ $currentTerm->name }}
                    </p>
                @else
                    <p class="mt-1 text-sm text-amber-300">
                        {{ __('No active session or term set — your school is getting things ready.') }}
                    </p>
                @endif
            </div>
        </div>

        {{-- ── Class Info Card ────────────────────────────────────── --}}
        @if ($class)
            <div class="dash-panel dash-animate dash-animate-delay-1" style="padding: 0;">
                <div class="p-4 flex items-center gap-4">
                    <div class="flex items-center justify-center w-12 h-12 rounded-xl bg-blue-100 dark:bg-blue-900/30 shrink-0">
                        <flux:icon.building-library class="w-6 h-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="font-bold text-zinc-900 dark:text-white">{{ $class->name }}</p>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $class->level?->name }}
                            @if ($class->teacher)
                                &mdash; {{ __('Class Teacher: :name', ['name' => $class->teacher->name]) }}
                            @endif
                        </p>
                    </div>
                    @if ($profile?->admission_number)
                        <button
                            type="button"
                            x-data="{ copied: false }"
                            @click="navigator.clipboard.writeText('{{ e($profile->admission_number) }}').then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                            title="{{ __('Click to copy admission number') }}"
                            class="hidden sm:inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-zinc-100 text-zinc-700 dark:bg-zinc-700 dark:text-zinc-300 hover:bg-zinc-200 dark:hover:bg-zinc-600 transition-colors cursor-pointer select-none"
                        >
                            <span x-show="!copied">{{ $profile->admission_number }}</span>
                            <span x-show="copied" x-cloak class="text-emerald-600 dark:text-emerald-400">{{ __('Copied!') }}</span>
                            <svg x-show="!copied" class="w-3 h-3 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                            </svg>
                            <svg x-show="copied" x-cloak class="w-3 h-3 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </button>
                    @endif
                </div>
            </div>
        @else
            <div class="dash-alert dash-alert-amber dash-animate dash-animate-delay-1" role="alert">
                <flux:icon.exclamation-triangle class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0" />
                <p class="text-sm text-amber-700 dark:text-amber-300">
                    {{ __('You have not been assigned to a class yet. Please contact your school admin.') }}
                </p>
            </div>
        @endif

        {{-- ── Stats Cards ────────────────────────────────────────── --}}
        <section aria-label="{{ __('Your statistics') }}">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
                {{-- Row 1: Academics --}}
                <a href="{{ route('student.report-cards.index') }}" wire:navigate class="stat-card dash-animate dash-animate-delay-1 block" style="border-left-color: #0891b2;">
                    <div class="flex items-center gap-2.5">
                        <div class="stat-icon bg-teal-500/15">
                            <flux:icon.document-chart-bar class="w-4 h-4 sm:w-5 sm:h-5 text-teal-600 dark:text-teal-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] sm:text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Report Cards') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($reportCardsCount) }}</p>
                            @if ($cbtResultsCount > 0)
                                <p class="text-[9px] sm:text-[10px] text-zinc-400 dark:text-zinc-500">{{ $cbtResultsCount }} {{ __('CBT result' . ($cbtResultsCount === 1 ? '' : 's')) }}</p>
                            @endif
                        </div>
                    </div>
                </a>

                <a href="{{ route('student.results.index') }}" wire:navigate class="stat-card stat-card-emerald dash-animate dash-animate-delay-1 block">
                    <div class="flex items-center gap-2.5">
                        <div class="stat-icon bg-emerald-500/15">
                            <flux:icon.document-text class="w-4 h-4 sm:w-5 sm:h-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] sm:text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Result Sheets (PDF)') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($resultsCount) }}</p>
                        </div>
                    </div>
                </a>

                <a href="{{ route('student.assignments.index') }}" wire:navigate class="stat-card stat-card-purple dash-animate dash-animate-delay-2 block">
                    <div class="flex items-center gap-2.5">
                        <div class="stat-icon bg-purple-500/15">
                            <flux:icon.clipboard-document-list class="w-4 h-4 sm:w-5 sm:h-5 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] sm:text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Assignments') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($assignmentsCount) }}</p>
                        </div>
                    </div>
                </a>

                {{-- Row 2: Interactive + Communication --}}
                <a href="{{ route('student.exams.index') }}" wire:navigate class="stat-card dash-animate dash-animate-delay-3 block" style="border-left-color: #6366f1;">
                    <div class="flex items-center gap-2.5">
                        <div class="stat-icon bg-indigo-500/15">
                            <flux:icon.computer-desktop class="w-4 h-4 sm:w-5 sm:h-5 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] sm:text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('CBT') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($availableExamsCount + $availableAssessmentsCount + $availableCbtAssignmentsCount) }}</p>
                        </div>
                    </div>
                </a>

                <a href="{{ route('student.quizzes.index') }}" wire:navigate class="stat-card stat-card-amber dash-animate dash-animate-delay-4 block">
                    <div class="flex items-center gap-2.5">
                        <div class="stat-icon bg-amber-500/15">
                            <flux:icon.academic-cap class="w-4 h-4 sm:w-5 sm:h-5 text-amber-600 dark:text-amber-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] sm:text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Quizzes') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($availableQuizzes) }}</p>
                        </div>
                    </div>
                </a>

                <a href="{{ route('student.games.index') }}" wire:navigate class="stat-card stat-card-pink dash-animate dash-animate-delay-5 block">
                    <div class="flex items-center gap-2.5">
                        <div class="stat-icon bg-pink-500/15">
                            <flux:icon.puzzle-piece class="w-4 h-4 sm:w-5 sm:h-5 text-pink-600 dark:text-pink-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] sm:text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Games') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($availableGames) }}</p>
                        </div>
                    </div>
                </a>

                <a href="{{ route('student.notices.index') }}" wire:navigate class="stat-card stat-card-cyan dash-animate dash-animate-delay-5 block">
                    <div class="flex items-center gap-2.5">
                        <div class="stat-icon bg-cyan-500/15">
                            <flux:icon.megaphone class="w-4 h-4 sm:w-5 sm:h-5 text-cyan-600 dark:text-cyan-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] sm:text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Notice Board') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($noticesCount) }}</p>
                        </div>
                    </div>
                </a>
            </div>
        </section>

        {{-- ── Achievements & Streaks ─────────────────────────────── --}}
        <livewire:student.achievements />

        {{-- ── My Performance (Quiz + CBT combined) ──────────────── --}}
        @if ($quizzesTaken > 0 || $examsTaken > 0)
            <div class="dash-panel dash-animate dash-animate-delay-2" style="padding: 0;">
                <div class="dash-panel-header">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('My Performance') }}</h2>
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @if ($quizzesTaken > 0)
                        <div class="flex items-center px-4 py-3 gap-4">
                            <div class="flex items-center gap-1.5 shrink-0 w-20">
                                <flux:icon.academic-cap class="w-4 h-4 text-amber-500" />
                                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Quizzes') }}</span>
                            </div>
                            <div class="flex flex-1 items-center gap-4">
                                <div class="text-center">
                                    <p class="text-base font-bold text-zinc-900 dark:text-white">{{ $quizzesTaken }}</p>
                                    <p class="text-[10px] text-zinc-400">{{ __('taken') }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-base font-bold {{ $quizAvgScore >= 70 ? 'text-emerald-600 dark:text-emerald-400' : ($quizAvgScore >= 50 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">{{ number_format((float) $quizAvgScore, 0) }}%</p>
                                    <p class="text-[10px] text-zinc-400">{{ __('avg') }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-base font-bold {{ $quizPassRate >= 70 ? 'text-emerald-600 dark:text-emerald-400' : ($quizPassRate >= 50 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">{{ $quizPassRate }}%</p>
                                    <p class="text-[10px] text-zinc-400">{{ __('pass rate') }}</p>
                                </div>
                            </div>
                        </div>
                    @endif
                    @if ($examsTaken > 0)
                        <div class="flex items-center px-4 py-3 gap-4">
                            <div class="flex items-center gap-1.5 shrink-0 w-20">
                                <flux:icon.computer-desktop class="w-4 h-4 text-indigo-500" />
                                <span class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('CBT') }}</span>
                            </div>
                            <div class="flex flex-1 items-center gap-4">
                                <div class="text-center">
                                    <p class="text-base font-bold text-zinc-900 dark:text-white">{{ $examsTaken }}</p>
                                    <p class="text-[10px] text-zinc-400">{{ __('taken') }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-base font-bold {{ ($examAvgScore ?? 0) >= 70 ? 'text-emerald-600 dark:text-emerald-400' : (($examAvgScore ?? 0) >= 50 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">{{ number_format((float) ($examAvgScore ?? 0), 0) }}%</p>
                                    <p class="text-[10px] text-zinc-400">{{ __('avg') }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-base font-bold {{ $examPassRate >= 70 ? 'text-emerald-600 dark:text-emerald-400' : ($examPassRate >= 50 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400') }}">{{ $examPassRate }}%</p>
                                    <p class="text-[10px] text-zinc-400">{{ __('pass rate') }}</p>
                                </div>
                                <a href="{{ route('student.exams.index') }}" wire:navigate class="ml-auto text-xs font-medium text-indigo-600 dark:text-indigo-400 hover:underline shrink-0">{{ __('View') }}</a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- ── Academic Progress Timeline ─────────────────────────── --}}
        @if (! empty($timelineData))
            @include('student.partials.progress-timeline', ['timelineData' => $timelineData, 'student' => $student])
        @endif

        {{-- ── What's Next (CBT ongoing/upcoming + assignment deadlines) --}}
        @php
            $ongoingItems    = $cbtItems->where('_status', 'ongoing');
            $upcomingItems   = $cbtItems->where('_status', 'upcoming');
            $hasWhatsNext    = $ongoingItems->isNotEmpty() || $upcomingItems->isNotEmpty() || $upcomingDeadlines->isNotEmpty();
        @endphp
        @if ($hasWhatsNext)
            <div class="dash-panel dash-animate dash-animate-delay-3" style="padding: 0;">
                <div class="dash-panel-header">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white flex items-center gap-2">
                        <flux:icon.clock class="w-4 h-4 text-indigo-500" />
                        {{ __("What's Next") }}
                    </h2>
                    <a href="{{ route('student.exams.index') }}" wire:navigate class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">{{ __('All CBT') }}</a>
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">

                    {{-- Ongoing CBT items --}}
                    @foreach ($ongoingItems as $cbt)
                        @php
                            $daysLeft = $cbt->available_until ? (int) now()->diffInDays($cbt->available_until, false) : null;
                            $urgent   = $daysLeft !== null && $daysLeft <= 1;
                            $catIcon  = match ($cbt->category) { 'exam' => 'computer-desktop', 'assessment' => 'clipboard-document-check', default => 'clipboard-document-list' };
                            $catLabel = match ($cbt->category) { 'exam' => __('Exam'), 'assessment' => __('Assessment'), default => __('Assignment') };
                        @endphp
                        <a href="{{ route('student.exams.show', $cbt) }}" wire:navigate
                           class="activity-item hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors">
                            <div class="activity-dot {{ $cbt->_taken ? 'bg-emerald-100 dark:bg-emerald-900/30' : ($urgent ? 'bg-red-100 dark:bg-red-900/30' : 'bg-green-100 dark:bg-green-900/30') }}">
                                <flux:icon :name="$catIcon" class="w-4 h-4 {{ $cbt->_taken ? 'text-emerald-600 dark:text-emerald-400' : ($urgent ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400') }}" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white truncate">{{ $cbt->title }}</p>
                                    <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 shrink-0">
                                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>
                                        {{ __('Ongoing') }}
                                    </span>
                                    @if ($cbt->_taken)
                                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 shrink-0">
                                            <flux:icon.check class="w-3 h-3" /> {{ __('Taken') }}
                                        </span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                    <span class="text-xs text-zinc-400 capitalize">{{ $catLabel }}</span>
                                    @if ($cbt->subject)
                                        <span class="text-xs text-zinc-400">&middot;</span>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $cbt->subject->name }}</span>
                                    @endif
                                    @if ($cbt->total_questions)
                                        <span class="text-xs text-zinc-400">&middot; {{ $cbt->total_questions }} {{ __('q') }}</span>
                                    @endif
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                @if ($cbt->available_until)
                                    <p class="text-sm font-semibold {{ $urgent ? 'text-red-600 dark:text-red-400' : 'text-zinc-700 dark:text-zinc-300' }}">
                                        {{ $cbt->available_until->format('M j') }}
                                    </p>
                                    <p class="text-xs {{ $urgent ? 'text-red-500' : 'text-zinc-400' }}">
                                        @if ($daysLeft === 0) {{ __('Today') }}
                                        @elseif ($daysLeft === 1) {{ __('Tomorrow') }}
                                        @else {{ __(':d days left', ['d' => $daysLeft]) }}
                                        @endif
                                    </p>
                                @endif
                            </div>
                        </a>
                    @endforeach

                    {{-- Upcoming CBT items (not yet open) --}}
                    @foreach ($upcomingItems as $cbt)
                        @php
                            $opensIn   = (int) now()->diffInDays($cbt->available_from, false);
                            $catLabel  = match ($cbt->category) { 'exam' => __('Exam'), 'assessment' => __('Assessment'), default => __('Assignment') };
                            $catIcon   = match ($cbt->category) { 'exam' => 'computer-desktop', 'assessment' => 'clipboard-document-check', default => 'clipboard-document-list' };
                        @endphp
                        <div class="activity-item opacity-75">
                            <div class="activity-dot bg-amber-100 dark:bg-amber-900/30">
                                <flux:icon :name="$catIcon" class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white truncate">{{ $cbt->title }}</p>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 shrink-0">{{ __('Upcoming') }}</span>
                                </div>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                    {{ $catLabel }}
                                    @if ($cbt->subject) &middot; {{ $cbt->subject->name }} @endif
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-semibold text-zinc-700 dark:text-zinc-300">{{ $cbt->available_from->format('M j') }}</p>
                                <p class="text-xs text-zinc-400">
                                    {{ $cbt->available_from->format('g:i A') }}
                                </p>
                            </div>
                        </div>
                    @endforeach

                    {{-- Assignment deadlines --}}
                    @foreach ($upcomingDeadlines as $deadline)
                        @php
                            $daysLeft = (int) now()->diffInDays($deadline->due_date, false);
                            $urgent   = $daysLeft <= 2;
                        @endphp
                        <div class="activity-item">
                            <div class="activity-dot {{ $urgent ? 'bg-red-100 dark:bg-red-900/30' : 'bg-purple-100 dark:bg-purple-900/30' }}">
                                <flux:icon.calendar-days class="w-4 h-4 {{ $urgent ? 'text-red-600 dark:text-red-400' : 'text-purple-600 dark:text-purple-400' }}" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-zinc-900 dark:text-white truncate">
                                    {{ $deadline->title ?? __('Week :week Assignment', ['week' => $deadline->week_number]) }}
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Assignment due') }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-semibold {{ $urgent ? 'text-red-600 dark:text-red-400' : 'text-zinc-700 dark:text-zinc-300' }}">
                                    {{ $deadline->due_date->format('M j') }}
                                </p>
                                <p class="text-xs {{ $urgent ? 'text-red-500' : 'text-zinc-400' }}">
                                    @if ($daysLeft == 0) {{ __('Today') }}
                                    @elseif ($daysLeft == 1) {{ __('Tomorrow') }}
                                    @else {{ __(':d days', ['d' => $daysLeft]) }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    @endforeach

                </div>
            </div>
        @endif

        {{-- ── CBT/Study Calendar ─────────────────────────────────── --}}
        @if ($profile?->class_id)
            @include('student.partials.study-calendar', ['calendarEvents' => $calendarEvents])
        @endif

        {{-- ── My Learning (unified quizzes + games) ─────────────── --}}
        @if ($learningItems->isNotEmpty() || $totalPublishedQuizzes > 0 || $totalPublishedGames > 0)
            <div class="dash-panel dash-animate dash-animate-delay-3" style="padding: 0;">
                <div class="dash-panel-header">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white flex items-center gap-2">
                        <flux:icon.light-bulb class="w-4 h-4 text-amber-500" />
                        {{ __('My Learning') }}
                    </h2>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('student.quizzes.index') }}" wire:navigate class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">{{ __('Quizzes') }}</a>
                        <span class="text-zinc-300 dark:text-zinc-600">|</span>
                        <a href="{{ route('student.games.index') }}" wire:navigate class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">{{ __('Games') }}</a>
                    </div>
                </div>

                {{-- Progress rings --}}
                @if ($totalPublishedQuizzes > 0 || $totalPublishedGames > 0)
                    @php
                        $quizPct = $totalPublishedQuizzes > 0 ? round(($quizzesCompletedCount / $totalPublishedQuizzes) * 100) : 0;
                        $gamePct = $totalPublishedGames > 0 ? round(($gamesCompletedCount / $totalPublishedGames) * 100) : 0;
                        $quizDash = round(100.53 * $quizPct / 100, 2);  // circumference for r=16 ≈ 100.53
                        $gameDash = round(100.53 * $gamePct / 100, 2);
                    @endphp
                    <div class="flex items-center justify-center gap-6 sm:gap-10 px-4 py-4 border-b border-zinc-100 dark:border-zinc-700/50">
                        {{-- Quiz ring --}}
                        <div class="flex items-center gap-3">
                            <div class="relative w-14 h-14 sm:w-16 sm:h-16 shrink-0">
                                <svg viewBox="0 0 36 36" class="w-full h-full -rotate-90" aria-hidden="true">
                                    <circle cx="18" cy="18" r="16" fill="none" stroke-width="3" class="stroke-zinc-200 dark:stroke-zinc-700" />
                                    <circle cx="18" cy="18" r="16" fill="none" stroke-width="3" stroke-linecap="round"
                                        class="stroke-blue-500 dark:stroke-blue-400 transition-all duration-700"
                                        stroke-dasharray="{{ $quizDash }} {{ 100.53 - $quizDash }}" />
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-xs sm:text-sm font-bold text-zinc-900 dark:text-white">{{ $quizPct }}%</span>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $quizzesCompletedCount }}/{{ $totalPublishedQuizzes }}</p>
                                <p class="text-[10px] sm:text-xs text-zinc-500 dark:text-zinc-400">{{ __('Quizzes done') }}</p>
                            </div>
                        </div>

                        {{-- Divider --}}
                        <div class="w-px h-10 bg-zinc-200 dark:bg-zinc-700 hidden sm:block"></div>

                        {{-- Game ring --}}
                        <div class="flex items-center gap-3">
                            <div class="relative w-14 h-14 sm:w-16 sm:h-16 shrink-0">
                                <svg viewBox="0 0 36 36" class="w-full h-full -rotate-90" aria-hidden="true">
                                    <circle cx="18" cy="18" r="16" fill="none" stroke-width="3" class="stroke-zinc-200 dark:stroke-zinc-700" />
                                    <circle cx="18" cy="18" r="16" fill="none" stroke-width="3" stroke-linecap="round"
                                        class="stroke-pink-500 dark:stroke-pink-400 transition-all duration-700"
                                        stroke-dasharray="{{ $gameDash }} {{ 100.53 - $gameDash }}" />
                                </svg>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="text-xs sm:text-sm font-bold text-zinc-900 dark:text-white">{{ $gamePct }}%</span>
                                </div>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $gamesCompletedCount }}/{{ $totalPublishedGames }}</p>
                                <p class="text-[10px] sm:text-xs text-zinc-500 dark:text-zinc-400">{{ __('Games played') }}</p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Unified learning items list --}}
                @if ($learningItems->isNotEmpty())
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                        @foreach ($learningItems as $item)
                            <div class="p-4 flex items-center gap-3 {{ $item->completed ? 'opacity-60' : '' }}">
                                {{-- Type icon --}}
                                <div class="flex items-center justify-center w-9 h-9 rounded-lg shrink-0
                                    {{ $item->type === 'quiz' ? 'bg-blue-100 dark:bg-blue-900/30' : 'bg-pink-100 dark:bg-pink-900/30' }}">
                                    @if ($item->type === 'quiz')
                                        <flux:icon.academic-cap class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                    @else
                                        <flux:icon.puzzle-piece class="w-4 h-4 text-pink-600 dark:text-pink-400" />
                                    @endif
                                </div>

                                {{-- Content --}}
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-1.5">
                                        <p class="font-medium text-sm text-zinc-900 dark:text-white truncate">{{ $item->title }}</p>
                                        @if ($item->completed)
                                            <flux:icon.check-circle class="w-4 h-4 text-emerald-500 shrink-0" />
                                        @endif
                                    </div>
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5 mt-0.5">
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider
                                            {{ $item->type === 'quiz' ? 'bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400' : 'bg-pink-50 text-pink-600 dark:bg-pink-900/20 dark:text-pink-400' }}">
                                            {{ $item->type === 'quiz' ? __('Quiz') : __('Game') }}
                                        </span>
                                        <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $item->meta }}</span>
                                        @if (isset($item->time_limit) && $item->time_limit)
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">&middot; {{ $item->time_limit }} {{ __('min') }}</span>
                                        @endif
                                        @if (isset($item->difficulty))
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400 capitalize">&middot; {{ $item->difficulty }}</span>
                                        @endif
                                        @if (isset($item->attempts_label))
                                            <span class="text-xs text-zinc-500 dark:text-zinc-400">&middot; {{ $item->attempts_label }} {{ __('attempts') }}</span>
                                        @endif
                                        @if ($item->expires_at)
                                            <span class="text-xs text-amber-600 dark:text-amber-400">&middot; {{ __('Due :date', ['date' => $item->expires_at->format('M j')]) }}</span>
                                        @endif
                                    </div>
                                </div>

                                {{-- Action button --}}
                                <a href="{{ $item->route }}" wire:navigate
                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors shrink-0
                                        {{ $item->completed
                                            ? 'text-zinc-500 bg-zinc-100 hover:bg-zinc-200 dark:text-zinc-400 dark:bg-zinc-700 dark:hover:bg-zinc-600'
                                            : ($item->type === 'quiz'
                                                ? 'text-white bg-blue-600 hover:bg-blue-700'
                                                : 'text-white bg-pink-600 hover:bg-pink-700') }}">
                                    {{ $item->btn_label }}
                                    <flux:icon.arrow-right class="w-3 h-3" />
                                </a>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-8 text-center">
                        <div class="w-12 h-12 mx-auto rounded-full bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center mb-3">
                            <flux:icon.light-bulb class="w-6 h-6 text-zinc-400" />
                        </div>
                        <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No quizzes or games yet — your teacher will post them here when ready.') }}</p>
                    </div>
                @endif
            </div>
        @endif

        {{-- ── Recent Results + Recent Assignments ────────────────── --}}
        <div class="grid gap-4 sm:gap-6 lg:grid-cols-2">
            <div class="dash-panel dash-animate dash-animate-delay-4" style="padding: 0;">
                <div class="dash-panel-header">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Recent Results') }}</h2>
                    @if ($resultsCount > 0)
                        <a href="{{ route('student.results.index') }}" wire:navigate class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">{{ __('View all') }}</a>
                    @endif
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @forelse ($recentResults as $result)
                        <a href="{{ route('student.results.show', $result) }}" wire:navigate class="activity-item hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors">
                            <div class="activity-dot bg-emerald-100 dark:bg-emerald-900/30">
                                <flux:icon.document-text class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $result->session?->name }} &mdash; {{ $result->term?->name }}
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $result->created_at->format('M j, Y') }}</p>
                            </div>
                            <flux:icon.chevron-right class="w-4 h-4 text-zinc-400 shrink-0" />
                        </a>
                    @empty
                        <div class="p-8 text-center">
                            <div class="w-12 h-12 mx-auto rounded-full bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center mb-3">
                                <flux:icon.document-text class="w-6 h-6 text-zinc-400" />
                            </div>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Results will appear here once uploaded by your school.') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="dash-panel dash-animate dash-animate-delay-5" style="padding: 0;">
                <div class="dash-panel-header">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Recent Assignments') }}</h2>
                    @if ($assignmentsCount > 0)
                        <a href="{{ route('student.assignments.index') }}" wire:navigate class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">{{ __('View all') }}</a>
                    @endif
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @forelse ($recentAssignments as $assignment)
                        <div class="activity-item">
                            <div class="activity-dot bg-purple-100 dark:bg-purple-900/30">
                                <flux:icon.clipboard-document-list class="w-4 h-4 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ $assignment->title ?? __('Week :week Assignment', ['week' => $assignment->week_number]) }}
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Week :week', ['week' => $assignment->week_number]) }}
                                    @if ($assignment->due_date)
                                        &mdash; {{ __('Due: :date', ['date' => $assignment->due_date->format('M j, Y')]) }}
                                    @endif
                                </p>
                            </div>
                            @if ($assignment->file_url)
                                <a href="{{ $assignment->file_url }}" target="_blank" rel="noopener noreferrer" class="text-blue-600 dark:text-blue-400 hover:text-blue-700 shrink-0">
                                    <flux:icon.arrow-down-tray class="w-4 h-4" />
                                    <span class="sr-only">{{ __('Download') }}</span>
                                </a>
                            @endif
                        </div>
                    @empty
                        <div class="p-8 text-center">
                            <div class="w-12 h-12 mx-auto rounded-full bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center mb-3">
                                <flux:icon.clipboard-document-list class="w-6 h-6 text-zinc-400" />
                            </div>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Your teacher hasn\'t posted any assignments yet — check back soon!') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ── Latest Notices ─────────────────────────────────────── --}}
        @if ($recentNotices->isNotEmpty())
            <div class="dash-panel dash-animate dash-animate-delay-5" style="padding: 0;">
                <div class="dash-panel-header">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Latest Notices') }}</h2>
                    <a href="{{ route('student.notices.index') }}" wire:navigate class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">{{ __('View all') }}</a>
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @foreach ($recentNotices as $notice)
                        <a href="{{ route('student.notices.show', $notice) }}" wire:navigate class="flex items-start gap-3 p-4 hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors">
                            @if ($notice->image_url)
                                <img src="{{ $notice->imageThumbnailUrl() }}" alt="" class="w-12 h-12 rounded-lg object-cover shrink-0" loading="lazy" />
                            @else
                                <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-cyan-100 dark:bg-cyan-900/30 shrink-0">
                                    <flux:icon.megaphone class="w-5 h-5 text-cyan-600 dark:text-cyan-400" />
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-sm text-zinc-900 dark:text-white">{{ $notice->title }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $notice->published_at->format('M j, Y') }}</p>
                            </div>
                            <flux:icon.chevron-right class="w-4 h-4 text-zinc-400 mt-1 shrink-0" />
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Achievement Toast Notification --}}
    @if (! empty($achievementToasts))
        <div x-data="{ toasts: {{ Js::from($achievementToasts) }}, current: 0, show: true }"
             x-init="setTimeout(() => show = false, 5000)"
             x-show="show"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="translate-y-full opacity-0"
             x-transition:enter-end="translate-y-0 opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="translate-y-0 opacity-100"
             x-transition:leave-end="translate-y-full opacity-0"
             class="fixed bottom-6 right-6 z-50 max-w-sm"
             role="alert"
             aria-live="assertive"
        >
            <template x-for="(toast, index) in toasts" :key="index">
                <div class="mb-2 flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg bg-white dark:bg-zinc-800 border border-amber-200 dark:border-amber-700/50 ring-1 ring-amber-100 dark:ring-amber-900/30">
                    <span class="text-2xl" x-text="toast.icon"></span>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-zinc-900 dark:text-white">🎉 {{ __('Achievement Unlocked!') }}</p>
                        <p class="text-xs font-semibold text-amber-700 dark:text-amber-400" x-text="toast.name"></p>
                    </div>
                    <button @click="show = false" class="ml-auto text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 shrink-0" aria-label="{{ __('Close') }}">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </template>
        </div>
    @endif
</x-layouts::app>
