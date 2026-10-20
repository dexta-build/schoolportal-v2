<x-layouts::app :title="$exam->title">
    <div x-data="examTaker()" x-init="init()" @visibilitychange.window="handleVisibilityChange()" class="space-y-4">

        {{-- Fullscreen suggestion overlay --}}
        <template x-if="showFullscreenPrompt">
            <div class="fixed inset-0 z-50 bg-zinc-900/80 flex items-center justify-center p-4">
                <div class="bg-white dark:bg-zinc-800 rounded-xl p-6 max-w-md w-full text-center space-y-4 shadow-2xl">
                    <flux:icon name="arrows-pointing-out" class="mx-auto size-12 text-indigo-500" />
                    <h2 class="text-lg font-bold text-zinc-900 dark:text-white">{{ __('Enter Fullscreen?') }}</h2>
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('For the best experience and to avoid accidental tab switches, we recommend fullscreen mode.') }}
                    </p>
                    <div class="flex gap-2 justify-center">
                        <flux:button variant="ghost" @click="showFullscreenPrompt = false">
                            {{ __('Skip') }}
                        </flux:button>
                        <flux:button variant="primary" icon="arrows-pointing-out" @click="enterFullscreen()">
                            {{ __('Go Fullscreen') }}
                        </flux:button>
                    </div>
                </div>
            </div>
        </template>

        {{-- Sticky Header with Timer --}}
        <div class="sticky top-0 z-10 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 sm:p-4 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-white truncate">{{ $exam->title }}</h1>
                    <p class="text-xs text-zinc-500">
                        {{ __('Question') }} <span x-text="currentIndex + 1"></span> {{ __('of') }} {{ $questions->count() }}
                        @if ($exam->max_tab_switches)
                            <span class="ml-2 text-zinc-400">&middot;</span>
                            <span :class="tabSwitches >= {{ $exam->max_tab_switches }} - 1 ? 'text-red-500' : 'text-zinc-500'"
                                class="ml-1">
                                <span x-text="'{{ __('Tabs:') }} ' + tabSwitches + '/{{ $exam->max_tab_switches }}'"></span>
                            </span>
                        @endif
                    </p>
                    {{-- Save status --}}
                    <p class="mt-0.5 flex items-center gap-1 text-xs" role="status" aria-live="polite">
                        <template x-if="sessionExpired">
                            <span class="font-medium text-red-600 dark:text-red-400">{{ __('Session expired — your answers are kept on this device. Log in again to continue.') }}</span>
                        </template>
                        <template x-if="!sessionExpired && saveRetrying && unsavedQuestionIds.length > 0">
                            <span class="font-medium text-amber-600 dark:text-amber-400"
                                x-text="'{{ __(':n answer(s) not saved yet — retrying. Keep this page open.') }}'.replace(':n', unsavedQuestionIds.length)"></span>
                        </template>
                        <template x-if="!sessionExpired && !saveRetrying && isSaving">
                            <span class="text-zinc-500">{{ __('Saving…') }}</span>
                        </template>
                        <template x-if="!sessionExpired && !saveRetrying && !isSaving && unsavedQuestionIds.length === 0 && answeredCount > 0">
                            <span class="text-emerald-600 dark:text-emerald-400">{{ __('All answers saved') }}</span>
                        </template>
                    </p>
                </div>
                @if ($remainingSeconds !== null)
                    <div class="shrink-0 text-right">
                        <div :class="timeRemaining <= 60 ? 'text-red-600 animate-pulse' : timeRemaining <= 300 ? 'text-amber-600' : 'text-zinc-700 dark:text-zinc-300'"
                            class="text-lg sm:text-xl font-mono font-bold">
                            <span x-text="formatTime(timeRemaining)"></span>
                        </div>
                        <p class="text-xs text-zinc-500">{{ __('remaining') }}</p>
                    </div>
                @endif
            </div>

            {{-- Progress bar --}}
            <div class="mt-2 h-1.5 bg-zinc-200 dark:bg-zinc-700 rounded-full overflow-hidden">
                <div class="h-full bg-indigo-600 rounded-full transition-all duration-300"
                    :style="'width: ' + Math.round((answeredCount / totalQuestions) * 100) + '%'"></div>
            </div>

            {{-- Navigation dots --}}
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($questions as $index => $question)
                    <button type="button" @click="goTo({{ $index }})"
                        :class="{
                            'ring-2 ring-indigo-500 bg-indigo-600 text-white': currentIndex === {{ $index }},
                            'bg-green-500 text-white': currentIndex !== {{ $index }} && answeredQuestions[{{ $question->id }}],
                            'bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-400': currentIndex !== {{ $index }} && !answeredQuestions[{{ $question->id }}]
                        }"
                        class="relative size-7 rounded-full text-xs font-medium flex items-center justify-center transition">
                        {{ $index + 1 }}
                        {{-- Flag indicator --}}
                        <span x-show="flaggedQuestions[{{ $question->id }}]" x-cloak
                            class="absolute -top-1 -right-1 size-3 bg-orange-500 rounded-full border border-white dark:border-zinc-800"></span>
                    </button>
                @endforeach
            </div>

            {{-- ⏱️ Timer warning banner (shown at ≤5 min remaining, inside sticky header) --}}
            @if ($remainingSeconds !== null)
                <div x-show="showTimerWarning" x-cloak
                     class="mt-2 -mx-3 sm:-mx-4 -mb-3 sm:-mb-4 flex items-center justify-between gap-3 px-4 py-2.5 rounded-b-lg transition-colors"
                     :class="timeRemaining <= 60
                         ? 'bg-red-600 text-white'
                         : 'bg-amber-500 text-white'">
                    <div class="flex items-center gap-2 text-sm font-medium">
                        <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                             :class="timeRemaining <= 60 ? 'animate-pulse' : ''"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                        <span x-text="timeRemaining <= 60
                            ? '{{ __('Less than 1 minute remaining — submit soon!') }}'
                            : '{{ __(':m minutes remaining — review your answers!') }}'.replace(':m', Math.ceil(timeRemaining / 60))"></span>
                    </div>
                    <button type="button" @click="showTimerWarning = false"
                        class="shrink-0 rounded p-1 hover:bg-white/20 transition-colors"
                        aria-label="{{ __('Dismiss timer warning') }}">
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            @endif
        </div>

        {{-- 📶 Offline queued-submit banner (shown when student submitted while offline) --}}
        <div x-show="offlineSubmitQueued" x-cloak
             class="mt-3 flex items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-950/40 dark:border-amber-800 px-4 py-3 text-sm text-amber-800 dark:text-amber-300"
             role="alert" aria-live="assertive">
            <svg class="size-5 shrink-0 animate-pulse" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.584 10.587a2 2 0 0 0 2.828 2.83m5.145 5.145A9.955 9.955 0 0 1 12 22C6.477 22 2 17.523 2 12c0-2.106.654-4.062 1.77-5.672m3.144-2.65A9.956 9.956 0 0 1 12 2c5.523 0 10 4.477 10 10 0 2.107-.655 4.063-1.77 5.673"/>
            </svg>
            <div>
                <p class="font-semibold">{{ __("You're offline — submission queued") }}</p>
                <p class="mt-0.5 text-xs opacity-80">{{ __('Your answers are saved on this device. The :label will submit automatically as soon as your connection is restored.', ['label' => Str::lower($label)]) }}</p>
            </div>
        </div>

        {{-- Questions --}}
        @foreach ($questions as $index => $question)
            <div x-show="currentIndex === {{ $index }}" x-cloak
                class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 sm:p-6">

                {{-- Question type badge & flag button --}}
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        @php
                            $badgeColors = [
                                'multiple_choice' => 'indigo',
                                'true_false' => 'purple',
                                'fill_blank' => 'amber',
                                'short_answer' => 'cyan',
                                'theory' => 'emerald',
                                'matching' => 'pink',
                            ];
                            $typeLabels = [
                                'multiple_choice' => 'Multiple Choice',
                                'true_false' => 'True/False',
                                'fill_blank' => 'Fill in the Blank',
                                'short_answer' => 'Short Answer',
                                'theory' => 'Theory',
                                'matching' => 'Matching',
                            ];
                        @endphp
                        <flux:badge :color="$badgeColors[$question->type] ?? 'zinc'" size="sm">
                            {{ $typeLabels[$question->type] ?? ucfirst($question->type) }}
                        </flux:badge>
                        @if ($question->section_label)
                            <span class="text-xs text-zinc-400">{{ $question->section_label }}</span>
                        @endif
                        <span class="text-xs text-zinc-400">{{ $question->points }} {{ Str::plural('pt', $question->points) }}</span>
                    </div>
                    <button type="button" @click="toggleFlag({{ $question->id }})"
                        :class="flaggedQuestions[{{ $question->id }}] ? 'text-orange-500' : 'text-zinc-400 hover:text-orange-400'"
                        class="transition" :title="flaggedQuestions[{{ $question->id }}] ? '{{ __('Remove flag') }}' : '{{ __('Flag for review') }}'">
                        <svg xmlns="http://www.w3.org/2000/svg" class="size-5" :fill="flaggedQuestions[{{ $question->id }}] ? 'currentColor' : 'none'" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5" />
                        </svg>
                    </button>
                </div>

                {{-- Question text --}}
                <p class="text-base sm:text-lg text-zinc-900 dark:text-white font-medium mb-4 select-none"
                    @contextmenu.prevent>{{ $question->question_text }}</p>

                {{-- Question image --}}
                @if ($question->question_image_url)
                    <div class="mb-4">
                        <img src="{{ $question->question_image_url }}" alt="{{ __('Question image') }}"
                            class="max-h-64 rounded-lg object-contain select-none" draggable="false"
                            @contextmenu.prevent>
                    </div>
                @endif

                {{-- Answer input based on type --}}
                @if ($question->type === 'multiple_choice')
                    @php
                        $options = $exam->shuffle_options
                            ? collect($question->options)->shuffle(crc32($attempt->id . '-' . $question->id))
                            : collect($question->options);
                    @endphp
                    <div class="space-y-2">
                        @foreach ($options as $option)
                            <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition"
                                :class="answeredQuestions[{{ $question->id }}] === {{ json_encode($option) }}
                                    ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-950/30'
                                    : 'border-zinc-200 dark:border-zinc-700 hover:border-indigo-300 hover:bg-zinc-50 dark:hover:bg-zinc-700/70'">
                                <input type="radio" name="answer_{{ $question->id }}" :value="{{ json_encode($option) }}"
                                    @change="selectAnswer({{ $question->id }}, {{ json_encode($option) }})"
                                    :checked="answeredQuestions[{{ $question->id }}] === {{ json_encode($option) }}"
                                    class="text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-zinc-800 dark:text-zinc-200 select-none">{{ $option }}</span>
                            </label>
                        @endforeach
                    </div>

                @elseif ($question->type === 'true_false')
                    <div class="space-y-2">
                        @foreach (['True', 'False'] as $option)
                            <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition"
                                :class="answeredQuestions[{{ $question->id }}] === '{{ $option }}'
                                    ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-950/30'
                                    : 'border-zinc-200 dark:border-zinc-700 hover:border-indigo-300 hover:bg-zinc-50 dark:hover:bg-zinc-700/70'">
                                <input type="radio" name="answer_{{ $question->id }}" value="{{ $option }}"
                                    @change="selectAnswer({{ $question->id }}, '{{ $option }}')"
                                    :checked="answeredQuestions[{{ $question->id }}] === '{{ $option }}'"
                                    class="text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-zinc-800 dark:text-zinc-200">{{ __($option) }}</span>
                            </label>
                        @endforeach
                    </div>

                @elseif ($question->type === 'fill_blank')
                    <input type="text" placeholder="{{ __('Type your answer...') }}"
                        :value="answeredQuestions[{{ $question->id }}] || ''"
                        @input.debounce.500ms="selectAnswer({{ $question->id }}, $event.target.value)"
                        class="w-full rounded-lg border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 bg-white dark:bg-white/10 shadow-xs px-3 py-2 text-sm text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-500">

                @elseif ($question->type === 'short_answer')
                    <div>
                        <textarea rows="3" placeholder="{{ __('Type your short answer...') }}"
                            @input.debounce.500ms="selectAnswer({{ $question->id }}, $event.target.value)"
                            class="w-full rounded-lg border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 bg-white dark:bg-white/10 shadow-xs px-3 py-2 text-sm text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-500"
                            x-text="answeredQuestions[{{ $question->id }}] || ''">{{ $answers[$question->id] ?? '' }}</textarea>
                        @if ($question->min_words || $question->max_words)
                            <p class="text-xs text-zinc-500 mt-1">
                                @if ($question->min_words && $question->max_words)
                                    {{ __(':min – :max words', ['min' => $question->min_words, 'max' => $question->max_words]) }}
                                @elseif ($question->min_words)
                                    {{ __('Minimum :min words', ['min' => $question->min_words]) }}
                                @else
                                    {{ __('Maximum :max words', ['max' => $question->max_words]) }}
                                @endif
                            </p>
                        @endif
                    </div>

                @elseif ($question->type === 'theory')
                    <div>
                        <textarea rows="8" placeholder="{{ __('Write your answer here...') }}"
                            @input.debounce.500ms="selectAnswer({{ $question->id }}, $event.target.value)"
                            class="w-full rounded-lg border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 bg-white dark:bg-white/10 shadow-xs px-3 py-2 text-sm text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-500"
                            x-text="answeredQuestions[{{ $question->id }}] || ''">{{ $answers[$question->id] ?? '' }}</textarea>
                        @if ($question->min_words || $question->max_words)
                            <p class="text-xs text-zinc-500 mt-1">
                                @if ($question->min_words && $question->max_words)
                                    {{ __(':min – :max words', ['min' => $question->min_words, 'max' => $question->max_words]) }}
                                @elseif ($question->min_words)
                                    {{ __('Minimum :min words', ['min' => $question->min_words]) }}
                                @else
                                    {{ __('Maximum :max words', ['max' => $question->max_words]) }}
                                @endif
                            </p>
                        @endif
                    </div>

                @elseif ($question->type === 'matching')
                    @php
                        $options = $question->options ?? [];
                        $leftItems = collect($options)->pluck('left')->toArray();
                        $rightItems = collect($options)->pluck('right')->shuffle()->toArray();
                    @endphp
                    <div x-data="matchingQuestion({{ $question->id }}, {{ json_encode($leftItems) }}, {{ json_encode($rightItems) }})"
                        class="space-y-3">
                        <p class="text-xs text-zinc-500">{{ __('Select an answer from the dropdown for each item.') }}</p>
                        @foreach ($leftItems as $i => $leftItem)
                            <div class="flex items-center gap-3">
                                <span class="flex-1 p-2 rounded bg-zinc-50 dark:bg-zinc-900 text-sm text-zinc-800 dark:text-zinc-200 select-none">
                                    {{ $leftItem }}
                                </span>
                                <span class="text-zinc-400">→</span>
                                <select @change="matchItem({{ $i }}, $event.target.value)"
                                    class="flex-1 rounded-lg border border-zinc-200 dark:border-white/10 bg-white dark:bg-white/10 shadow-xs px-2 py-1 text-sm text-zinc-700 dark:text-zinc-300">
                                    <option value="">{{ __('Select...') }}</option>
                                    @foreach ($rightItems as $rightItem)
                                        <option value="{{ $rightItem }}">{{ $rightItem }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endforeach

        {{-- Navigation buttons --}}
        <div class="flex items-center justify-between">
            <flux:button type="button" variant="subtle" @click="prev()" x-show="currentIndex > 0">
                <flux:icon name="arrow-left" class="size-4 mr-1" /> {{ __('Previous') }}
            </flux:button>
            <div x-show="currentIndex === 0"></div>

            <div class="flex items-center gap-2">
                <flux:button type="button" variant="subtle" @click="next()" x-show="currentIndex < totalQuestions - 1">
                    {{ __('Next') }} <flux:icon name="arrow-right" class="size-4 ml-1" />
                </flux:button>

                <div x-show="currentIndex === totalQuestions - 1 || answeredCount === totalQuestions">
                    <flux:modal.trigger name="submit-exam-confirm">
                        <flux:button type="button" variant="primary" icon="paper-airplane">
                            {{ __('Submit') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>

                <flux:modal name="submit-exam-confirm" class="md:w-[28rem]">
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Submit your :label?', ['label' => Str::lower($label)]) }}</flux:heading>
                            <flux:text class="mt-2">
                                <span x-show="answeredCount === totalQuestions">
                                    {{ __('You have answered all questions. Ready to submit?') }}
                                </span>
                                <span x-show="answeredCount < totalQuestions" class="text-amber-700 dark:text-amber-400">
                                    {{ __('You have') }}
                                    <span class="font-semibold" x-text="totalQuestions - answeredCount"></span>
                                    {{ __('unanswered question(s). Submit anyway?') }}
                                </span>
                            </flux:text>
                        </div>

                        {{-- Flagged questions warning --}}
                        <template x-if="flaggedCount > 0">
                            <div class="rounded-lg border border-orange-200 dark:border-orange-800 bg-orange-50 dark:bg-orange-950/20 p-3 text-sm text-orange-700 dark:text-orange-400">
                                <div class="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="size-4" fill="currentColor" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5" />
                                    </svg>
                                    <span>{{ __('You have') }} <span class="font-semibold" x-text="flaggedCount"></span> {{ __('flagged question(s).') }}</span>
                                </div>
                            </div>
                        </template>

                        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-900 p-4 text-sm space-y-2">
                            <div class="flex items-center justify-between text-zinc-700 dark:text-zinc-300">
                                <span class="flex items-center gap-2">
                                    <flux:icon name="check-circle" class="size-4 text-green-500" />
                                    {{ __('Answered') }}
                                </span>
                                <span class="font-semibold" x-text="answeredCount + ' / ' + totalQuestions"></span>
                            </div>
                            <div class="flex items-center justify-between text-zinc-700 dark:text-zinc-300" x-show="answeredCount < totalQuestions">
                                <span class="flex items-center gap-2">
                                    <flux:icon name="exclamation-triangle" class="size-4 text-amber-500" />
                                    {{ __('Unanswered') }}
                                </span>
                                <span class="font-semibold text-amber-700 dark:text-amber-400" x-text="totalQuestions - answeredCount"></span>
                            </div>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400 pt-2 border-t border-zinc-200 dark:border-zinc-700">
                                {{ __('You cannot change your answers after submitting.') }}
                            </p>
                        </div>

                        {{-- Hidden form — submit triggered programmatically to avoid beforeunload race --}}
                        <form method="POST" action="{{ route($routePrefix . '.submit', $attempt) }}" id="submitForm">
                            @csrf
                        </form>

                        <div class="flex justify-end gap-2">
                            <flux:modal.close>
                                <flux:button type="button" variant="ghost">{{ __('Keep Reviewing') }}</flux:button>
                            </flux:modal.close>
                            <flux:button type="button" variant="primary" icon="paper-airplane"
                                @click="submitExamFinal()" x-bind:disabled="submitting">
                                <span x-show="!submitting">{{ __('Submit Now') }}</span>
                                <span x-show="submitting">{{ __('Submitting...') }}</span>
                            </flux:button>
                        </div>
                    </div>
                </flux:modal>
            </div>
        </div>


        {{-- ⏱️ 1-minute review modal (auto-triggered once at 60s) --}}
        <template x-if="showOneMinuteModal">
            <div class="fixed inset-0 z-50 bg-zinc-900/70 flex items-center justify-center p-4">
                <div class="bg-white dark:bg-zinc-800 rounded-xl p-6 max-w-sm w-full space-y-4 shadow-2xl">
                    <div class="flex items-start gap-3">
                        <span class="text-3xl leading-none select-none" aria-hidden="true">⏱️</span>
                        <div>
                            <h2 class="text-base font-bold text-red-600 dark:text-red-400">{{ __('1 minute left!') }}</h2>
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ __('Time is almost up. Make sure you have answered all questions.') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200/60 dark:border-amber-700/40 px-4 py-2.5">
                        <span class="text-sm text-amber-800 dark:text-amber-300">{{ __('Unanswered questions') }}</span>
                        <span class="text-sm font-bold text-amber-700 dark:text-amber-400" x-text="totalQuestions - answeredCount"></span>
                    </div>
                    <div class="flex gap-2">
                        <button type="button"
                            @click="
                                showOneMinuteModal = false;
                                const firstUnansweredIdx = questions.findIndex(qId => !answeredQuestions[qId]);
                                if (firstUnansweredIdx !== -1) currentIndex = firstUnansweredIdx;
                            "
                            x-show="answeredCount < totalQuestions"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700 transition-colors">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
                            {{ __('Jump to first unanswered') }}
                        </button>
                        <button type="button" @click="showOneMinuteModal = false"
                            class="flex-1 inline-flex items-center justify-center rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 px-3 py-2 text-sm font-medium text-zinc-700 dark:text-zinc-200 hover:bg-zinc-50 dark:hover:bg-zinc-600 transition-colors">
                            {{ __('Dismiss') }}
                        </button>
                    </div>
                </div>
            </div>
        </template>

        {{-- 🔁 Tab-switch warning modal (professional design) --}}
        <template x-if="showTabWarning">
            <div class="fixed inset-0 z-50 bg-zinc-900/75 flex items-center justify-center p-4">
                <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-2xl max-w-md w-full overflow-hidden">
                    {{-- Coloured top bar --}}
                    <div class="h-1.5"
                         :class="{{ $exam->max_tab_switches ?? 'null' }} !== null && tabSwitches >= {{ $exam->max_tab_switches ?? 999 }} - 1
                             ? 'bg-red-500'
                             : 'bg-amber-400'"></div>
                    <div class="p-6 space-y-4">
                        <div class="flex items-start gap-4">
                            <div class="shrink-0 flex items-center justify-center w-10 h-10 rounded-full"
                                 :class="{{ $exam->max_tab_switches ?? 'null' }} !== null && tabSwitches >= {{ $exam->max_tab_switches ?? 999 }} - 1
                                     ? 'bg-red-100 dark:bg-red-900/30'
                                     : 'bg-amber-100 dark:bg-amber-900/30'">
                                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"
                                     :class="{{ $exam->max_tab_switches ?? 'null' }} !== null && tabSwitches >= {{ $exam->max_tab_switches ?? 999 }} - 1
                                         ? 'text-red-600 dark:text-red-400'
                                         : 'text-amber-600 dark:text-amber-400'"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-zinc-900 dark:text-white">{{ __('You left the :label tab', ['label' => Str::lower($label)]) }}</h2>
                                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ __('Switching away from this :label is monitored. Please stay focused.', ['label' => Str::lower($label)]) }}
                                </p>
                            </div>
                        </div>

                        @if ($exam->max_tab_switches)
                            <div class="rounded-lg border px-4 py-3"
                                 :class="tabSwitches >= {{ $exam->max_tab_switches }} - 1
                                     ? 'bg-red-50 dark:bg-red-900/20 border-red-200/60 dark:border-red-700/40'
                                     : 'bg-zinc-50 dark:bg-zinc-700/40 border-zinc-200 dark:border-zinc-600'">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-zinc-600 dark:text-zinc-400">{{ __('Tab switch count') }}</span>
                                    <span class="font-bold" :class="tabSwitches >= {{ $exam->max_tab_switches }} - 1 ? 'text-red-600 dark:text-red-400' : 'text-zinc-900 dark:text-white'">
                                        <span x-text="tabSwitches"></span> / {{ $exam->max_tab_switches }}
                                    </span>
                                </div>
                                <template x-if="tabSwitches >= {{ $exam->max_tab_switches }} - 1">
                                    <p class="mt-2 text-xs font-semibold text-red-600 dark:text-red-400">
                                        {{ __('Next violation will auto-submit your :label!', ['label' => Str::lower($label)]) }}
                                    </p>
                                </template>
                                <template x-if="tabSwitches < {{ $exam->max_tab_switches }} - 1">
                                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                        <span x-text="{{ $exam->max_tab_switches }} - tabSwitches"></span> {{ __('switch(es) remaining before auto-submit') }}
                                    </p>
                                </template>
                            </div>
                        @endif

                        <button type="button" @click="showTabWarning = false"
                            class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-colors">
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3"/></svg>
                            {{ __('Return to :label', ['label' => $label]) }}
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    @push('scripts')
    <script>
        function examTaker() {
            const initialAnswers = @json($answers);
            const totalQuestions = {{ $questions->count() }};
            const timeLimit = {{ $remainingSeconds ?? 'null' }};
            const maxTabSwitches = {{ $exam->max_tab_switches ?? 'null' }};
            const preventTabSwitch = {{ $exam->prevent_tab_switch ? 'true' : 'false' }};
            const preventCopyPaste = {{ $exam->prevent_copy_paste ? 'true' : 'false' }};
            const tabSwitchUrl = '{{ route($routePrefix . ".tab-switch", $attempt) }}';
            const csrfToken = '{{ csrf_token() }}';
            const attemptId = '{{ $attempt->id }}';
            const browserTabId = window.__schoolPortalExamTabId
                ?? (window.__schoolPortalExamTabId = window.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random()}`);

            const answeredQuestions = {};
            for (const [qId, answer] of Object.entries(initialAnswers)) {
                if (answer !== null && answer !== '') {
                    answeredQuestions[qId] = answer;
                }
            }

            // What the server has confirmed — anything in answeredQuestions that differs is unsaved
            const syncedAnswers = { ...answeredQuestions };

            // Restore any locally-saved draft (takes precedence over server state —
            // covers the case where the student was offline and lost connectivity
            // mid-exam, then refreshed or the browser recovered).
            const draftKey = 'exam_draft_' + attemptId;
            try {
                const saved = localStorage.getItem(draftKey);
                if (saved) {
                    const draft = JSON.parse(saved);
                    Object.assign(answeredQuestions, draft);
                }
            } catch (e) {}

            return {
                currentIndex: 0,
                totalQuestions,
                answeredQuestions,
                syncedAnswers,
                savingQuestions: {},
                saveRetrying: false,
                retryTimer: null,
                retryDelay: 2000,
                sessionExpired: false,
                flaggedQuestions: {},
                timeRemaining: timeLimit,
                timerInterval: null,
                tabSwitches: {{ $attempt->tab_switches ?? 0 }},
                showTabWarning: false,
                showFullscreenPrompt: preventTabSwitch,
                showTimerWarning: false,
                showOneMinuteModal: false,
                oneMinuteModalShown: false,
                questions: {{ $questions->pluck('id') }},
                offlineSubmitQueued: false,
                submitting: false,
                tabDetectionArmed: false,
                pageWasVisible: !document.hidden,
                tabDetectionTimer: null,

                get answeredCount() {
                    return Object.values(this.answeredQuestions).filter(a => a !== null && a !== '').length;
                },

                get flaggedCount() {
                    return Object.values(this.flaggedQuestions).filter(Boolean).length;
                },

                get unsavedQuestionIds() {
                    return Object.keys(this.answeredQuestions)
                        .filter(qId => this.answeredQuestions[qId] !== this.syncedAnswers[qId]);
                },

                get isSaving() {
                    return Object.values(this.savingQuestions).some(Boolean);
                },

                init() {
                    if (this.timeRemaining !== null) {
                        this.startTimer();
                    }

                    // --- Offline: auto-submit when connectivity returns ---
                    this._onlineHandler = () => {
                        if (this.offlineSubmitQueued) {
                            this.offlineSubmitQueued = false;
                            this._doFinalSubmit();
                            return;
                        }
                        this.flushUnsaved();
                    };
                    window.addEventListener('online', this._onlineHandler);

                    // Answers restored from the local draft that never reached the server (e.g. the
                    // page was reloaded during an outage) — send them now
                    this.flushUnsaved();

                    // --- Anti-cheat: Copy/Cut/Paste prevention ---
                    if (preventCopyPaste) {
                        this._clipboardHandler = (e) => e.preventDefault();
                        document.addEventListener('copy', this._clipboardHandler);
                        document.addEventListener('cut', this._clipboardHandler);
                        document.addEventListener('paste', this._clipboardHandler);
                    }

                    // --- Anti-cheat: Block dev tools & clipboard shortcuts ---
                    this._keydownHandler = (e) => {
                        // Block F12 (dev tools)
                        if (e.key === 'F12') {
                            e.preventDefault();
                            return;
                        }
                        // Block Ctrl+Shift+I / Ctrl+Shift+J / Ctrl+Shift+C (dev tools)
                        if (e.ctrlKey && e.shiftKey && ['I', 'J', 'C'].includes(e.key.toUpperCase())) {
                            e.preventDefault();
                            return;
                        }
                        // Block Ctrl+U (view source)
                        if (e.ctrlKey && e.key.toUpperCase() === 'U') {
                            e.preventDefault();
                            return;
                        }
                        // Block clipboard shortcuts only if prevent_copy_paste is on
                        if (preventCopyPaste) {
                            if (e.ctrlKey && ['C', 'V', 'X', 'A'].includes(e.key.toUpperCase())) {
                                e.preventDefault();
                                return;
                            }
                        }
                        // Block Ctrl+S (save page)
                        if (e.ctrlKey && e.key.toUpperCase() === 'S') {
                            e.preventDefault();
                            return;
                        }
                        // Block Ctrl+P (print)
                        if (e.ctrlKey && e.key.toUpperCase() === 'P') {
                            e.preventDefault();
                            return;
                        }
                    };
                    document.addEventListener('keydown', this._keydownHandler);

                    // --- Anti-cheat: Prevent accidental navigation ---
                    this._beforeUnloadHandler = (e) => {
                        if (this.submitting) return;

                        e.preventDefault();
                        e.returnValue = '';
                    };
                    window.addEventListener('beforeunload', this._beforeUnloadHandler);

                    // --- Anti-cheat: Multi-tab detection via BroadcastChannel ---
                    try {
                        this._bc = new BroadcastChannel('exam_attempt_' + attemptId);
                        // Announce this tab
                        this._bc.postMessage({ type: 'ping', tabId: browserTabId, ts: Date.now() });
                        this._bc.onmessage = (event) => {
                            if (event.data?.tabId === browserTabId) return;

                            if (event.data.type === 'ping') {
                                // Another tab opened the same exam — warn and count as tab switch
                                if (preventTabSwitch && maxTabSwitches !== null) {
                                    this.tabSwitches++;
                                    this.showTabWarning = true;
                                    this.notifyTabSwitch();
                                }
                                // Reply so the other tab knows we exist
                                this._bc.postMessage({ type: 'pong', tabId: browserTabId, ts: Date.now() });
                            }
                        };
                    } catch (e) {
                        // BroadcastChannel not supported — graceful degradation
                    }

                    // --- Anti-cheat: Detect right-click ---
                    this._contextMenuHandler = (e) => {
                        e.preventDefault();
                    };
                    document.addEventListener('contextmenu', this._contextMenuHandler);

                    // --- Anti-cheat: Detect print screen (best effort) ---
                    this._printScreenHandler = (e) => {
                        if (e.key === 'PrintScreen') {
                            if (preventCopyPaste) {
                                navigator.clipboard.writeText('').catch(() => {});
                            }
                        }
                    };
                    document.addEventListener('keyup', this._printScreenHandler);

                    this.armTabDetection();
                },

                startTimer() {
                    this.timerInterval = setInterval(() => {
                        this.timeRemaining--;
                        // 5-minute warning banner
                        if (this.timeRemaining <= 300 && !this.showTimerWarning) {
                            this.showTimerWarning = true;
                        }
                        // 1-minute modal (show once)
                        if (this.timeRemaining <= 60 && !this.oneMinuteModalShown) {
                            this.oneMinuteModalShown = true;
                            this.showOneMinuteModal = true;
                        }
                        if (this.timeRemaining <= 0) {
                            clearInterval(this.timerInterval);
                            this.showTimerWarning = false;
                            this.showOneMinuteModal = false;
                            this.forceSubmit();
                        }
                    }, 1000);
                },

                armTabDetection() {
                    clearTimeout(this.tabDetectionTimer);

                    if (document.hidden) return;

                    this.pageWasVisible = true;
                    this.tabDetectionTimer = setTimeout(() => {
                        if (!document.hidden) this.tabDetectionArmed = true;
                    }, 1200);
                },

                handleVisibilityChange() {
                    if (!preventTabSwitch || maxTabSwitches === null) return;

                    if (!this.tabDetectionArmed) {
                        this.pageWasVisible = !document.hidden;
                        if (!document.hidden) this.armTabDetection();
                        return;
                    }

                    if (!document.hidden) {
                        this.pageWasVisible = true;
                        return;
                    }

                    if (!this.pageWasVisible) return;

                    this.pageWasVisible = false;
                    this.tabSwitches++;
                    this.showTabWarning = true;
                    this.notifyTabSwitch();
                },

                destroy() {
                    clearInterval(this.timerInterval);
                    clearTimeout(this.tabDetectionTimer);
                    window.removeEventListener('online', this._onlineHandler);
                    window.removeEventListener('beforeunload', this._beforeUnloadHandler);
                    document.removeEventListener('keydown', this._keydownHandler);
                    document.removeEventListener('contextmenu', this._contextMenuHandler);
                    document.removeEventListener('keyup', this._printScreenHandler);

                    if (this._clipboardHandler) {
                        document.removeEventListener('copy', this._clipboardHandler);
                        document.removeEventListener('cut', this._clipboardHandler);
                        document.removeEventListener('paste', this._clipboardHandler);
                    }

                    this._bc?.close();
                },

                notifyTabSwitch() {
                    fetch(tabSwitchUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ tab_switches: this.tabSwitches }),
                    }).then(r => r.json()).then(data => {
                        if (data.auto_submitted) {
                            // Remove beforeunload so redirect works
                            window.removeEventListener('beforeunload', this._beforeUnloadHandler);
                            window.location.href = data.redirect;
                        }
                    }).catch(() => {});
                },

                toggleFlag(questionId) {
                    this.flaggedQuestions[questionId] = !this.flaggedQuestions[questionId];
                },

                selectAnswer(questionId, answer) {
                    this.answeredQuestions[questionId] = answer;
                    // Persist draft locally — survives a page reload if network drops
                    try { localStorage.setItem(draftKey, JSON.stringify(this.answeredQuestions)); } catch (e) {}
                    this.saveAnswerToServer(questionId);
                },

                // Sends the latest answer for one question. Failures (offline, server errors) are
                // retried with backoff until the server confirms, so no answer is silently dropped.
                async saveAnswerToServer(questionId) {
                    questionId = String(questionId);
                    if (this.savingQuestions[questionId] || this.submitting || this.sessionExpired) return;

                    const answer = this.answeredQuestions[questionId];
                    if (answer === this.syncedAnswers[questionId]) return;

                    if (!navigator.onLine) {
                        this.scheduleRetry();
                        return;
                    }

                    this.savingQuestions[questionId] = true;
                    let confirmed = false;

                    try {
                        const response = await fetch('{{ route($routePrefix . ".save-answer", $attempt) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({
                                question_id: questionId,
                                selected_answer: answer,
                            }),
                        });

                        if (response.status === 419 || response.status === 401) {
                            // Session ended — retrying can't succeed. The draft stays on this device
                            // and is re-sent automatically once the student logs back in.
                            this.sessionExpired = true;
                            return;
                        }

                        // 403/422: attempt closed or question invalid — retrying won't help
                        if (!response.ok && response.status !== 403 && response.status !== 422) {
                            throw new Error('Save failed with status ' + response.status);
                        }

                        const data = await response.json().catch(() => ({}));
                        if (data.status === 'timed_out' && data.redirect) {
                            this.submitting = true;
                            window.removeEventListener('beforeunload', this._beforeUnloadHandler);
                            window.location.href = data.redirect;
                            return;
                        }

                        this.syncedAnswers[questionId] = answer;
                        confirmed = true;
                        this.retryDelay = 2000;
                        if (this.unsavedQuestionIds.length === 0) this.saveRetrying = false;
                    } catch (e) {
                        this.scheduleRetry();
                    } finally {
                        this.savingQuestions[questionId] = false;
                    }

                    // The student changed this answer while the request was in flight
                    if (confirmed && this.answeredQuestions[questionId] !== this.syncedAnswers[questionId]) {
                        this.saveAnswerToServer(questionId);
                    }
                },

                scheduleRetry() {
                    this.saveRetrying = true;
                    if (this.retryTimer) return;

                    this.retryTimer = setTimeout(() => {
                        this.retryTimer = null;
                        this.flushUnsaved();
                    }, this.retryDelay);
                    this.retryDelay = Math.min(this.retryDelay * 2, 30000);
                },

                // One at a time, so a classroom reconnecting together doesn't flood the server
                async flushUnsaved() {
                    for (const questionId of this.unsavedQuestionIds) {
                        await this.saveAnswerToServer(questionId);
                        if (this.retryTimer || this.sessionExpired) return;
                    }
                },

                submitExamFinal() {
                    if (this.submitting) return;

                    if (!navigator.onLine) {
                        // Queue the submission — _onlineHandler will fire it when back online.
                        // Draft is already saved in localStorage from every selectAnswer().
                        this.offlineSubmitQueued = true;
                        return;
                    }
                    this._doFinalSubmit();
                },

                _doFinalSubmit() {
                    this.submitting = true;
                    // Remove the anti-cheat beforeunload guard FIRST (synchronously),
                    // then inject answers and call form.submit() directly.
                    // Using form.submit() (not a submit button click) means the browser
                    // never fires the 'submit' event, so no Livewire/Flux interception
                    // can race ahead and trigger the beforeunload dialog.
                    window.removeEventListener('beforeunload', this._beforeUnloadHandler);
                    clearTimeout(this.retryTimer);
                    // Keep the local draft until the results page confirms the submit worked —
                    // if the submit request fails, the answers are still here when the student returns
                    const form = document.getElementById('submitForm');
                    form.querySelectorAll('.injected-answer').forEach(el => el.remove());
                    for (const [questionId, answer] of Object.entries(this.answeredQuestions)) {
                        if (answer !== null && answer !== '') {
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'answers[' + questionId + ']';
                            input.value = answer;
                            input.className = 'injected-answer';
                            form.appendChild(input);
                        }
                    }
                    form.submit();
                },

                forceSubmit() {
                    this.submitExamFinal();
                },

                formatTime(seconds) {
                    if (seconds === null) return '';
                    const total = Math.max(0, Math.floor(seconds));
                    const h = Math.floor(total / 3600);
                    const m = Math.floor((total % 3600) / 60);
                    const s = total % 60;
                    if (h > 0) {
                        return String(h) + ':' + String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                    }
                    return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
                },

                goTo(index) { this.currentIndex = index; },
                prev() { if (this.currentIndex > 0) this.currentIndex--; },
                next() { if (this.currentIndex < this.totalQuestions - 1) this.currentIndex++; },

                enterFullscreen() {
                    this.showFullscreenPrompt = false;
                    const el = document.documentElement;
                    if (el.requestFullscreen) {
                        el.requestFullscreen().catch(() => {});
                    } else if (el.webkitRequestFullscreen) {
                        el.webkitRequestFullscreen();
                    } else if (el.msRequestFullscreen) {
                        el.msRequestFullscreen();
                    }
                },

                destroy() {
                    if (this.timerInterval) clearInterval(this.timerInterval);
                    if (this._bc) this._bc.close();
                    if (this._onlineHandler) window.removeEventListener('online', this._onlineHandler);
                },
            };
        }

        function matchingQuestion(questionId, leftItems, rightItems) {
            return {
                matches: {},
                matchItem(index, value) {
                    this.matches[index] = value;
                    // Serialize matches as JSON string for the parent answer
                    const serialized = JSON.stringify(this.matches);
                    const parent = this.$el.closest('[x-data*="examTaker"]');
                    if (parent && parent.__x) {
                        parent.__x.$data.selectAnswer(questionId, serialized);
                    } else {
                        // Fallback: use Alpine.$data
                        Alpine.evaluate(this.$el.closest('[x-data]'), `selectAnswer(${questionId}, '${serialized.replace(/'/g, "\\'")}')`);
                    }
                },
            };
        }
    </script>
    @endpush
</x-layouts::app>
