<x-layouts::app :title="__('Reset CBT Access')">
    <div class="mx-auto max-w-5xl space-y-6" x-data="{
        scope: @js(old('scope', 'students')),
        search: '',
        confirming: false,
        selected: @js(collect(old('student_ids', []))->map(fn ($id) => (string) $id)->values()->all()),
        students: @js($studentOptions),
        get filteredStudents() {
            const term = this.search.trim().toLowerCase();
            return term === '' ? this.students : this.students.filter(student =>
                `${student.name} ${student.username} ${student.status}`.toLowerCase().includes(term)
            );
        },
        get allVisibleSelected() {
            return this.filteredStudents.length > 0 && this.filteredStudents.every(student => this.selected.includes(String(student.id)));
        },
        toggleVisible() {
            const visibleIds = this.filteredStudents.map(student => String(student.id));
            this.selected = this.allVisibleSelected
                ? this.selected.filter(id => !visibleIds.includes(id))
                : [...new Set([...this.selected, ...visibleIds])];
        }
    }">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <flux:button href="{{ route($routePrefix . '.show', $exam) }}" variant="ghost" size="sm" icon="arrow-left" wire:navigate>
                    {{ __('Back to CBT') }}
                </flux:button>
                <h1 class="mt-3 text-xl font-bold text-zinc-900 dark:text-white">{{ __('Reset CBT Access') }}</h1>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $exam->title }} · {{ $exam->subject?->name }} · {{ $exam->class?->name }}
                </p>
            </div>
            <flux:badge color="green" size="sm">{{ __('Approved and published') }}</flux:badge>
        </div>

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800/70 dark:bg-red-950/50 dark:text-red-100">
                <p class="font-semibold">{{ __('Please review the reset details.') }}</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (session('success'))
            <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/60 dark:text-emerald-100">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div role="alert" class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-800 dark:border-red-800/60 dark:bg-red-950/60 dark:text-red-100">
                {{ session('error') }}
            </div>
        @endif

        {{-- Continue an existing attempt (network/IP lock) --}}
        @if ($resumableAttempts->isNotEmpty())
            <section class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Students with an attempt in progress') }}</h2>
                    <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('An attempt is locked to the network it started on. If a student\'s connection changed and they can\'t get back in, allow them to resume — they keep their saved answers and remaining time.') }}
                    </p>
                </div>
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ($resumableAttempts as $resumable)
                        <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-zinc-900 dark:text-white">{{ $resumable['name'] }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $resumable['username'] }} · {{ __('Started') }} {{ $resumable['started_at'] }}
                                    @if ($resumable['remaining_minutes'] !== null)
                                        · {{ trans_choice(':count minute left|:count minutes left', $resumable['remaining_minutes'], ['count' => $resumable['remaining_minutes']]) }}
                                    @endif
                                </p>
                            </div>
                            <form method="POST" action="{{ route($routePrefix . '.allow-resume', $exam) }}">
                                @csrf
                                <input type="hidden" name="attempt_id" value="{{ $resumable['id'] }}">
                                <flux:button type="submit" size="sm" icon="lock-open">{{ __('Allow resume') }}</flux:button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-800/70 dark:bg-amber-950/40 dark:text-amber-100">
            <div class="flex items-start gap-3">
                <flux:icon name="information-circle" class="mt-0.5 size-5 shrink-0" />
                <p>{{ __('Resetting access preserves previous attempts and results. Any current attempt for the selected students will be closed, and each student will receive one fresh attempt on this same CBT.') }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route($routePrefix . '.reset-access.store', $exam) }}" class="space-y-6">
            @csrf

            <section class="space-y-3">
                <div>
                    <h2 class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Who should receive fresh access?') }}</h2>
                    <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Choose the full class or select individual students.') }}</p>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition"
                           :class="scope === 'class' ? 'border-indigo-500 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-950/40' : 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800'">
                        <input type="radio" name="scope" value="class" x-model="scope" class="mt-1 size-4">
                        <span>
                            <span class="block font-semibold text-zinc-900 dark:text-white">{{ __('Entire class') }}</span>
                            <span class="mt-0.5 block text-sm text-zinc-500 dark:text-zinc-400">{{ __('All :count active students in :class', ['count' => $studentOptions->count(), 'class' => $exam->class?->name]) }}</span>
                        </span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border p-4 transition"
                           :class="scope === 'students' ? 'border-indigo-500 bg-indigo-50 dark:border-indigo-400 dark:bg-indigo-950/40' : 'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800'">
                        <input type="radio" name="scope" value="students" x-model="scope" class="mt-1 size-4">
                        <span>
                            <span class="block font-semibold text-zinc-900 dark:text-white">{{ __('Selected students') }}</span>
                            <span class="mt-0.5 block text-sm text-zinc-500 dark:text-zinc-400" x-text="selected.length ? `${selected.length} selected` : '{{ __('Search and select one or more students') }}'"></span>
                        </span>
                    </label>
                </div>
            </section>

            <section x-show="scope === 'students'" x-cloak class="space-y-3">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <div class="relative flex-1">
                        <flux:icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                        <input type="search" x-model="search" placeholder="{{ __('Search students by name, username, or status') }}"
                               class="min-h-11 w-full rounded-lg border border-zinc-300 bg-white pl-10 pr-3 text-sm text-zinc-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white">
                    </div>
                    <button type="button" x-on:click="toggleVisible()" class="min-h-11 rounded-lg border border-zinc-300 px-4 text-sm font-medium text-zinc-700 hover:bg-zinc-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800">
                        <span x-text="allVisibleSelected ? '{{ __('Clear visible') }}' : '{{ __('Select visible') }}'"></span>
                    </button>
                </div>

                <div class="max-h-[28rem] overflow-y-auto rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                    <template x-for="student in filteredStudents" :key="student.id">
                        <label class="flex min-h-16 cursor-pointer items-center gap-3 border-b border-zinc-100 px-3 py-3 last:border-b-0 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/70">
                            <input type="checkbox" name="student_ids[]" :value="student.id" x-model="selected" class="size-4 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-zinc-900 dark:text-white" x-text="student.name"></span>
                                <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400" x-text="student.username"></span>
                            </span>
                            <span class="shrink-0 text-right">
                                <span class="block text-xs font-medium"
                                      :class="{
                                          'text-red-600 dark:text-red-400': student.status_color === 'red',
                                          'text-amber-600 dark:text-amber-400': student.status_color === 'amber',
                                          'text-green-600 dark:text-green-400': student.status_color === 'green',
                                          'text-zinc-500 dark:text-zinc-400': student.status_color === 'zinc'
                                      }"
                                      x-text="student.status"></span>
                                <span class="mt-0.5 block text-[11px] text-zinc-400" x-text="student.active_until ? `Access until ${student.active_until}` : `${student.attempts} attempt(s)`"></span>
                            </span>
                        </label>
                    </template>
                    <div x-show="filteredStudents.length === 0" class="px-4 py-10 text-center text-sm text-zinc-500">
                        {{ __('No students match your search.') }}
                    </div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2">
                <flux:input
                    type="datetime-local"
                    name="available_until"
                    label="{{ __('New closing date and time') }}"
                    value="{{ old('available_until', now()->addDay()->format('Y-m-d\TH:i')) }}"
                    min="{{ now()->addMinute()->format('Y-m-d\TH:i') }}"
                    required
                />
                <flux:textarea
                    name="reason"
                    label="{{ __('Reason (optional)') }}"
                    placeholder="{{ __('For example: approved absence or connectivity issue') }}"
                    rows="3"
                >{{ old('reason') }}</flux:textarea>
            </section>

            <div class="flex flex-col-reverse gap-3 border-t border-zinc-200 pt-5 dark:border-zinc-700 sm:flex-row sm:items-center sm:justify-end">
                <flux:button href="{{ route($routePrefix . '.show', $exam) }}" variant="ghost" wire:navigate>{{ __('Cancel') }}</flux:button>
                <flux:button type="button" variant="primary" icon="arrow-path" x-on:click="confirming = true" x-bind:disabled="scope === 'students' && selected.length === 0">
                    <span x-text="scope === 'class' ? '{{ __('Reset access for class') }}' : `{{ __('Reset access') }} (${selected.length})`"></span>
                </flux:button>
            </div>

            <div x-show="confirming" x-cloak x-transition.opacity
                 class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-3 sm:items-center"
                 x-on:click.self="confirming = false"
                 x-on:keydown.escape.window="confirming = false">
                <div class="w-full max-w-md rounded-lg border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-start gap-3">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">
                            <flux:icon name="arrow-path" class="size-5" />
                        </span>
                        <div>
                            <h2 class="font-semibold text-zinc-900 dark:text-white">{{ __('Confirm CBT access reset') }}</h2>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400"
                               x-text="scope === 'class'
                                   ? '{{ __('This will close any current attempts and grant one fresh attempt to the entire class.') }}'
                                   : `{{ __('This will close any current attempts and grant one fresh attempt to') }} ${selected.length} {{ __('selected student(s).') }}`"></p>
                        </div>
                    </div>
                    <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <flux:button type="button" variant="ghost" x-on:click="confirming = false">{{ __('Cancel') }}</flux:button>
                        <flux:button type="submit" variant="primary" icon="arrow-path">{{ __('Confirm reset') }}</flux:button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</x-layouts::app>
