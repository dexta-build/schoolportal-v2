<x-layouts::app :title="__(':label Results', ['label' => $label])">
    <div class="space-y-6">
        <div>
            <flux:button variant="subtle" size="sm" href="{{ route($routePrefix . '.index') }}" wire:navigate class="mb-2">
                <flux:icon name="arrow-left" class="size-4 mr-1" /> {{ __('Back to :label', ['label' => Str::plural($label)]) }}
            </flux:button>
        </div>

        {{-- Score Card --}}
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6 text-center">
            <h1 class="text-lg font-bold text-zinc-900 dark:text-white mb-1">{{ $exam->title }}</h1>
            @if ($exam->subject)
                <p class="text-sm text-indigo-600 dark:text-indigo-400 font-medium mb-4">{{ $exam->subject->name }}</p>
            @endif

            @if ($attempt->status === 'submitted' || $attempt->status === 'timed_out' && $attempt->score === null)
                {{-- Job dispatched but not yet processed by the queue worker --}}
                <div class="text-4xl sm:text-5xl font-bold text-zinc-400">--</div>
                <div class="text-sm text-zinc-500 mt-2">
                    <flux:badge color="zinc" size="sm">{{ __('Grading in Progress') }}</flux:badge>
                </div>
                <p class="text-xs text-zinc-500 mt-2">
                    {{ __('Your answers have been submitted. Results will appear shortly — please refresh this page in a moment.') }}
                </p>
            @elseif ($attempt->status === 'grading')
                {{-- Partial score - awaiting manual grading for theory questions --}}
                <div class="text-4xl sm:text-5xl font-bold text-amber-600">
                    {{ $attempt->score ?? '?' }}/{{ $attempt->total_points }}
                </div>
                <div class="text-sm text-amber-600 mt-2">
                    <flux:badge color="amber" size="sm">{{ __('Awaiting Teacher Grading') }}</flux:badge>
                </div>
                <p class="text-xs text-zinc-500 mt-2">
                    {{ __('Some questions require manual grading by your teacher. Your final score will be updated once grading is complete.') }}
                </p>
            @else
                <div class="text-4xl sm:text-5xl font-bold {{ $attempt->passed ? 'text-green-600' : 'text-red-600' }}">
                    {{ $attempt->score }}/{{ $attempt->total_points }}
                </div>
                <div class="text-xl font-semibold text-zinc-600 dark:text-zinc-300 mt-1">
                    {{ number_format($attempt->percentage, 1) }}%
                </div>

                @if ($grade)
                    <div class="mt-1">
                        <span class="inline-flex items-center gap-1 text-sm font-semibold text-indigo-600 dark:text-indigo-400">
                            {{ $grade['grade'] }} &mdash; {{ $grade['label'] }}
                        </span>
                    </div>
                @endif

                {{-- Progress bar --}}
                <div class="max-w-sm mx-auto mt-4 h-3 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-500 {{ $attempt->passed ? 'bg-green-500' : 'bg-red-500' }}"
                        style="width: {{ min($attempt->percentage, 100) }}%"></div>
                </div>

                <div class="mt-3">
                    @if ($attempt->passed)
                        <flux:badge color="green" size="sm">{{ __('PASSED') }}</flux:badge>
                    @else
                        <flux:badge color="red" size="sm">{{ __('FAILED') }}</flux:badge>
                    @endif
                </div>
            @endif

            @if ($attempt->wasTimeElapsed())
                <div class="mt-3">
                    <flux:badge color="red" size="sm">{{ __('Time Elapsed') }}</flux:badge>
                </div>
            @elseif ($attempt->completion_reason === 'tab_switch_limit')
                <div class="mt-3">
                    <flux:badge color="amber" size="sm">{{ __('Auto-submitted after tab-switch limit') }}</flux:badge>
                </div>
            @elseif (in_array($attempt->completion_reason, ['reset_by_admin', 'force_ended_by_admin'], true))
                <div class="mt-3">
                    <flux:badge color="purple" size="sm">{{ __('Ended by School') }}</flux:badge>
                </div>
            @endif

            {{-- Attempt Stats --}}
            <div class="flex items-center justify-center gap-4 mt-4 text-sm text-zinc-500">
                @if ($attempt->time_spent_seconds)
                    <span>
                        {{ __('Time:') }}
                        @if ($attempt->time_spent_seconds >= 3600)
                            {{ floor($attempt->time_spent_seconds / 3600) }}h
                        @endif
                        {{ floor(($attempt->time_spent_seconds % 3600) / 60) }}m {{ $attempt->time_spent_seconds % 60 }}s
                    </span>
                @endif
                @if ($attempt->tab_switches > 0)
                    <span class="text-orange-500">
                        {{ __(':n tab switch(es)', ['n' => $attempt->tab_switches]) }}
                    </span>
                @endif
                <span>{{ __('Attempt :n', ['n' => $attempt->attempt_number]) }}</span>
            </div>

            {{-- Retake button --}}
            @if ($exam->canStudentAttempt(auth()->id()))
                <div class="mt-4">
                    <flux:button variant="primary" size="sm" href="{{ route($routePrefix . '.show', $exam) }}" wire:navigate>
                        @if ($exam->hasActiveResetForStudent(auth()->id()))
                            {{ __('Start :label Again', ['label' => $label]) }}
                        @else
                            {{ __('Retake :label', ['label' => $label]) }} ({{ $exam->completedAttemptsFor(auth()->id()) }}/{{ $exam->max_attempts }})
                        @endif
                    </flux:button>
                </div>
            @endif
        </div>

        {{-- Answer Review --}}
        @if ($exam->show_correct_answers || $attempt->status === 'grading' || $attempt->status === 'graded')
            <div class="space-y-3">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Answer Review') }}</h2>

                @foreach ($exam->questions()->orderBy('sort_order')->get() as $index => $question)
                    @php
                        $answer = $answers[$question->id] ?? null;
                        $isCorrect = $answer?->is_correct;
                        $isTheoryType = in_array($question->type, ['short_answer', 'theory']);
                        $selectedAnswer = $isTheoryType ? $answer?->theory_answer : $answer?->selected_answer;
                        $needsGrading = $answer && $answer->is_correct === null && $isTheoryType;
                    @endphp
                    <div class="rounded-lg border p-4
                        {{ $needsGrading
                            ? 'border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/20'
                            : ($isCorrect
                                ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950/20'
                                : ($answer && $isCorrect === false
                                    ? 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/20'
                                    : 'border-zinc-200 dark:border-zinc-700')) }}">

                        <div class="flex items-start gap-2 mb-2">
                            @if ($needsGrading)
                                <flux:icon name="clock" class="size-5 text-amber-500 shrink-0 mt-0.5" />
                            @elseif ($isCorrect)
                                <flux:icon name="check-circle" class="size-5 text-green-600 shrink-0 mt-0.5" />
                            @elseif ($answer && $isCorrect === false)
                                <flux:icon name="x-circle" class="size-5 text-red-600 shrink-0 mt-0.5" />
                            @else
                                <flux:icon name="minus-circle" class="size-5 text-zinc-400 shrink-0 mt-0.5" />
                            @endif
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white">
                                        {{ __('Q:num', ['num' => $index + 1]) }}. {{ $question->question_text }}
                                    </p>
                                </div>
                                @php
                                    $typeBadgeColors = [
                                        'multiple_choice' => 'indigo',
                                        'true_false' => 'purple',
                                        'fill_blank' => 'amber',
                                        'short_answer' => 'cyan',
                                        'theory' => 'emerald',
                                        'matching' => 'pink',
                                    ];
                                @endphp
                                <flux:badge :color="$typeBadgeColors[$question->type] ?? 'zinc'" size="sm" class="mt-1">
                                    {{ ucfirst(str_replace('_', ' ', $question->type)) }}
                                </flux:badge>
                                <span class="text-xs text-zinc-400 ml-1">{{ $question->points }} {{ Str::plural('pt', $question->points) }}</span>
                                @if ($answer)
                                    <span class="text-xs text-zinc-400 ml-1">&middot; {{ __('Earned:') }} {{ $answer->points_earned }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="ml-7 text-sm space-y-1">
                            @if ($selectedAnswer)
                                @if (in_array($question->type, ['theory', 'short_answer']))
                                    <div class="p-3 rounded bg-white/50 dark:bg-zinc-800/50 text-zinc-800 dark:text-zinc-200 text-sm whitespace-pre-wrap">{{ $selectedAnswer }}</div>
                                @elseif ($question->type === 'matching')
                                    @php
                                        $matchData = json_decode($selectedAnswer, true);
                                        $leftItems = collect($question->options)->pluck('left')->toArray();
                                    @endphp
                                    @if (is_array($matchData))
                                        <div class="space-y-1">
                                            @foreach ($matchData as $idx => $right)
                                                <p class="text-zinc-700 dark:text-zinc-300">
                                                    {{ $leftItems[$idx] ?? '?' }} → {{ $right }}
                                                </p>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-zinc-700 dark:text-zinc-300">{{ $selectedAnswer }}</p>
                                    @endif
                                @else
                                    <p class="{{ $isCorrect ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                                        {{ __('Your answer:') }} {{ $selectedAnswer }}
                                        {{ $isCorrect ? '✓' : '✗' }}
                                    </p>
                                @endif
                            @else
                                <p class="text-zinc-500 italic">{{ __('Not answered') }}</p>
                            @endif

                            @if ($needsGrading)
                                <p class="text-amber-600 dark:text-amber-400 text-xs font-medium">
                                    {{ __('Awaiting teacher grading') }}
                                </p>
                            @endif

                            @if ($answer?->teacher_comment)
                                <div class="p-2 mt-1 rounded bg-indigo-50 dark:bg-indigo-950/20 text-indigo-700 dark:text-indigo-300 text-xs">
                                    <span class="font-medium">{{ __('Teacher comment:') }}</span> {{ $answer->teacher_comment }}
                                </div>
                            @endif

                            @if ($exam->show_correct_answers && ! $isCorrect && $question->isObjective())
                                <p class="text-green-700 dark:text-green-400 font-medium">
                                    {{ __('Correct answer:') }} {{ $question->correct_answer }}
                                </p>
                            @endif

                            @if ($exam->show_correct_answers && $question->explanation)
                                <p class="text-zinc-600 dark:text-zinc-400 text-xs mt-1 italic">
                                    {{ $question->explanation }}
                                </p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    @push('scripts')
    <script>
        // Submission succeeded — the take page's local answer backup is no longer needed
        try { localStorage.removeItem('exam_draft_{{ $attempt->id }}'); } catch (e) {}
    </script>
    @endpush
</x-layouts::app>
