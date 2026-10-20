<x-layouts::app :title="__('CBT')">
    <div class="space-y-6">
        <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('CBT') }}</h1>

        @if (session('error'))
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 dark:border-red-800/60 dark:bg-red-950/60 dark:text-red-100">
                {{ session('error') }}
            </div>
        @endif

        @if (session('success'))
            <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/60 dark:text-emerald-100">
                {{ session('success') }}
            </div>
        @endif

        {{-- Category Tabs --}}
        @php
            $currentCategory = request('category');
            $tabs = [
                null => __('All'),
                'exam' => __('Exams'),
                'assessment' => __('Assessments'),
                'assignment' => __('Assignments'),
            ];
        @endphp
        <div class="flex gap-1 overflow-x-auto border-b border-zinc-200 dark:border-zinc-700" role="tablist" aria-label="{{ __('Category filter') }}">
            @foreach ($tabs as $tabValue => $tabLabel)
                @php
                    $isActive = $currentCategory === ($tabValue ?: null);
                    $tabUrl = route('student.exams.index', $tabValue ? ['category' => $tabValue] : []);
                @endphp
                <a href="{{ $tabUrl }}"
                   role="tab"
                   aria-selected="{{ $isActive ? 'true' : 'false' }}"
                   @if ($isActive) aria-current="page" @endif
                   class="shrink-0 px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors {{ $isActive ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300' }}"
                   wire:navigate>
                    {{ $tabLabel }}
                </a>
            @endforeach
        </div>

        {{-- Available --}}
        @php
            $available = $exams->filter(fn ($exam) => $exam->canStudentAttempt($studentId));
            $completed = $exams->filter(fn ($exam) => ! $exam->canStudentAttempt($studentId) && $exam->completedAttemptsFor($studentId) > 0);
        @endphp

        @if ($available->count())
            <div>
                <h2 class="text-base font-semibold text-zinc-700 dark:text-zinc-300 mb-3 flex items-center gap-2">
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-semibold bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>{{ __('Ongoing') }}
                    </span>
                    <span class="text-sm text-zinc-500 font-normal">{{ __('Currently open — you can take these now') }}</span>
                </h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($available as $exam)
                        @php
                            $attemptsDone = $exam->completedAttemptsFor($studentId);
                            $bestAttempt = $exam->bestAttemptForStudent($studentId);
                            $inProgress = $exam->resumableAttemptForStudent($studentId);
                            $activeReset = $exam->activeResetForStudent($studentId);
                            $effectiveDeadline = $inProgress?->accessReset?->available_until
                                ?? $activeReset?->available_until
                                ?? $exam->available_until;
                            $hasTaken = $attemptsDone > 0;
                            $examRoutePrefix = 'student.exams';
                            $typeColors = ['assessment' => 'sky', 'assignment' => 'amber', 'exam' => 'indigo'];
                            $examTypeLabel = match ($exam->category) {
                                'assessment' => __('Assessment'),
                                'assignment' => __('Assignment'),
                                default => __('Exam'),
                            };
                        @endphp
                        <div class="rounded-lg border {{ $hasTaken ? 'border-emerald-200 dark:border-emerald-800/50' : 'border-zinc-200 dark:border-zinc-700' }} bg-white dark:bg-zinc-800 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-semibold text-zinc-900 dark:text-white truncate">{{ $exam->title }}</h3>
                                    @if ($exam->subject)
                                        <p class="text-xs text-indigo-600 dark:text-indigo-400 font-medium">{{ $exam->subject->name }}</p>
                                    @endif
                                </div>
                                <div class="flex max-w-[55%] shrink-0 flex-wrap items-center justify-end gap-1.5">
                                    @if ($hasTaken)
                                        <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                            ✓ {{ __('Taken') }}
                                        </span>
                                    @endif
                                    @if ($activeReset || $inProgress?->accessReset)
                                        <flux:badge color="purple" size="sm">{{ __('Reset access') }}</flux:badge>
                                    @endif
                                    <flux:badge :color="$typeColors[$exam->category] ?? 'zinc'" size="sm">{{ $examTypeLabel }}</flux:badge>
                                </div>
                            </div>

                            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-2">
                                {{ $exam->questions_count ?? $exam->questions->count() }} {{ __('questions') }}
                                @if ($exam->time_limit_minutes)
                                    &middot; {{ $exam->time_limit_minutes }} {{ __('min') }}
                                @endif
                                @if ($effectiveDeadline)
                                    &middot; {{ __('Closes:') }} {{ $effectiveDeadline->format('M j, g:i A') }}
                                @endif
                            </p>

                            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                {{ __('Completed attempts:') }} {{ $attemptsDone }}
                                @if ($bestAttempt)
                                    &middot; {{ __('Best:') }} {{ number_format($bestAttempt->percentage, 0) }}%
                                @endif
                            </p>

                            <div class="mt-3">
                                @if ($inProgress)
                                    <form method="POST" action="{{ route($examRoutePrefix . '.start', $exam) }}">
                                        @csrf
                                        <flux:button type="submit" variant="primary" size="sm" icon="play">
                                            {{ __('Resume') }}
                                        </flux:button>
                                    </form>
                                @else
                                    <flux:button variant="primary" size="sm" icon="play" href="{{ route($examRoutePrefix . '.show', $exam) }}" wire:navigate>
                                        @if ($activeReset)
                                            {{ __('Start Again') }}
                                        @else
                                            {{ $attemptsDone > 0 ? __('Retake') : __('View Details') }}
                                        @endif
                                    </flux:button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Completed --}}
        @if ($completed->count())
            <div>
                <h2 class="text-base font-semibold text-zinc-700 dark:text-zinc-300 mb-3">{{ __('Completed') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($completed as $exam)
                        @php
                            $bestAttempt = $exam->bestAttemptForStudent($studentId);
                            $latestAttempt = $exam->latestAttemptFor($studentId);
                            $examRoutePrefix = 'student.exams';
                        @endphp
                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4">
                            <div class="flex items-start justify-between">
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-zinc-900 dark:text-white truncate">{{ $exam->title }}</h3>
                                    @if ($exam->subject)
                                        <p class="text-xs text-indigo-600 dark:text-indigo-400 font-medium">{{ $exam->subject->name }}</p>
                                    @endif
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">
                                        {{ $exam->questions_count ?? $exam->questions->count() }} {{ __('questions') }}
                                    </p>
                                </div>
                                @if ($bestAttempt)
                                    <div class="text-right shrink-0">
                                        <span class="text-lg font-bold {{ $bestAttempt->passed ? 'text-green-600' : 'text-red-600' }}">
                                            {{ number_format($bestAttempt->percentage, 0) }}%
                                        </span>
                                        <p class="text-xs {{ $bestAttempt->passed ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $bestAttempt->passed ? __('Passed') : __('Failed') }}
                                        </p>
                                        @if ($bestAttempt->status === 'grading')
                                            <flux:badge color="amber" size="sm" class="mt-1">{{ __('Awaiting Grading') }}</flux:badge>
                                        @elseif ($bestAttempt->status === 'graded')
                                            <flux:badge color="blue" size="sm" class="mt-1">{{ __('Graded') }}</flux:badge>
                                        @elseif ($latestAttempt?->wasTimeElapsed())
                                            <flux:badge color="red" size="sm" class="mt-1">{{ __('Time Elapsed') }}</flux:badge>
                                        @elseif (in_array($latestAttempt?->completion_reason, ['reset_by_admin', 'force_ended_by_admin'], true))
                                            <flux:badge color="purple" size="sm" class="mt-1">{{ __('Ended by School') }}</flux:badge>
                                        @elseif ($latestAttempt?->completion_reason === 'tab_switch_limit')
                                            <flux:badge color="amber" size="sm" class="mt-1">{{ __('Auto-submitted') }}</flux:badge>
                                        @elseif ($latestAttempt?->status === 'grading_failed')
                                            <flux:badge color="red" size="sm" class="mt-1">{{ __('Grading Issue') }}</flux:badge>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            @if ($bestAttempt)
                                <div class="mt-3">
                                    <flux:button variant="subtle" size="sm" href="{{ route($examRoutePrefix . '.results', $bestAttempt) }}" wire:navigate>
                                        {{ __('View Results') }}
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Upcoming (published but not yet open) --}}
        @if ($upcoming->count())
            <div>
                <h2 class="text-base font-semibold text-zinc-700 dark:text-zinc-300 mb-3">{{ __('Upcoming') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($upcoming as $exam)
                        <div class="rounded-lg border border-amber-200 dark:border-amber-700/50 bg-amber-50/50 dark:bg-amber-900/10 p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-zinc-900 dark:text-white truncate">{{ $exam->title }}</h3>
                                    @if ($exam->subject)
                                        <p class="text-xs text-indigo-600 dark:text-indigo-400 font-medium">{{ $exam->subject->name }}</p>
                                    @endif
                                </div>
                                <flux:badge color="amber" size="sm">{{ __('Upcoming') }}</flux:badge>
                            </div>

                            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-2">
                                {{ $exam->questions()->count() }} {{ __('questions') }}
                                @if ($exam->time_limit_minutes)
                                    &middot; {{ $exam->time_limit_minutes }} {{ __('min') }}
                                @endif
                            </p>

                            <div class="mt-2 flex items-center gap-1.5 text-sm font-medium text-amber-700 dark:text-amber-400">
                                <flux:icon name="clock" class="size-4" />
                                {{ __('Available on :date at :time', [
                                    'date' => $exam->available_from->format('M j, Y'),
                                    'time' => $exam->available_from->format('g:i A'),
                                ]) }}
                            </div>

                            @if ($exam->available_until)
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-1">
                                    {{ __('Due:') }} {{ $exam->available_until->format('M j, Y \a\t g:i A') }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Closed / Past --}}
        @if ($closed->count())
            <div>
                <h2 class="text-base font-semibold text-zinc-700 dark:text-zinc-300 mb-3">{{ __('Closed') }}</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($closed as $exam)
                        @php
                            $bestAttempt  = $exam->bestAttemptForStudent($studentId);
                            $latestAttempt = $exam->latestAttemptFor($studentId);
                            $attemptsDone = $exam->completedAttemptsFor($studentId);
                            $typeColors   = ['assessment' => 'sky', 'assignment' => 'amber', 'exam' => 'indigo'];
                            $examTypeLabel = match ($exam->category) {
                                'assessment' => __('Assessment'),
                                'assignment' => __('Assignment'),
                                default      => __('Exam'),
                            };
                        @endphp
                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50/50 dark:bg-zinc-800/50 p-4 opacity-90">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-zinc-800 dark:text-zinc-200 truncate">{{ $exam->title }}</h3>
                                    @if ($exam->subject)
                                        <p class="text-xs text-indigo-600 dark:text-indigo-400 font-medium">{{ $exam->subject->name }}</p>
                                    @endif
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-1">
                                    <flux:badge :color="$typeColors[$exam->category] ?? 'zinc'" size="sm">{{ $examTypeLabel }}</flux:badge>
                                    @if ($attemptsDone === 0)
                                        <flux:badge color="red" size="sm">{{ __('Missed :type', ['type' => $examTypeLabel]) }}</flux:badge>
                                    @elseif ($latestAttempt?->wasTimeElapsed())
                                        <flux:badge color="red" size="sm">{{ __('Time Elapsed') }}</flux:badge>
                                    @elseif (in_array($latestAttempt?->completion_reason, ['reset_by_admin', 'force_ended_by_admin'], true))
                                        <flux:badge color="purple" size="sm">{{ __('Ended by School') }}</flux:badge>
                                    @elseif ($latestAttempt?->completion_reason === 'tab_switch_limit')
                                        <flux:badge color="amber" size="sm">{{ __('Auto-submitted') }}</flux:badge>
                                    @elseif ($latestAttempt?->status === 'grading_failed')
                                        <flux:badge color="red" size="sm">{{ __('Grading Issue') }}</flux:badge>
                                    @endif
                                </div>
                            </div>

                            <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Closed:') }} {{ $exam->available_until->format('M j, Y \a\t g:i A') }}
                            </p>

                            @if ($bestAttempt)
                                <div class="mt-3 flex items-center justify-between gap-3">
                                    <div>
                                        <span class="text-lg font-bold {{ $bestAttempt->passed ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ number_format($bestAttempt->percentage ?? 0, 0) }}%
                                        </span>
                                        <span class="ml-1 text-xs {{ $bestAttempt->passed ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ $bestAttempt->passed ? __('Passed') : __('Failed') }}
                                        </span>
                                        @if ($bestAttempt->status === 'grading')
                                            <flux:badge color="amber" size="sm" class="ml-1">{{ __('Awaiting Grading') }}</flux:badge>
                                        @endif
                                    </div>
                                    <flux:button variant="subtle" size="sm" href="{{ route($routePrefix . '.results', $bestAttempt) }}" wire:navigate>
                                        {{ __('View Results') }}
                                    </flux:button>
                                </div>
                            @else
                                <p class="mt-3 text-sm text-zinc-400 dark:text-zinc-500 italic">
                                    {{ __('You missed this :type.', ['type' => Str::lower($examTypeLabel)]) }}
                                </p>
                                <div class="mt-3">
                                    <flux:button variant="subtle" size="sm" href="{{ route($routePrefix . '.show', $exam) }}" wire:navigate>
                                        {{ __('View Details') }}
                                    </flux:button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Empty state --}}
        @if ($available->isEmpty() && $completed->isEmpty() && $upcoming->isEmpty() && $closed->isEmpty())
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-8 text-center">
                <flux:icon name="clipboard-document-list" class="mx-auto h-12 w-12 text-zinc-400" />
                <h3 class="mt-2 text-sm font-semibold text-zinc-900 dark:text-white">{{ __('No items available') }}</h3>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Your teacher has not published any exams, assessments, or assignments for your class yet.') }}</p>
            </div>
        @endif

        @if ($exams->hasPages())
            <div class="mt-4">{{ $exams->links() }}</div>
        @endif
    </div>
</x-layouts::app>
