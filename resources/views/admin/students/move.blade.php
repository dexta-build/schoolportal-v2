
<x-layouts::app :title="__('Change Class (Same Session)')">
    <div class="space-y-6">
        <x-admin-header :title="__('Change Class (Same Session)')" :description="__('Move a student to another class in the same level during the current session — for example from JSS 1A to JSS 1B.')" />

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        @if (session('error'))
            <flux:callout variant="danger" icon="x-circle">{{ session('error') }}</flux:callout>
        @endif

        @if ($errors->any())
            <flux:callout variant="danger" icon="x-circle">
                <ul class="list-disc list-inside space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </flux:callout>
        @endif

        {{-- Step 1: Select Level and Class (GET cascade) --}}
        <flux:card class="space-y-4">
            <div>
                <h2 class="text-base font-semibold text-zinc-800 dark:text-zinc-100">{{ __('Step 1 — Select Level & Class') }}</h2>
                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">{{ __('Choose a level and class to see the students in that class.') }}</p>
            </div>

            <form method="GET" action="{{ route('admin.students.move') }}" class="flex flex-wrap items-end gap-3">
                <div class="min-w-48">
                    <flux:select
                        name="level_id"
                        label="{{ __('Level') }}"
                        aria-label="{{ __('Select level') }}"
                        onchange="this.form.submit()"
                    >
                        <option value="">{{ __('Select Level') }}</option>
                        @foreach ($levels as $level)
                            <option value="{{ $level->id }}" @selected($selectedLevel == $level->id)>{{ $level->name }}</option>
                        @endforeach
                    </flux:select>
                </div>

                @if ($selectedLevel)
                    <div class="min-w-48">
                        <flux:select
                            name="class_id"
                            label="{{ __('Class') }}"
                            aria-label="{{ __('Select class') }}"
                            onchange="this.form.submit()"
                        >
                            <option value="">{{ __('Select Class') }}</option>
                            @foreach ($classes as $class)
                                <option value="{{ $class->id }}" @selected($selectedClass == $class->id)>{{ $class->name }}</option>
                            @endforeach
                        </flux:select>
                    </div>
                @endif
            </form>
        </flux:card>

        {{-- Step 2 & 3: Pick Students and Target Class --}}
        @if ($selectedClass)
            @if ($students->isEmpty())
                <flux:callout variant="warning" icon="information-circle">
                    {{ __('No students found in this class.') }}
                </flux:callout>
            @else
                <form
                    method="POST"
                    action="{{ route('admin.students.move.process') }}"
                    x-data="{
                        search: '',
                        selectedIds: [],
                        allStudents: {{ Js::from($students->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'username' => $s->username, 'gender' => $s->gender])) }},
                        get filteredStudents() {
                            if (!this.search.trim()) return this.allStudents;
                            const q = this.search.toLowerCase();
                            return this.allStudents.filter(s =>
                                s.name.toLowerCase().includes(q) || s.username.toLowerCase().includes(q)
                            );
                        },
                        toggleAll(checked) {
                            this.selectedIds = checked ? this.filteredStudents.map(s => s.id) : [];
                        },
                        get allFilteredSelected() {
                            return this.filteredStudents.length > 0 &&
                                this.filteredStudents.every(s => this.selectedIds.includes(s.id));
                        }
                    }"
                >
                    @csrf
                    <input type="hidden" name="level_id" value="{{ $selectedLevel }}">
                    <input type="hidden" name="class_id" value="{{ $selectedClass }}">

                    {{-- Hidden inputs for selected IDs (Alpine-managed) --}}
                    <template x-for="id in selectedIds" :key="id">
                        <input type="hidden" name="student_ids[]" :value="id">
                    </template>

                    <div class="space-y-4">
                        {{-- Student Picker --}}
                        <flux:card class="space-y-4">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <h2 class="text-base font-semibold text-zinc-800 dark:text-zinc-100">{{ __('Step 2 — Select Students') }}</h2>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">
                                        {{ __(':count student(s) in this class.', ['count' => $students->count()]) }}
                                    </p>
                                </div>
                                <div class="w-full sm:w-64">
                                    <flux:input
                                        x-model="search"
                                        placeholder="{{ __('Search students...') }}"
                                        icon="magnifying-glass"
                                        aria-label="{{ __('Search students') }}"
                                    />
                                </div>
                            </div>

                            {{-- Select All --}}
                            <div class="flex items-center gap-2 pb-2 border-b border-zinc-200 dark:border-zinc-700">
                                <input
                                    type="checkbox"
                                    id="select-all"
                                    class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                                    :checked="allFilteredSelected"
                                    @change="toggleAll($event.target.checked)"
                                >
                                <label for="select-all" class="text-sm font-medium text-zinc-700 dark:text-zinc-300 cursor-pointer select-none">
                                    {{ __('Select All') }}
                                    <span class="text-zinc-400 font-normal" x-show="selectedIds.length > 0">
                                        (<span x-text="selectedIds.length"></span>&nbsp;{{ __('selected') }})
                                    </span>
                                </label>
                            </div>

                            {{-- Student List --}}
                            <div class="space-y-0.5 max-h-72 overflow-y-auto pr-1">
                                <template x-for="student in filteredStudents" :key="student.id">
                                    <label class="flex items-center gap-3 px-2 py-2 rounded-lg hover:bg-zinc-50 dark:hover:bg-zinc-800 cursor-pointer transition-colors">
                                        <input
                                            type="checkbox"
                                            class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                                            :value="student.id"
                                            :checked="selectedIds.includes(student.id)"
                                            @change="
                                                if ($event.target.checked) {
                                                    selectedIds = [...selectedIds, student.id];
                                                } else {
                                                    selectedIds = selectedIds.filter(id => id !== student.id);
                                                }
                                            "
                                        >
                                        <div class="flex-1 min-w-0">
                                            <span class="text-sm font-medium text-zinc-800 dark:text-zinc-100" x-text="student.name"></span>
                                            <span class="ml-1.5 text-xs text-zinc-400" x-text="'@' + student.username"></span>
                                        </div>
                                        <span
                                            class="text-xs px-1.5 py-0.5 rounded-full"
                                            :class="student.gender === 'male'
                                                ? 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400'
                                                : 'bg-pink-50 text-pink-600 dark:bg-pink-900/30 dark:text-pink-400'"
                                            x-text="student.gender ? student.gender.charAt(0).toUpperCase() + student.gender.slice(1) : ''"
                                            x-show="student.gender"
                                        ></span>
                                    </label>
                                </template>

                                <p x-show="filteredStudents.length === 0" class="text-sm text-zinc-400 text-center py-6">
                                    {{ __('No students match your search.') }}
                                </p>
                            </div>
                        </flux:card>

                        {{-- Target Class --}}
                        <flux:card class="space-y-4">
                            <div>
                                <h2 class="text-base font-semibold text-zinc-800 dark:text-zinc-100">{{ __('Step 3 — Choose Destination Class') }}</h2>
                                <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-0.5">{{ __('Only classes within the same level are shown. Students and parents will be notified.') }}</p>
                            </div>

                            <div class="max-w-xs">
                                <flux:select
                                    name="target_class_id"
                                    label="{{ __('Move to Class') }}"
                                    aria-label="{{ __('Select target class') }}"
                                    required
                                >
                                    <option value="">{{ __('Select Target Class') }}</option>
                                    @foreach ($classes as $class)
                                        @if ($class->id != $selectedClass)
                                            <option value="{{ $class->id }}">{{ $class->name }}</option>
                                        @endif
                                    @endforeach
                                </flux:select>
                            </div>

                            <div class="flex items-center gap-3 pt-1">
                                <flux:modal.trigger name="confirm-move">
                                    <flux:button
                                        type="button"
                                        variant="primary"
                                        icon="arrows-right-left"
                                        x-bind:disabled="selectedIds.length === 0"
                                    >
                                        {{ __('Move Selected Students') }}
                                    </flux:button>
                                </flux:modal.trigger>

                                <p class="text-xs text-zinc-400" x-show="selectedIds.length === 0">
                                    {{ __('Select at least one student first.') }}
                                </p>
                            </div>
                        </flux:card>
                    </div>

                    {{-- Confirmation Modal --}}
                    <flux:modal name="confirm-move" class="max-w-md">
                        <div class="space-y-4">
                            <div>
                                <flux:heading size="lg">{{ __('Confirm Move') }}</flux:heading>
                                <flux:text class="mt-1">
                                    {{ __('You are about to move') }}
                                    <strong x-text="selectedIds.length"></strong>
                                    {{ __('student(s) to the selected class.') }}
                                </flux:text>
                            </div>

                            <flux:callout variant="warning" icon="information-circle" class="text-sm">
                                {{ __('Students and their parents will receive a notification about this change.') }}
                            </flux:callout>

                            <div class="flex justify-end gap-2">
                                <flux:modal.close>
                                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                </flux:modal.close>
                                <flux:button
                                    type="button"
                                    variant="primary"
                                    icon="arrows-right-left"
                                    x-on:click="$el.closest('form').submit()"
                                >
                                    {{ __('Yes, Move Them') }}
                                </flux:button>
                            </div>
                        </div>
                    </flux:modal>
                </form>
            @endif
        @endif
    </div>
</x-layouts::app>
