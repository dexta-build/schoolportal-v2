<x-layouts::app :title="__('Quiz Results')">
    <div class="space-y-6">
        <div>
            <flux:button variant="subtle" size="sm" href="{{ route('student.quizzes.index') }}" wire:navigate class="mb-2">
                <flux:icon name="arrow-left" class="size-4 mr-1" /> {{ __('Back to Quizzes') }}
            </flux:button>
        </div>

        {{-- Score Card --}}
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6 text-center">
            <h1 class="text-lg font-bold text-zinc-900 dark:text-white mb-4">{{ $quiz->title }}</h1>

            <div class="text-4xl sm:text-5xl font-bold {{ $attempt->passed ? 'text-green-600' : 'text-red-600' }}">
                {{ $attempt->score }}/{{ $attempt->total_points }}
            </div>
            <div class="text-xl font-semibold text-zinc-600 dark:text-zinc-300 mt-1">
                {{ number_format($attempt->percentage, 1) }}%
            </div>

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
                @if ($attempt->status === 'timed_out')
                    <flux:badge color="amber" size="sm" class="ml-1">{{ __('Timed Out') }}</flux:badge>
                @endif
            </div>

            @if ($attempt->time_spent_seconds)
                <p class="text-sm text-zinc-500 mt-2">
                    {{ __('Time:') }} {{ floor($attempt->time_spent_seconds / 60) }}m {{ $attempt->time_spent_seconds % 60 }}s
                </p>
            @endif

            {{-- Retake button --}}
            @if ($quiz->canStudentAttempt(auth()->id()))
                <div class="mt-4">
                    <form method="POST" action="{{ route('student.quizzes.start', $quiz) }}">
                        @csrf
                        <flux:button type="submit" variant="primary" size="sm">
                            {{ __('Retake Quiz') }} ({{ $quiz->attemptsForStudent(auth()->id()) }}/{{ $quiz->max_attempts }})
                        </flux:button>
                    </form>
                </div>
            @endif
        </div>

        {{-- Answer Review --}}
        @if ($quiz->show_correct_answers)
            <div class="space-y-3">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Answer Review') }}</h2>

                @foreach ($quiz->questions as $index => $question)
                    @php
                        $answer = $answers[$question->id] ?? null;
                        $isCorrect = $answer?->is_correct;
                        $selectedAnswer = $answer?->selected_answer;
                    @endphp
                    <div class="rounded-lg border p-4 {{ $isCorrect ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950/20' : 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950/20' }}">
                        <div class="flex items-start gap-2 mb-2">
                            @if ($isCorrect)
                                <flux:icon name="check-circle" class="size-5 text-green-600 shrink-0 mt-0.5" />
                            @else
                                <flux:icon name="x-circle" class="size-5 text-red-600 shrink-0 mt-0.5" />
                            @endif
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">
                                    {{ __('Q:num', ['num' => $index + 1]) }}. {{ $question->question_text }}
                                </p>
                            </div>
                        </div>

                        <div class="ml-7 text-sm space-y-1">
                            @if ($selectedAnswer)
                                <p class="{{ $isCorrect ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' }}">
                                    {{ __('Your answer:') }} {{ $selectedAnswer }}
                                    {{ $isCorrect ? '✓' : '✗' }}
                                </p>
                            @else
                                <p class="text-zinc-500 italic">{{ __('Not answered') }}</p>
                            @endif

                            @if (! $isCorrect)
                                <p class="text-green-700 dark:text-green-400 font-medium">
                                    {{ __('Correct answer:') }} {{ $question->correct_answer }}
                                </p>
                            @endif

                            @if ($question->explanation)
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
        try { localStorage.removeItem('quiz_draft_{{ $attempt->id }}'); } catch (e) {}
    </script>
    @endpush
</x-layouts::app>
