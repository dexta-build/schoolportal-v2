<x-layouts::app :title="__('Create ' . $categoryLabel)">
    <div class="space-y-6">
        <x-admin-header
            :title="__('Create ' . $categoryLabel)"
            :description="__('Generate questions with AI or create them manually.')"
        >
            <x-page-help topic="cbt-create" />
        </x-admin-header>

        @if (session('error'))
            <flux:callout variant="danger" icon="x-circle">{{ session('error') }}</flux:callout>
        @endif

        {{-- Mode selector --}}
        <div x-data="{ mode: 'ai' }" class="space-y-6">
            <div class="flex gap-3">
                <flux:button x-on:click="mode = 'ai'" x-bind:class="mode === 'ai' ? '' : 'opacity-60'" variant="subtle" icon="sparkles">
                    {{ __('AI Generate') }}
                </flux:button>
                <flux:button x-on:click="mode = 'manual'" x-bind:class="mode === 'manual' ? '' : 'opacity-60'" variant="subtle" icon="pencil">
                    {{ __('Manual Create') }}
                </flux:button>
            </div>

            @if ($availableCredits !== null)
                <div class="text-sm text-zinc-500">
                    {{ __('AI credits available: :count', ['count' => $availableCredits]) }}
                    · {{ __('Manual creation is always free') }}
                </div>
            @endif

            {{-- AI Generation Form --}}
            <div x-show="mode === 'ai'" x-cloak>
                <flux:card>
                    <form method="POST" action="{{ route($routePrefix . '.generate', ['category' => $category]) }}" enctype="multipart/form-data" class="space-y-6" x-data="{ generating: false }" x-init="$nextTick(() => filterSubjectsForClass($el.querySelector('[name=class_id]'), $el.querySelector('[name=subject_id]')))" x-on:submit="if (generating) { $event.preventDefault(); return; } generating = true;">
                        @csrf
                        <input type="hidden" name="category" value="{{ $category ?? 'exam' }}">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <flux:select name="class_id" label="{{ __('Class') }}" required x-on:change="filterSubjectsForClass($event.target, $event.target.form.querySelector('[name=subject_id]'))">
                                <option value="">{{ __('Select class...') }}</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class->id }}" @selected(old('class_id') == $class->id)>{{ $class->name }} ({{ $class->level?->name }})</option>
                                @endforeach
                            </flux:select>

                            <div x-data="{
                                showAdd: false, saving: false, error: '', newName: '', existingSubject: null, existingStatus: '',
                                async addSubject(trigger) {
                                    this.saving = true; this.error = ''; this.existingSubject = null; this.existingStatus = '';
                                    try {
                                        const form = trigger.closest('form');
                                        const classId = form?.querySelector('[name=class_id]')?.value;
                                        if (!classId) {
                                            this.saving = false;
                                            this.error = @js(__('Select a class before adding the subject.'));
                                            return;
                                        }
                                        const r = await fetch('{{ route($routePrefix . '.store-subject') }}', {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                                            body: JSON.stringify({ name: this.newName.trim(), class_id: classId })
                                        });
                                        const data = await r.json();
                                        if (!r.ok && ['existing_unassigned', 'already_assigned'].includes(data.status)) {
                                            this.error = data.message;
                                            this.existingSubject = data.subject;
                                            this.existingStatus = data.status;
                                            this.saving = false;
                                            return;
                                        }
                                        if (!r.ok) { this.error = data.message || 'Validation failed'; this.saving = false; return; }
                                        if (data.error) { this.error = data.error; this.saving = false; return; }
                                        selectSubjectForClass(form, data.subject, classId);
                                        this.newName = ''; this.showAdd = false; this.saving = false; this.existingSubject = null;
                                    } catch (e) { this.saving = false; this.error = e.message || 'Failed to save.'; }
                                },
                                async assignExisting(trigger) {
                                    if (!this.existingSubject) return;
                                    this.saving = true;
                                    try {
                                        const form = trigger.closest('form');
                                        const classId = form?.querySelector('[name=class_id]')?.value;
                                        const r = await fetch('{{ route($routePrefix . '.store-subject') }}', {
                                            method: 'POST',
                                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                                            body: JSON.stringify({ name: this.existingSubject.name, subject_id: this.existingSubject.id, class_id: classId, assign_existing: true })
                                        });
                                        const data = await r.json();
                                        if (!r.ok) { this.error = data.message || 'Unable to assign subject'; this.saving = false; return; }
                                        selectSubjectForClass(form, data.subject, classId);
                                        this.newName = ''; this.error = ''; this.existingSubject = null; this.existingStatus = ''; this.showAdd = false; this.saving = false;
                                    } catch (e) { this.saving = false; this.error = e.message || 'Failed to assign subject.'; }
                                }
                            }">
                                <div class="flex items-end gap-2">
                                    <div class="flex-1">
                                        <flux:label>{{ __('Subject') }} *</flux:label>
                                        <flux:select name="subject_id" required x-ref="aiSubjectSelect">
                                            <option value="">{{ __('Select subject...') }}</option>
                                            @foreach ($subjects as $subject)
                                                <option value="{{ $subject->id }}" data-class-ids="{{ $subject->classes->pluck('id')->join(',') }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }}</option>
                                            @endforeach
                                        </flux:select>
                                    </div>
                                    <flux:button type="button" variant="subtle" size="sm" icon="plus" x-on:click="showAdd = !showAdd" aria-label="{{ __('Add Subject') }}" />
                                </div>
                                <div x-show="showAdd" x-cloak class="mt-2 p-3 border border-zinc-200 dark:border-zinc-700 rounded-lg space-y-2 bg-zinc-50 dark:bg-zinc-800">
                                    <flux:label>{{ __('New Subject Name') }}</flux:label>
                                    <flux:input type="text" x-model="newName" x-on:input="existingSubject = null; existingStatus = ''; error = ''" placeholder="{{ __('e.g., Mathematics') }}" />
                                    <div class="flex gap-2">
                                        <button type="button" class="inline-flex items-center px-2 py-1 text-xs font-medium rounded bg-indigo-600 text-white hover:bg-indigo-700 disabled:opacity-50" x-bind:disabled="saving || !newName.trim()" x-on:click="addSubject($el)">
                                            <span x-show="saving">{{ __('Saving...') }}</span>
                                            <span x-show="!saving">{{ __('Add') }}</span>
                                        </button>
                                        <button type="button" class="inline-flex items-center px-2 py-1 text-xs font-medium rounded text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-700" x-on:click="showAdd = false; newName = ''">{{ __('Cancel') }}</button>
                                    </div>
                                    <p x-show="error" x-text="error" class="text-xs text-red-500"></p>
                                    <button
                                        type="button"
                                        x-show="existingSubject"
                                        x-on:click="assignExisting($el)"
                                        x-bind:disabled="saving"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 disabled:opacity-50 dark:border-indigo-800 dark:bg-indigo-950/50 dark:text-indigo-300"
                                    >
                                        <span x-text="existingStatus === 'already_assigned' ? '{{ __('Use Existing Subject') }}' : '{{ __('Assign to Selected Class') }}'"></span>
                                    </button>
                                </div>
                            </div>

                            <flux:select name="score_component_id" label="{{ __('Score Component (optional)') }}">
                                <option value="">{{ __('None') }}</option>
                                @foreach ($scoreComponents as $comp)
                                    <option value="{{ $comp->id }}">{{ $comp->name }} ({{ $comp->short_name }}) — max {{ $comp->max_score }}</option>
                                @endforeach
                            </flux:select>
                        </div>

                        <div x-data="{ sourceType: 'file' }" class="space-y-4">
                            <div>
                                <flux:label>{{ __('Source Material') }}</flux:label>
                                <div class="flex gap-3 mt-1.5">
                                    <button type="button" x-on:click="sourceType = 'file'" class="px-3 py-1.5 text-sm font-medium rounded-md border transition-colors" x-bind:class="sourceType === 'file' ? 'bg-indigo-50 border-indigo-300 text-indigo-700 dark:bg-indigo-900/30 dark:border-indigo-600 dark:text-indigo-300' : 'border-zinc-200 text-zinc-600 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-400 dark:hover:bg-zinc-800'">
                                        {{ __('Upload File') }}
                                    </button>
                                    <button type="button" x-on:click="sourceType = 'link'" class="px-3 py-1.5 text-sm font-medium rounded-md border transition-colors" x-bind:class="sourceType === 'link' ? 'bg-indigo-50 border-indigo-300 text-indigo-700 dark:bg-indigo-900/30 dark:border-indigo-600 dark:text-indigo-300' : 'border-zinc-200 text-zinc-600 hover:bg-zinc-50 dark:border-zinc-600 dark:text-zinc-400 dark:hover:bg-zinc-800'">
                                        {{ __('Paste Link') }}
                                    </button>
                                </div>
                            </div>

                            <input type="hidden" name="source_type" x-bind:value="sourceType">

                            <div x-show="sourceType === 'file'">
                                <flux:input name="source_file" label="{{ __('Upload PDF or Word Document') }}" type="file" accept=".pdf,.doc,.docx" />
                                <p class="text-xs text-zinc-500 mt-1">{{ __('Accepted formats: PDF, DOC, DOCX. Max 10MB.') }}</p>
                            </div>
                            <div x-show="sourceType === 'link'" x-cloak>
                                <flux:input name="document_url" label="{{ __('Document Link') }}" type="url" placeholder="{{ __('https://docs.google.com/... or any document link') }}" />
                                <p class="text-xs text-zinc-500 mt-1">{{ __('Paste a link to a Google Doc, PDF, or any web page with the content.') }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <flux:input
                                name="question_count"
                                type="number"
                                min="1"
                                max="100"
                                step="1"
                                value="{{ old('question_count', 10) }}"
                                label="{{ __('Number of Questions') }}"
                                required
                            />

                            <flux:select name="difficulty" label="{{ __('Difficulty') }}" required>
                                <option value="easy">{{ __('Easy') }}</option>
                                <option value="medium" selected>{{ __('Medium') }}</option>
                                <option value="hard">{{ __('Hard') }}</option>
                            </flux:select>

                            <div>
                                <flux:label>{{ __('Question Types') }}</flux:label>
                                <div class="flex flex-wrap gap-3 mt-1.5">
                                    @foreach (['multiple_choice' => 'MCQ (Multiple Choice Question)', 'true_false' => 'True/False', 'fill_blank' => 'Fill Blank (Subjective)', 'short_answer' => 'Short Answer', 'theory' => 'Theory', 'matching' => 'Matching'] as $val => $label)
                                        <flux:checkbox name="question_types[]" value="{{ $val }}" label="{{ $label }}" :checked="$val === 'multiple_choice'" />
                                    @endforeach
                                </div>
                                @error('question_types')
                                    <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="flex flex-col items-end gap-2">
                            <button type="submit" x-bind:disabled="generating" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                <template x-if="!generating">
                                    <span class="inline-flex items-center gap-2">
                                        <x-flux::icon.sparkles class="size-5" />
                                        {{ __('Generate Questions') }}
                                    </span>
                                </template>
                                <template x-if="generating">
                                    <span class="inline-flex items-center gap-2">
                                        <svg class="animate-spin size-5" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        {{ __('Generating...') }}
                                    </span>
                                </template>
                            </button>
                            <p x-show="generating" x-cloak class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Kindly be patient, this may take between 15 to 30 seconds.') }}
                            </p>
                        </div>
                    </form>
                </flux:card>
            </div>

            {{-- Manual Creation Form --}}
            <div x-show="mode === 'manual'" x-cloak>
                @include('admin.exams._manual-form', [
                    'classes' => $classes,
                    'subjects' => $subjects,
                    'scoreComponents' => $scoreComponents,
                    'currentSession' => $currentSession,
                    'currentTerm' => $currentTerm,
                    'category' => $category,
                    'storeRoute' => route($routePrefix . '.store', ['category' => $category]),
                    'indexRoute' => route($routePrefix . '.index', $category ? ['category' => $category] : []),
                    'routePrefix' => $routePrefix,
                ])
            </div>
        </div>
    </div>
</x-layouts::app>
