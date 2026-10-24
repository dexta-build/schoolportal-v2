<x-layouts::app :title="__('Promote Students (New Session)')">
    <div class="space-y-6">
        <x-admin-header :title="__('Promote Students (New Session)')" :description="__('At the start of a new session, move students up to their next class or level.')" />

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        {{-- Promotion Form --}}
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
            <flux:heading size="sm" class="mb-4">{{ __('Bulk Promotion') }}</flux:heading>
            <p class="text-sm text-zinc-500 mb-4">{{ __('Promote all students from one class to another. You can deselect individual students on the preview page.') }}</p>
            <form method="POST" action="{{ route('admin.promotions.preview') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="mode" value="bulk">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <flux:select name="from_class_id" :label="__('From Class')" required>
                        <option value="">{{ __('Select source class...') }}</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}">{{ $class->name }} ({{ $class->level?->name }}) — {{ $class->students_count }} {{ __('students') }}</option>
                        @endforeach
                    </flux:select>

                    <flux:select name="to_class_id" :label="__('To Class')" required>
                        <option value="">{{ __('Select destination class...') }}</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}">{{ $class->name }} ({{ $class->level?->name }})</option>
                        @endforeach
                    </flux:select>

                    <flux:select name="to_session_id" :label="__('For Session')" required>
                        <option value="">{{ __('Select session...') }}</option>
                        @foreach ($sessions as $session)
                            <option value="{{ $session->id }}">{{ $session->name }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:button type="submit" variant="primary" size="sm">{{ __('Preview Promotion') }}</flux:button>
            </form>
        </div>

        {{-- Single Student Promotion --}}
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6"
             x-data="{
                 search: '',
                 students: {{ Js::from($allStudents ?? []) }},
                 get filtered() {
                     if (!this.search || this.search.length < 2) return [];
                     const q = this.search.toLowerCase();
                     return this.students.filter(s =>
                         s.name.toLowerCase().includes(q) || s.username.toLowerCase().includes(q)
                     ).slice(0, 10);
                 },
                 selectedStudent: null,
                 selectStudent(s) { this.selectedStudent = s; this.search = ''; }
             }">
            <flux:heading size="sm" class="mb-4">{{ __('Single Student Promotion') }}</flux:heading>
            <p class="text-sm text-zinc-500 mb-4">{{ __('Search for a specific student to promote individually. Use this for double/triple promotions (skipping classes).') }}</p>

            <div class="space-y-4">
                {{-- Search --}}
                <div class="relative max-w-md">
                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Find Student') }}</label>
                    <input type="text" x-model="search" placeholder="{{ __('Type student name or username...') }}"
                           class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-800 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]" />
                    <div x-show="search.length >= 2 && !selectedStudent" x-cloak class="absolute z-10 mt-1 w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 shadow-lg max-h-60 overflow-y-auto">
                        <template x-for="s in filtered" :key="s.id">
                            <button type="button" @click="selectStudent(s)" class="w-full text-left px-3 py-2 hover:bg-zinc-100 dark:hover:bg-zinc-700 flex items-center gap-2 text-sm">
                                <flux:icon.user class="w-4 h-4 text-zinc-400 shrink-0" />
                                <div class="flex-1 min-w-0">
                                    <span x-text="s.name" class="font-medium"></span>
                                    <span x-text="'@' + s.username" class="text-zinc-400 text-xs ml-1"></span>
                                </div>
                                <span x-text="s.class_name" class="text-xs text-zinc-500 shrink-0"></span>
                            </button>
                        </template>
                        <div x-show="filtered.length === 0" class="px-3 py-4 text-center text-sm text-zinc-500">
                            <flux:icon.exclamation-circle class="w-5 h-5 mx-auto mb-1 text-zinc-400" />
                            {{ __('No student found matching your search.') }}
                        </div>
                    </div>
                </div>

                {{-- Selected student + promotion form --}}
                <div x-show="selectedStudent" x-cloak>
                    <div class="rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-4 mb-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="font-medium" x-text="selectedStudent?.name"></span>
                                <span class="text-sm text-zinc-500 ml-1" x-text="'@' + selectedStudent?.username"></span>
                                <div class="text-sm text-zinc-500 mt-1">
                                    {{ __('Current Class:') }} <span class="font-medium text-zinc-700 dark:text-zinc-300" x-text="selectedStudent?.class_name"></span>
                                </div>
                            </div>
                            <button type="button" @click="selectedStudent = null" class="text-zinc-400 hover:text-red-500 text-lg">&times;</button>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('admin.promotions.store') }}" class="space-y-4"
                          x-data="{ showConfirm: false }"
                          @submit.prevent="showConfirm = true">
                        @csrf
                        <input type="hidden" name="student_ids[]" :value="selectedStudent?.id">
                        <input type="hidden" name="from_class_id" :value="selectedStudent?.class_id">
                        <input type="hidden" name="from_session_id" value="{{ app('current.school')->currentSession()?->id }}">

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 max-w-lg">
                            <flux:select name="to_class_id" :label="__('Promote To Class')" required>
                                <option value="">{{ __('Select destination...') }}</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class->id }}">{{ $class->name }} ({{ $class->level?->name }})</option>
                                @endforeach
                            </flux:select>

                            <flux:select name="to_session_id" :label="__('For Session')" required>
                                <option value="">{{ __('Select session...') }}</option>
                                @foreach ($sessions as $session)
                                    <option value="{{ $session->id }}">{{ $session->name }}</option>
                                @endforeach
                            </flux:select>
                        </div>

                        <flux:button variant="primary" type="button" size="sm" @click="showConfirm = true">{{ __('Promote Student') }}</flux:button>

                        {{-- Confirmation dialog --}}
                        <template x-teleport="body">
                            <div x-show="showConfirm" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-900/50">
                                <div @click.outside="showConfirm = false" class="bg-white dark:bg-zinc-800 rounded-xl p-6 max-w-md w-full shadow-2xl space-y-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex-shrink-0 size-10 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                                            <flux:icon name="exclamation-triangle" class="size-5 text-amber-600" />
                                        </div>
                                        <div>
                                            <h3 class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Confirm Promotion') }}</h3>
                                            <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ __('Are you sure you want to promote this student? This action cannot be easily undone.') }}</p>
                                        </div>
                                    </div>
                                    <div class="flex justify-end gap-2">
                                        <flux:button variant="ghost" type="button" @click="showConfirm = false">{{ __('Cancel') }}</flux:button>
                                        <flux:button variant="primary" type="button" @click="showConfirm = false; $el.closest('form').removeAttribute('x-on:submit.prevent'); $nextTick(() => $el.closest('form').submit())">{{ __('Yes, Promote') }}</flux:button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </form>
                </div>
            </div>
        </div>

        {{-- Recent Promotions --}}
        @if ($recentPromotions->count())
            <div>
                <flux:heading size="sm" class="mb-3">{{ __('Recent Promotions') }}</flux:heading>
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Student') }}</flux:table.column>
                        <flux:table.column>{{ __('From') }}</flux:table.column>
                        <flux:table.column>{{ __('To') }}</flux:table.column>
                        <flux:table.column>{{ __('Promoted By') }}</flux:table.column>
                        <flux:table.column>{{ __('Date') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($recentPromotions as $promotion)
                            <flux:table.row>
                                <flux:table.cell class="font-medium">{{ $promotion->student?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $promotion->fromClass?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $promotion->toClass?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell class="text-zinc-500">{{ $promotion->promoter?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell class="text-zinc-500">{{ $promotion->promoted_at->format('M j, Y') }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>

                @if ($recentPromotions->hasPages())
                    <div class="mt-4">{{ $recentPromotions->links() }}</div>
                @endif
            </div>
        @endif
    </div>
</x-layouts::app>
