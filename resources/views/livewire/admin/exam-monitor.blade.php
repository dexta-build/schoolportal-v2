<div wire:poll.5s>
    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <flux:card class="p-4 text-center">
            <span class="text-xs text-zinc-500 block">{{ __('Total Students') }}</span>
            <span class="text-3xl font-bold">{{ $summary['total'] }}</span>
        </flux:card>
        <flux:card class="p-4 text-center">
            <span class="text-xs text-zinc-500 block">{{ __('Not Started') }}</span>
            <span class="text-3xl font-bold text-zinc-400">{{ $summary['not_started'] }}</span>
        </flux:card>
        <flux:card class="p-4 text-center">
            <span class="text-xs text-zinc-500 block">{{ __('In Progress') }}</span>
            <span class="text-3xl font-bold text-amber-500">{{ $summary['in_progress'] }}</span>
            @if ($summary['in_progress'] > 0)
                <span class="inline-flex items-center gap-1 mt-1 text-xs text-amber-600">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                    </span>
                    {{ __('Live') }}
                </span>
            @endif
        </flux:card>
        <flux:card class="p-4 text-center">
            <span class="text-xs text-zinc-500 block">{{ __('Submitted') }}</span>
            <span class="text-3xl font-bold text-green-600">{{ $summary['submitted'] }}</span>
        </flux:card>
    </div>

    {{-- Progress Bar --}}
    @if ($summary['total'] > 0)
        <div class="mb-6">
            <div class="flex items-center justify-between text-xs text-zinc-500 mb-1">
                <span>{{ __('Completion Progress') }}</span>
                <span>{{ $summary['submitted'] }} / {{ $summary['total'] }} ({{ round(($summary['submitted'] / $summary['total']) * 100) }}%)</span>
            </div>
            <div class="w-full h-3 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden flex">
                @php
                    $completedPct = ($summary['submitted'] / $summary['total']) * 100;
                    $inProgressPct = ($summary['in_progress'] / $summary['total']) * 100;
                    $notStartedPct = ($summary['not_started'] / $summary['total']) * 100;
                @endphp
                <div class="h-full bg-green-500 transition-all duration-500" style="width: {{ $completedPct }}%"></div>
                <div class="h-full bg-amber-400 transition-all duration-500" style="width: {{ $inProgressPct }}%"></div>
            </div>
            <div class="flex items-center gap-4 mt-2 text-xs text-zinc-500">
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-green-500"></span> {{ __('Submitted') }}</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span> {{ __('In Progress') }}</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-zinc-300 dark:bg-zinc-600"></span> {{ __('Not Started') }}</span>
            </div>
        </div>
    @endif

    {{-- Bulk action bar (only when students are in-progress) --}}
    @if ($summary['in_progress'] > 0)
        <div class="mb-4 flex items-center justify-between rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-800/40 dark:bg-amber-900/20">
            <p class="text-sm text-amber-800 dark:text-amber-300">
                <strong>{{ $summary['in_progress'] }}</strong>
                {{ trans_choice('student is|students are', $summary['in_progress']) }} {{ __('currently taking the exam.') }}
            </p>
            <flux:button
                variant="danger"
                size="sm"
                x-on:click="appConfirm({
                    title: {{ Js::from(__('End the exam for everyone?')) }},
                    message: {{ Js::from(__('All :n students still writing will be stopped now and their answers graded. This cannot be undone.', ['n' => $summary['in_progress']])) }},
                    confirmLabel: {{ Js::from(__('End exam for all')) }},
                    variant: 'danger',
                }).then(ok => ok && $wire.forceEndAll())"
            >
                <flux:icon.x-circle class="size-4 mr-1" />
                {{ __('End Exam for All') }}
            </flux:button>
        </div>
    @endif

    {{-- Suspicious activity notice --}}
    @php $flaggedCount = $students->where('flagged', true)->count(); @endphp
    @if ($flaggedCount > 0)
        <div class="mb-4 flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/40 dark:bg-red-900/20 dark:text-red-300">
            <flux:icon.exclamation-triangle class="size-5 shrink-0 text-red-500" />
            <span>
                {{ trans_choice(':n student has|:n students have', $flaggedCount, ['n' => $flaggedCount]) }}
                {{ __('exceeded the tab-switch limit (:max). Rows highlighted in red.', ['max' => $maxTabSwitchWarning]) }}
            </span>
        </div>
    @endif

    {{-- Student List --}}
    <flux:card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-zinc-500">
                        <th class="p-3 font-medium">{{ __('Student') }}</th>
                        <th class="p-3 font-medium">{{ __('Status') }}</th>
                        <th class="p-3 font-medium">{{ __('Progress') }}</th>
                        <th class="p-3 font-medium hidden sm:table-cell">{{ __('Started') }}</th>
                        <th class="p-3 font-medium hidden md:table-cell">{{ __('Tab Switches') }}</th>
                        <th class="p-3 font-medium">{{ __('Score') }}</th>
                        <th class="p-3 font-medium">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        <tr class="border-b transition-colors
                            {{ $student->flagged ? 'bg-red-50/60 dark:bg-red-900/15 border-l-4 border-l-red-400' : ($student->attempt_status === 'in_progress' ? 'bg-amber-50/50 dark:bg-amber-900/10' : 'hover:bg-zinc-50 dark:hover:bg-zinc-800/50') }}">
                            <td class="p-3">
                                <div class="font-medium">{{ $student->name }}</div>
                                <div class="text-xs text-zinc-400">{{ $student->username }}</div>
                            </td>
                            <td class="p-3">
                                <flux:badge :color="$student->status_color" size="sm">
                                    @if ($student->status === 'In Progress')
                                        <span class="relative flex h-1.5 w-1.5 mr-1">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-amber-500"></span>
                                        </span>
                                    @endif
                                    {{ __($student->status) }}
                                </flux:badge>
                            </td>
                            <td class="p-3">
                                @if ($student->attempt_status === 'in_progress')
                                    <div class="flex items-center gap-2">
                                        <div class="w-16 h-1.5 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                                            @php
                                                $totalQ = $this->exam->total_questions ?: 1;
                                                $pct = min(100, round(($student->answered_count / $totalQ) * 100));
                                            @endphp
                                            <div class="h-full bg-amber-500 transition-all" style="width: {{ $pct }}%"></div>
                                        </div>
                                        <span class="text-xs text-zinc-500">{{ $student->answered_count }}/{{ $this->exam->total_questions }}</span>
                                    </div>
                                @elseif ($student->attempt_status)
                                    <span class="text-xs text-zinc-500">{{ __('Complete') }}</span>
                                @else
                                    <span class="text-xs text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="p-3 hidden sm:table-cell text-xs text-zinc-500">
                                {{ $student->started_at?->diffForHumans() ?? '—' }}
                            </td>
                            <td class="p-3 hidden md:table-cell">
                                @if ($student->flagged)
                                    <flux:badge color="red" size="sm" class="font-bold">
                                        <flux:icon.exclamation-triangle class="size-3 mr-0.5" />
                                        {{ $student->tab_switches }}
                                    </flux:badge>
                                @elseif ($student->tab_switches > 0)
                                    <flux:badge color="orange" size="sm">{{ $student->tab_switches }}</flux:badge>
                                @else
                                    <span class="text-zinc-400">0</span>
                                @endif
                            </td>
                            <td class="p-3 font-mono">
                                @if ($student->percentage !== null)
                                    <span class="{{ $student->passed ? 'text-green-600' : 'text-red-600' }}">{{ $student->percentage }}%</span>
                                @elseif ($student->attempt_status && $student->attempt_status !== 'in_progress')
                                    <span class="text-zinc-400">{{ __('Pending') }}</span>
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="p-3">
                                @if ($student->attempt_status === 'in_progress')
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        class="text-red-600 hover:text-red-700 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/20"
                                        x-on:click="appConfirm({
                                            title: {{ Js::from(__('End the exam for :name?', ['name' => $student->name])) }},
                                            message: {{ Js::from(__('They will be stopped now, marked as Timed Out, and their current answers graded.')) }},
                                            confirmLabel: {{ Js::from(__('End exam')) }},
                                            variant: 'danger',
                                        }).then(ok => ok && $wire.forceEnd({{ $student->id }}))"
                                    >
                                        <flux:icon.x-circle class="size-4 mr-1" />
                                        {{ __('End') }}
                                    </flux:button>
                                @else
                                    <span class="text-xs text-zinc-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-8 text-center text-zinc-500">{{ __('No students in this class.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    <div class="text-xs text-zinc-400 text-center mt-2">
        <flux:icon name="arrow-path" class="size-3 inline animate-spin" /> {{ __('Auto-refreshes every 5 seconds') }}
    </div>
</div>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
        <flux:card class="p-4 text-center">
            <span class="text-xs text-zinc-500 block">{{ __('Total Students') }}</span>
            <span class="text-3xl font-bold">{{ $summary['total'] }}</span>
        </flux:card>
        <flux:card class="p-4 text-center">
            <span class="text-xs text-zinc-500 block">{{ __('Not Started') }}</span>
            <span class="text-3xl font-bold text-zinc-400">{{ $summary['not_started'] }}</span>
        </flux:card>
        <flux:card class="p-4 text-center">
            <span class="text-xs text-zinc-500 block">{{ __('In Progress') }}</span>
            <span class="text-3xl font-bold text-amber-500">{{ $summary['in_progress'] }}</span>
            @if ($summary['in_progress'] > 0)
                <span class="inline-flex items-center gap-1 mt-1 text-xs text-amber-600">
                    <span class="relative flex h-2 w-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                    </span>
                    {{ __('Live') }}
                </span>
            @endif
        </flux:card>
        <flux:card class="p-4 text-center">
            <span class="text-xs text-zinc-500 block">{{ __('Submitted') }}</span>
            <span class="text-3xl font-bold text-green-600">{{ $summary['submitted'] }}</span>
        </flux:card>
    </div>

    {{-- Progress Bar --}}
    @if ($summary['total'] > 0)
        <div class="mb-6">
            <div class="flex items-center justify-between text-xs text-zinc-500 mb-1">
                <span>{{ __('Completion Progress') }}</span>
                <span>{{ $summary['submitted'] }} / {{ $summary['total'] }} ({{ round(($summary['submitted'] / $summary['total']) * 100) }}%)</span>
            </div>
            <div class="w-full h-3 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden flex">
                @php
                    $completedPct = ($summary['submitted'] / $summary['total']) * 100;
                    $inProgressPct = ($summary['in_progress'] / $summary['total']) * 100;
                    $notStartedPct = ($summary['not_started'] / $summary['total']) * 100;
                @endphp
                <div class="h-full bg-green-500 transition-all duration-500" style="width: {{ $completedPct }}%"></div>
                <div class="h-full bg-amber-400 transition-all duration-500" style="width: {{ $inProgressPct }}%"></div>
            </div>
            <div class="flex items-center gap-4 mt-2 text-xs text-zinc-500">
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-green-500"></span> {{ __('Submitted') }}</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span> {{ __('In Progress') }}</span>
                <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-zinc-300 dark:bg-zinc-600"></span> {{ __('Not Started') }}</span>
            </div>
        </div>
    @endif

    {{-- Student List --}}
    <flux:card>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b text-left text-zinc-500">
                        <th class="p-3 font-medium">{{ __('Student') }}</th>
                        <th class="p-3 font-medium">{{ __('Status') }}</th>
                        <th class="p-3 font-medium">{{ __('Progress') }}</th>
                        <th class="p-3 font-medium hidden sm:table-cell">{{ __('Started') }}</th>
                        <th class="p-3 font-medium hidden md:table-cell">{{ __('Tab Switches') }}</th>
                        <th class="p-3 font-medium">{{ __('Score') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        <tr class="border-b hover:bg-zinc-50 dark:hover:bg-zinc-800/50 {{ $student->attempt_status === 'in_progress' ? 'bg-amber-50/50 dark:bg-amber-900/10' : '' }}">
                            <td class="p-3">
                                <div class="font-medium">{{ $student->name }}</div>
                                <div class="text-xs text-zinc-400">{{ $student->username }}</div>
                            </td>
                            <td class="p-3">
                                <flux:badge :color="$student->status_color" size="sm">
                                    @if ($student->status === 'In Progress')
                                        <span class="relative flex h-1.5 w-1.5 mr-1">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-amber-500"></span>
                                        </span>
                                    @endif
                                    {{ __($student->status) }}
                                </flux:badge>
                            </td>
                            <td class="p-3">
                                @if ($student->attempt_status === 'in_progress')
                                    <div class="flex items-center gap-2">
                                        <div class="w-16 h-1.5 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                                            @php
                                                $totalQ = $this->exam->total_questions ?: 1;
                                                $pct = min(100, round(($student->answered_count / $totalQ) * 100));
                                            @endphp
                                            <div class="h-full bg-amber-500 transition-all" style="width: {{ $pct }}%"></div>
                                        </div>
                                        <span class="text-xs text-zinc-500">{{ $student->answered_count }}/{{ $this->exam->total_questions }}</span>
                                    </div>
                                @elseif ($student->attempt_status)
                                    <span class="text-xs text-zinc-500">{{ __('Complete') }}</span>
                                @else
                                    <span class="text-xs text-zinc-400">—</span>
                                @endif
                            </td>
                            <td class="p-3 hidden sm:table-cell text-xs text-zinc-500">
                                {{ $student->started_at?->diffForHumans() ?? '—' }}
                            </td>
                            <td class="p-3 hidden md:table-cell">
                                @if ($student->tab_switches > 0)
                                    <flux:badge color="red" size="sm">{{ $student->tab_switches }}</flux:badge>
                                @else
                                    <span class="text-zinc-400">0</span>
                                @endif
                            </td>
                            <td class="p-3 font-mono">
                                @if ($student->percentage !== null)
                                    <span class="{{ $student->passed ? 'text-green-600' : 'text-red-600' }}">{{ $student->percentage }}%</span>
                                @elseif ($student->attempt_status && $student->attempt_status !== 'in_progress')
                                    <span class="text-zinc-400">{{ __('Pending') }}</span>
                                @else
                                    <span class="text-zinc-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-8 text-center text-zinc-500">{{ __('No students in this class.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    <div class="text-xs text-zinc-400 text-center mt-2">
        <flux:icon name="arrow-path" class="size-3 inline animate-spin" /> {{ __('Auto-refreshes every 5 seconds') }}
    </div>
</div>
