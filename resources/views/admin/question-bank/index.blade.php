@php
    // Shared by admins and teachers — each uses their own routes. Teachers may only change their own questions.
    $qbPrefix = auth()->user()->role === 'teacher' ? 'teacher' : 'admin';
    $isTeacher = $qbPrefix === 'teacher';
@endphp
<x-layouts::app :title="__('Question Bank')">
    <div
        class="space-y-6"
        x-data="{
            showCreate: false,
            showEdit: false,
            editQuestion: null,
            openEdit(q) {
                this.editQuestion = q;
                this.showEdit = true;
            },
            confirmDelete(id, name) {
                appConfirm({
                    title: @js(__('Remove this question?')),
                    message: @js(__('It will be removed from the question bank. Exams that already use it are not affected.')),
                    confirmLabel: @js(__('Remove question')),
                    variant: 'danger',
                }).then(ok => ok && document.getElementById('delete-form-' + id).submit());
            }
        }"
    >
        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('Question Bank') }}</h1>
                <p class="mt-1 text-sm text-zinc-500">
                    {{ __('Reusable questions you can import into any exam or assessment.') }}
                </p>
            </div>
            <flux:button variant="primary" icon="plus" @click="showCreate = true">{{ __('Add Question') }}</flux:button>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        {{-- Filters --}}
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <flux:input
                type="search"
                name="q"
                value="{{ request('q') }}"
                placeholder="{{ __('Search questions...') }}"
                class="w-64"
            />
            <flux:select name="subject_id" class="w-44">
                <option value="">{{ __('All Subjects') }}</option>
                @foreach ($subjects as $subject)
                    <option value="{{ $subject->id }}" @selected(request('subject_id') == $subject->id)>
                        {{ $subject->name }}
                    </option>
                @endforeach
            </flux:select>
            <flux:select name="type" class="w-40">
                <option value="">{{ __('All Types') }}</option>
                <option value="multiple_choice" @selected(request('type') === 'multiple_choice')>MCQ</option>
                <option value="true_false" @selected(request('type') === 'true_false')>True/False</option>
                <option value="fill_blank" @selected(request('type') === 'fill_blank')>Fill Blank</option>
                <option value="short_answer" @selected(request('type') === 'short_answer')>Short Answer</option>
                <option value="theory" @selected(request('type') === 'theory')>Theory/Essay</option>
            </flux:select>
            <flux:select name="difficulty" class="w-36">
                <option value="">{{ __('All Difficulties') }}</option>
                <option value="easy" @selected(request('difficulty') === 'easy')>Easy</option>
                <option value="medium" @selected(request('difficulty') === 'medium')>Medium</option>
                <option value="hard" @selected(request('difficulty') === 'hard')>Hard</option>
            </flux:select>
            <flux:button type="submit" variant="ghost" icon="magnifying-glass">{{ __('Filter') }}</flux:button>
            @if (request()->hasAny(['q', 'subject_id', 'type', 'difficulty']))
                <flux:button href="{{ route($qbPrefix.'.question-bank.index') }}" variant="ghost" icon="x-mark" wire:navigate>
                    {{ __('Clear') }}
                </flux:button>
            @endif
        </form>

        {{-- List --}}
        @if ($questions->isEmpty())
            <flux:card class="py-16 text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                    <flux:icon name="question-mark-circle" class="size-8 text-zinc-400" />
                </div>
                <p class="text-zinc-500">{{ __('No questions in the bank yet. Add your first question!') }}</p>
                <flux:button variant="primary" icon="plus" class="mt-4" @click="showCreate = true">
                    {{ __('Add Question') }}
                </flux:button>
            </flux:card>
        @else
            <div class="space-y-3">
                @foreach ($questions as $question)
                    <flux:card class="p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0 space-y-2">
                                {{-- Badges row --}}
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:badge size="sm" color="indigo">{{ $question->typeLabel() }}</flux:badge>
                                    <flux:badge size="sm" :color="$question->difficultyColor()">
                                        {{ ucfirst($question->difficulty) }}
                                    </flux:badge>
                                    @if ($question->subject)
                                        <flux:badge size="sm" color="zinc">{{ $question->subject->name }}</flux:badge>
                                    @endif
                                    <span class="text-xs text-zinc-400">
                                        {{ $question->points }} {{ Str::plural('pt', $question->points) }}
                                        &middot; {{ __('Used :n×', ['n' => $question->times_used]) }}
                                    </span>
                                </div>

                                {{-- Question text --}}
                                <p class="text-sm font-medium text-zinc-900 dark:text-zinc-100 line-clamp-2">
                                    {{ $question->question_text }}
                                </p>

                                {{-- Options preview for MCQ --}}
                                @if ($question->type === 'multiple_choice' && $question->options)
                                    <div class="flex flex-wrap gap-x-4 gap-y-1">
                                        @foreach ($question->options as $option)
                                            <span class="text-xs {{ $option === $question->correct_answer ? 'text-green-600 dark:text-green-400 font-semibold' : 'text-zinc-400' }}">
                                                @if ($option === $question->correct_answer)✓ @endif{{ $option }}
                                            </span>
                                        @endforeach
                                    </div>
                                @elseif ($question->type === 'true_false')
                                    <span class="text-xs text-green-600 dark:text-green-400 font-semibold">
                                        ✓ {{ $question->correct_answer }}
                                    </span>
                                @endif

                                {{-- Tags --}}
                                @if ($question->tags)
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($question->tags as $tag)
                                            <span class="rounded bg-zinc-100 dark:bg-zinc-700 px-2 py-0.5 text-xs text-zinc-500 dark:text-zinc-400">#{{ $tag }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            {{-- Actions --}}
                            @if (! $isTeacher || (int) $question->created_by === (int) auth()->id())
                            <div class="flex shrink-0 items-center gap-2">
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="pencil"
                                    @click="openEdit({{ Js::from([
                                        'id' => $question->id,
                                        'subject_id' => $question->subject_id,
                                        'class_id' => $question->class_id,
                                        'type' => $question->type,
                                        'question_text' => $question->question_text,
                                        'options' => $question->options ?? ['', '', '', ''],
                                        'correct_answer' => $question->correct_answer,
                                        'explanation' => $question->explanation,
                                        'marking_guide' => $question->marking_guide,
                                        'sample_answer' => $question->sample_answer,
                                        'points' => $question->points,
                                        'min_words' => $question->min_words,
                                        'max_words' => $question->max_words,
                                        'difficulty' => $question->difficulty,
                                        'tags' => $question->tags ? implode(', ', $question->tags) : '',
                                    ]) }})"
                                >{{ __('Edit') }}</flux:button>

                                <form id="delete-form-{{ $question->id }}" method="POST" action="{{ route($qbPrefix.'.question-bank.destroy', $question) }}" class="hidden">
                                    @csrf
                                    @method('DELETE')
                                </form>
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="trash"
                                    class="text-red-500"
                                    @click="confirmDelete({{ $question->id }}, '{{ addslashes(Str::limit($question->question_text, 30)) }}')"
                                />
                            </div>
                            @endif
                        </div>
                    </flux:card>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="mt-4">
                {{ $questions->links() }}
            </div>
        @endif

        {{-- ═══ Create Question Modal ═══ --}}
        <flux:modal wire:model="showCreate" :show="true" x-show="showCreate" @keydown.escape.window="showCreate = false" class="w-full max-w-2xl">
            <x-slot:heading>{{ __('Add Question to Bank') }}</x-slot:heading>

            <form
                method="POST"
                action="{{ route($qbPrefix.'.question-bank.store') }}"
                x-data="questionForm({})"
                class="space-y-4"
            >
                @csrf
                @include('admin.question-bank._form')
                <div class="flex justify-end gap-3 pt-2 border-t border-zinc-100 dark:border-zinc-700">
                    <flux:button variant="ghost" @click="showCreate = false" type="button">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">{{ __('Add to Bank') }}</flux:button>
                </div>
            </form>
        </flux:modal>

        {{-- ═══ Edit Question Modal ═══ --}}
        <flux:modal x-show="showEdit" @keydown.escape.window="showEdit = false" class="w-full max-w-2xl">
            <x-slot:heading>{{ __('Edit Question') }}</x-slot:heading>

            <template x-if="editQuestion">
                <form
                    method="POST"
                    :action="`{{ url('portal/'.$qbPrefix.'/question-bank') }}/` + editQuestion.id"
                    x-data="questionForm(editQuestion)"
                    class="space-y-4"
                >
                    @csrf
                    @method('PUT')
                    @include('admin.question-bank._form')
                    <div class="flex justify-end gap-3 pt-2 border-t border-zinc-100 dark:border-zinc-700">
                        <flux:button variant="ghost" @click="showEdit = false" type="button">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary">{{ __('Save Changes') }}</flux:button>
                    </div>
                </form>
            </template>
        </flux:modal>
    </div>

    @push('scripts')
    <script>
    function questionForm(q) {
        return {
            type: q.type || 'multiple_choice',
            options: q.options || ['', '', '', ''],
            correctAnswer: q.correct_answer || '',
            question_text: q.question_text || '',
            points: q.points || 1,
            difficulty: q.difficulty || 'medium',
            marking_guide: q.marking_guide || '',
            sample_answer: q.sample_answer || '',
            min_words: q.min_words || '',
            max_words: q.max_words || '',
            explanation: q.explanation || '',
            tags: q.tags || '',
            subject_id: q.subject_id || '',
            class_id: q.class_id || '',

            get isObjective() {
                return ['multiple_choice', 'true_false', 'fill_blank', 'matching'].includes(this.type);
            },
            get isTheory() {
                return ['short_answer', 'theory'].includes(this.type);
            },
            get isMCQ() { return this.type === 'multiple_choice'; },
            get isTrueFalse() { return this.type === 'true_false'; },

            addOption() { if (this.options.length < 6) this.options.push(''); },
            removeOption(i) {
                if (this.options.length > 2) {
                    if (this.correctAnswer === this.options[i]) this.correctAnswer = '';
                    this.options.splice(i, 1);
                }
            },
        };
    }
    </script>
    @endpush
</x-layouts::app>
