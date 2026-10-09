<x-layouts::app :title="__('Edit Quiz')">
    <div class="space-y-6">
        <div>
            <flux:button variant="subtle" size="sm" href="{{ route('teacher.quizzes.index') }}" wire:navigate class="mb-2">
                <flux:icon name="arrow-left" class="size-4 mr-1" /> {{ __('Back to Quizzes') }}
            </flux:button>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('Edit Quiz') }}</h1>
        </div>

        @if ($errors->any())
            <flux:callout variant="danger" icon="x-circle">
                {{ __('Please fix the errors below.') }}
            </flux:callout>
        @endif

        <form method="POST" action="{{ route('teacher.quizzes.update', $quiz) }}" x-data="{ ...quizEditor(), submitting: false }" @submit="submitting = true">
            @csrf
            @method('PUT')

            {{-- Quiz Settings --}}
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 sm:p-5 mb-4">
                <h3 class="text-base font-semibold text-zinc-900 dark:text-white mb-4">{{ __('Quiz Settings') }}</h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <flux:input name="title" label="{{ __('Quiz Title') }}" required :value="old('title', $quiz->title)" />
                        @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <flux:select name="class_id" label="{{ __('Class') }}" required>
                            @foreach ($classes as $class)
                                <option value="{{ $class->id }}" @selected(old('class_id', $quiz->class_id) == $class->id)>{{ $class->name }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>

                <div class="mt-4">
                    <flux:textarea name="description" label="{{ __('Description (optional)') }}" rows="2">{{ old('description', $quiz->description) }}</flux:textarea>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-4">
                    <div>
                        <flux:input type="number" name="time_limit_minutes" label="{{ __('Time Limit (min)') }}"
                            :value="old('time_limit_minutes', $quiz->time_limit_minutes)" placeholder="{{ __('No limit') }}" min="1" max="180" />
                    </div>
                    <div>
                        <flux:input type="number" name="passing_score" label="{{ __('Passing Score (%)') }}"
                            :value="old('passing_score', $quiz->passing_score)" required min="1" max="100" />
                    </div>
                    <div>
                        <flux:input type="number" name="max_attempts" label="{{ __('Max Attempts') }}"
                            :value="old('max_attempts', $quiz->max_attempts)" required min="1" max="10" />
                    </div>
                </div>

                <div class="flex flex-wrap gap-4 mt-4">
                    <label class="inline-flex items-center gap-2">
                        <input type="hidden" name="shuffle_questions" value="0">
                        <input type="checkbox" name="shuffle_questions" value="1" @checked(old('shuffle_questions', $quiz->shuffle_questions)) class="rounded border-zinc-300">
                        <span class="text-sm">{{ __('Shuffle questions') }}</span>
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="hidden" name="shuffle_options" value="0">
                        <input type="checkbox" name="shuffle_options" value="1" @checked(old('shuffle_options', $quiz->shuffle_options)) class="rounded border-zinc-300">
                        <span class="text-sm">{{ __('Shuffle options') }}</span>
                    </label>
                    <label class="inline-flex items-center gap-2">
                        <input type="hidden" name="show_correct_answers" value="0">
                        <input type="checkbox" name="show_correct_answers" value="1" @checked(old('show_correct_answers', $quiz->show_correct_answers)) class="rounded border-zinc-300">
                        <span class="text-sm">{{ __('Show answers after submission') }}</span>
                    </label>
                </div>
            </div>

            {{-- Questions --}}
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4 sm:p-5 mb-4">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">
                        {{ __('Questions') }} (<span x-text="questions.length"></span>)
                    </h3>
                    <flux:button type="button" variant="subtle" size="sm" @click="addQuestion()">
                        <flux:icon name="plus" class="size-4 mr-1" /> {{ __('Add Question') }}
                    </flux:button>
                </div>

                <template x-for="(question, qIndex) in questions" :key="qIndex">
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-4 mb-4">
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium text-zinc-600 dark:text-zinc-400" x-text="'Q' + (qIndex + 1)"></span>
                                <select :name="'questions[' + qIndex + '][type]'" x-model="question.type" @change="onTypeChange(qIndex)"
                                    class="rounded-lg border border-zinc-200 dark:border-white/10 bg-white dark:bg-white/10 text-zinc-700 dark:text-zinc-300 shadow-xs text-xs py-1 px-2">
                                    <option value="multiple_choice">{{ __('Multiple Choice') }}</option>
                                    <option value="true_false">{{ __('True / False') }}</option>
                                    <option value="fill_blank">{{ __('Fill in Blank') }}</option>
                                </select>
                            </div>
                            <button type="button" @click="removeQuestion(qIndex)" class="text-red-500 hover:text-red-700" x-show="questions.length > 1">
                                <flux:icon name="trash" class="size-4" />
                            </button>
                        </div>

                        <div class="space-y-3">
                            <textarea :name="'questions[' + qIndex + '][question_text]'" x-model="question.question_text" rows="2"
                                class="w-full rounded-lg border border-zinc-200 border-b-zinc-300/80 dark:border-white/10 bg-white dark:bg-white/10 shadow-xs px-3 py-2 text-sm text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-500" required></textarea>

                            <template x-if="question.type === 'multiple_choice'">
                                <div class="space-y-2">
                                    <template x-for="(opt, oIndex) in question.options" :key="oIndex">
                                        <div class="flex items-center gap-2">
                                            <input type="radio" :name="'correct_' + qIndex" :value="oIndex"
                                                :checked="question.correct_answer === question.options[oIndex]"
                                                @change="question.correct_answer = question.options[oIndex]"
                                                class="text-indigo-600 focus:ring-indigo-500">
                                            <input type="text" x-model="question.options[oIndex]"
                                                :name="'questions[' + qIndex + '][options][' + oIndex + ']'"
                                                class="flex-1 rounded-lg border border-zinc-200 dark:border-white/10 bg-white dark:bg-white/10 shadow-xs px-2 py-1 text-sm text-zinc-700 dark:text-zinc-300" required>
                                        </div>
                                    </template>
                                    <input type="hidden" :name="'questions[' + qIndex + '][correct_answer]'" :value="question.correct_answer">
                                </div>
                            </template>

                            <template x-if="question.type === 'true_false'">
                                <div>
                                    <input type="hidden" :name="'questions[' + qIndex + '][options][0]'" value="True">
                                    <input type="hidden" :name="'questions[' + qIndex + '][options][1]'" value="False">
                                    <div class="flex items-center gap-4">
                                        <label class="inline-flex items-center gap-2">
                                            <input type="radio" :name="'correct_' + qIndex" value="True"
                                                :checked="question.correct_answer === 'True'"
                                                @change="question.correct_answer = 'True'" class="text-indigo-600">
                                            <span class="text-sm">{{ __('True') }}</span>
                                        </label>
                                        <label class="inline-flex items-center gap-2">
                                            <input type="radio" :name="'correct_' + qIndex" value="False"
                                                :checked="question.correct_answer === 'False'"
                                                @change="question.correct_answer = 'False'" class="text-indigo-600">
                                            <span class="text-sm">{{ __('False') }}</span>
                                        </label>
                                    </div>
                                    <input type="hidden" :name="'questions[' + qIndex + '][correct_answer]'" :value="question.correct_answer">
                                </div>
                            </template>

                            <template x-if="question.type === 'fill_blank'">
                                <div>
                                    <input type="hidden" :name="'questions[' + qIndex + '][options][0]'" value="">
                                    <input type="text" :name="'questions[' + qIndex + '][correct_answer]'" x-model="question.correct_answer"
                                        class="w-full rounded-lg border border-zinc-200 dark:border-white/10 bg-white dark:bg-white/10 shadow-xs px-2 py-1 text-sm text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-500"
                                        placeholder="{{ __('Correct answer...') }}" required>
                                </div>
                            </template>

                            <textarea :name="'questions[' + qIndex + '][explanation]'" x-model="question.explanation" rows="1"
                                class="w-full rounded-lg border border-zinc-200 dark:border-white/10 bg-white dark:bg-white/10 shadow-xs px-3 py-2 text-sm text-zinc-700 dark:text-zinc-300 placeholder-zinc-400 dark:placeholder-zinc-500"
                                placeholder="{{ __('Explanation (optional)') }}"></textarea>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary" x-bind:disabled="submitting">
                    <span x-show="!submitting">{{ __('Update & Resubmit for Approval') }}</span>
                    <span x-show="submitting" x-cloak class="inline-flex items-center gap-2">
                        <flux:icon name="arrow-path" class="size-4 animate-spin" />
                        {{ __('Saving...') }}
                    </span>
                </flux:button>
                <flux:button variant="subtle" href="{{ route('teacher.quizzes.index') }}" wire:navigate>{{ __('Cancel') }}</flux:button>
            </div>
        </form>
    </div>

    @php
        $existingQuestions = $quiz->questions->map(fn ($q) => [
            'type' => $q->type,
            'question_text' => $q->question_text,
            'options' => $q->options ?? ['', '', '', ''],
            'correct_answer' => $q->correct_answer ?? '',
            'explanation' => $q->explanation ?? '',
        ])->values();
    @endphp

    @push('scripts')
    <script>
        function quizEditor() {
            const existingQuestions = @json($existingQuestions);

            return {
                questions: existingQuestions.length > 0 ? existingQuestions : [{
                    type: 'multiple_choice',
                    question_text: '',
                    options: ['', '', '', ''],
                    correct_answer: '',
                    explanation: '',
                }],
                addQuestion() {
                    this.questions.push({
                        type: 'multiple_choice',
                        question_text: '',
                        options: ['', '', '', ''],
                        correct_answer: '',
                        explanation: '',
                    });
                },
                removeQuestion(index) {
                    this.questions.splice(index, 1);
                },
                onTypeChange(index) {
                    const q = this.questions[index];
                    if (q.type === 'true_false') {
                        q.options = ['True', 'False'];
                        q.correct_answer = 'True';
                    } else if (q.type === 'fill_blank') {
                        q.options = [''];
                        q.correct_answer = '';
                    } else {
                        q.options = ['', '', '', ''];
                        q.correct_answer = '';
                    }
                },
            };
        }
    </script>
    @endpush
</x-layouts::app>
