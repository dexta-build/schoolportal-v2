<x-layouts::app :title="$quiz->title">
    <div x-data="quizTaker()" class="space-y-4">
        {{-- Header with timer --}}
        <div class="sticky top-0 z-10 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-3 sm:p-4 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <h1 class="text-base sm:text-lg font-bold text-zinc-900 dark:text-white truncate">{{ $quiz->title }}</h1>
                    <p class="text-xs text-zinc-500">
                        {{ __('Question') }} <span x-text="currentIndex + 1"></span> {{ __('of') }} {{ $questions->count() }}
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
                            'bg-indigo-600 text-white': currentIndex === {{ $index }},
                            'bg-green-500 text-white': currentIndex !== {{ $index }} && answeredQuestions[{{ $question->id }}],
                            'bg-zinc-200 dark:bg-zinc-700 text-zinc-600 dark:text-zinc-400': currentIndex !== {{ $index }} && !answeredQuestions[{{ $question->id }}]
                        }"
                        class="size-7 rounded-full text-xs font-medium flex items-center justify-center transition">
                        {{ $index + 1 }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- 📶 Offline queued-submit banner --}}
        <div x-show="offlineSubmitQueued" x-cloak
             class="mt-3 flex items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 dark:bg-amber-950/40 dark:border-amber-800 px-4 py-3 text-sm text-amber-800 dark:text-amber-300"
             role="alert" aria-live="assertive">
            <svg class="size-5 shrink-0 animate-pulse" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.584 10.587a2 2 0 0 0 2.828 2.83m5.145 5.145A9.955 9.955 0 0 1 12 22C6.477 22 2 17.523 2 12c0-2.106.654-4.062 1.77-5.672m3.144-2.65A9.956 9.956 0 0 1 12 2c5.523 0 10 4.477 10 10 0 2.107-.655 4.063-1.77 5.673"/>
            </svg>
            <div>
                <p class="font-semibold">{{ __("You're offline — submission queued") }}</p>
                <p class="mt-0.5 text-xs opacity-80">{{ __('Your answers are saved on this device. The quiz will submit automatically when your connection is restored.') }}</p>
            </div>
        </div>

        {{-- Questions --}}
        @foreach ($questions as $index => $question)
            <div x-show="currentIndex === {{ $index }}" x-cloak
                class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 sm:p-6">

                <div class="mb-1">
                    @if ($question->type === 'multiple_choice')
                        <flux:badge color="indigo" size="sm">{{ __('Multiple Choice') }}</flux:badge>
                    @elseif ($question->type === 'true_false')
                        <flux:badge color="purple" size="sm">{{ __('True/False') }}</flux:badge>
                    @else
                        <flux:badge color="amber" size="sm">{{ __('Fill in the Blank') }}</flux:badge>
                    @endif
                </div>

                <p class="text-base sm:text-lg text-zinc-900 dark:text-white font-medium mb-4">{{ $question->question_text }}</p>

                @if ($question->type === 'multiple_choice' || $question->type === 'true_false')
                    @php
                        $options = $quiz->shuffle_options && $question->type === 'multiple_choice'
                            ? collect($question->options)->shuffle()
                            : collect($question->options);
                    @endphp
                    <div class="space-y-2">
                        @foreach ($options as $option)
                            <label class="flex items-center gap-3 p-3 rounded-lg border cursor-pointer transition"
                                :class="answeredQuestions[{{ $question->id }}] === '{{ addslashes($option) }}'
                                    ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-950/30'
                                    : 'border-zinc-200 dark:border-zinc-700 hover:border-indigo-300 hover:bg-zinc-50 dark:hover:bg-zinc-700/70'">
                                <input type="radio" name="answer_{{ $question->id }}" value="{{ $option }}"
                                    @change="selectAnswer({{ $question->id }}, '{{ addslashes($option) }}')"
                                    :checked="answeredQuestions[{{ $question->id }}] === '{{ addslashes($option) }}'"
                                    class="text-indigo-600 focus:ring-indigo-500">
                                <span class="text-sm text-zinc-800 dark:text-zinc-200">{{ $option }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    {{-- Fill in the blank --}}
                    <input type="text" placeholder="{{ __('Type your answer...') }}"
                        :value="answeredQuestions[{{ $question->id }}] || ''"
                        @input.debounce.500ms="selectAnswer({{ $question->id }}, $event.target.value)"
                        class="w-full rounded-lg border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 bg-white dark:bg-white/10 shadow-xs px-3 py-2 text-sm text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-500">
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
                    <flux:modal.trigger name="submit-quiz-confirm">
                        <flux:button type="button" variant="primary" icon="paper-airplane">
                            {{ __('Submit Quiz') }}
                        </flux:button>
                    </flux:modal.trigger>
                </div>

                <flux:modal name="submit-quiz-confirm" class="md:w-[28rem]">
                    <div class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Submit your quiz?') }}</flux:heading>
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

                        <form method="POST" action="{{ route('student.quizzes.submit', $attempt) }}" id="submitForm" @submit.prevent="handleSubmit()" class="flex justify-end gap-2">
                            @csrf
                            <flux:modal.close>
                                <flux:button type="button" variant="ghost">{{ __('Keep Reviewing') }}</flux:button>
                            </flux:modal.close>
                            <flux:button type="submit" variant="primary" icon="paper-airplane" x-bind:disabled="!isOnline">
                                <span x-show="isOnline">{{ __('Submit Now') }}</span>
                                <span x-show="!isOnline" class="flex items-center gap-1.5">
                                    <svg class="size-3.5 animate-pulse" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.584 10.587a2 2 0 0 0 2.828 2.83m5.145 5.145A9.955 9.955 0 0 1 12 22C6.477 22 2 17.523 2 12c0-2.106.654-4.062 1.77-5.672m3.144-2.65A9.956 9.956 0 0 1 12 2c5.523 0 10 4.477 10 10 0 2.107-.655 4.063-1.77 5.673"/></svg>
                                    {{ __('Offline') }}
                                </span>
                            </flux:button>
                        </form>
                    </div>
                </flux:modal>
            </div>
        </div>

    </div>

    @push('scripts')
    <script>
        function quizTaker() {
            const initialAnswers = @json($answers);
            const totalQuestions = {{ $questions->count() }};
            const timeLimit = {{ $remainingSeconds ?? 'null' }};
            const attemptId = '{{ $attempt->id }}';
            const draftKey = 'quiz_draft_' + attemptId;
            const saveUrl = '{{ route('student.quizzes.save-answer', $attempt) }}';
            const csrfToken = '{{ csrf_token() }}';

            // Convert answers object: {question_id: selected_answer}
            const answeredQuestions = {};
            for (const [qId, answer] of Object.entries(initialAnswers)) {
                if (answer !== null && answer !== '') {
                    answeredQuestions[qId] = answer;
                }
            }

            // What the server has confirmed — anything in answeredQuestions that differs is unsaved
            const syncedAnswers = { ...answeredQuestions };

            // Restore locally-saved draft (covers refresh after network drop)
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
                submitting: false,
                timeRemaining: timeLimit,
                timerInterval: null,
                offlineSubmitQueued: false,
                isOnline: navigator.onLine,

                get answeredCount() {
                    return Object.values(this.answeredQuestions).filter(a => a !== null && a !== '').length;
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

                    // Offline: track connectivity and auto-submit on reconnect
                    this._offlineHandler = () => { this.isOnline = false; };
                    this._onlineHandler = () => {
                        this.isOnline = true;
                        if (this.offlineSubmitQueued) {
                            this.offlineSubmitQueued = false;
                            this._doFinalSubmit();
                            return;
                        }
                        this.flushUnsaved();
                    };
                    window.addEventListener('offline', this._offlineHandler);
                    window.addEventListener('online', this._onlineHandler);

                    // Answers restored from the local draft that never reached the server — send them now
                    this.flushUnsaved();
                },

                startTimer() {
                    this.timerInterval = setInterval(() => {
                        this.timeRemaining--;
                        if (this.timeRemaining <= 0) {
                            clearInterval(this.timerInterval);
                            this.forceSubmit();
                        }
                    }, 1000);
                },

                injectAnswers() {
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
                },

                handleSubmit() {
                    if (!navigator.onLine) {
                        this.offlineSubmitQueued = true;
                        return;
                    }
                    this._doFinalSubmit();
                },

                forceSubmit() {
                    if (!navigator.onLine) {
                        this.offlineSubmitQueued = true;
                        return;
                    }
                    this._doFinalSubmit();
                },

                _doFinalSubmit() {
                    this.submitting = true;
                    clearTimeout(this.retryTimer);
                    // Keep the local draft until the results page confirms the submit worked —
                    // if the submit request fails, the answers are still here when the student returns
                    this.injectAnswers();
                    document.getElementById('submitForm').submit();
                },

                destroy() {
                    if (this.timerInterval) clearInterval(this.timerInterval);
                    if (this._offlineHandler) window.removeEventListener('offline', this._offlineHandler);
                    if (this._onlineHandler) window.removeEventListener('online', this._onlineHandler);
                },

                formatTime(seconds) {
                    if (seconds === null) return '';
                    const total = Math.max(0, Math.floor(seconds));
                    const m = Math.floor(total / 60);
                    const s = total % 60;
                    return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
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
                        const response = await fetch(saveUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ question_id: questionId, selected_answer: answer }),
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

                goTo(index) {
                    this.currentIndex = index;
                },

                prev() {
                    if (this.currentIndex > 0) this.currentIndex--;
                },

                next() {
                    if (this.currentIndex < this.totalQuestions - 1) this.currentIndex++;
                },

                destroy() {
                    if (this.timerInterval) clearInterval(this.timerInterval);
                },
            };
        }
    </script>
    @endpush
</x-layouts::app>
