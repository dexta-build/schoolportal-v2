<x-layouts::app :title="__('Students')">
    <div class="space-y-6">
        <x-admin-header
            :title="__('Students')"
            :action="route('admin.students.create')"
            :actionLabel="__('Add Student')"
        >
            <flux:button variant="subtle" size="sm" href="{{ route('admin.students.import') }}" wire:navigate icon="arrow-up-tray">
                {{ __('Import CSV') }}
            </flux:button>
            <flux:button variant="subtle" size="sm" icon="arrow-down-tray" href="{{ route('admin.students.export', request()->query()) }}">
                {{ __('Export CSV') }}
            </flux:button>
        </x-admin-header>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        @if (session('skipped_students'))
            <flux:callout variant="warning" icon="exclamation-triangle">
                <div>
                    <p class="font-medium mb-2">{{ __('The following students were skipped during import:') }}</p>
                    <ul class="list-disc list-inside text-sm space-y-1">
                        @foreach (session('skipped_students') as $skipped)
                            <li>{{ __('Row :line — :name (@:username): :reason', ['line' => $skipped['line'], 'name' => $skipped['name'], 'username' => $skipped['username'], 'reason' => $skipped['reason']]) }}</li>
                        @endforeach
                    </ul>
                </div>
            </flux:callout>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.students.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-48">
                <flux:input name="search" :value="request('search')" placeholder="{{ __('Search name or username...') }}" icon="magnifying-glass" aria-label="{{ __('Search students') }}" />
            </div>
            <flux:select name="class_id" class="min-w-40" aria-label="{{ __('Filter by class') }}">
                <option value="">{{ __('All Classes') }}</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->name }}</option>
                @endforeach
            </flux:select>
            <flux:select name="level_id" class="min-w-40" aria-label="{{ __('Filter by level') }}">
                <option value="">{{ __('All Levels') }}</option>
                @foreach ($levels as $level)
                    <option value="{{ $level->id }}" @selected(request('level_id') == $level->id)>{{ $level->name }}</option>
                @endforeach
            </flux:select>
            <flux:select name="status" class="min-w-40" aria-label="{{ __('Filter by status') }}">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
                <option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive') }}</option>
            </flux:select>
            <flux:button type="submit" variant="filled" size="sm">{{ __('Filter') }}</flux:button>
            @if (request()->hasAny(['search', 'class_id', 'level_id', 'status']))
                <flux:button variant="subtle" size="sm" href="{{ route('admin.students.index') }}" wire:navigate>{{ __('Clear') }}</flux:button>
            @endif
        </form>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Username') }}</flux:table.column>
                <flux:table.column>{{ __('Class') }}</flux:table.column>
                <flux:table.column>{{ __('Gender') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column class="w-32" />
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($students as $student)
                    <flux:table.row>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:avatar size="xs" :src="$student->avatarTableUrl()" :name="$student->name" />
                                <span class="font-medium">{{ $student->name }}</span>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $student->username }}</flux:table.cell>
                        <flux:table.cell>{{ $student->studentProfile?->class?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $student->gender ? ucfirst($student->gender) : '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($student->is_active)
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-end">
                            <x-row-actions :label="__('Actions for :name', ['name' => $student->name])">
                                <flux:menu.item icon="eye" href="{{ route('admin.students.show', $student) }}" wire:navigate>{{ __('View profile') }}</flux:menu.item>
                                <flux:menu.item icon="pencil-square" href="{{ route('admin.students.edit', $student) }}" wire:navigate>{{ __('Edit details') }}</flux:menu.item>
                                <flux:menu.separator />
                                @if ($student->is_active)
                                    <flux:menu.item icon="pause-circle" variant="danger" x-on:click="$flux.modal('deactivate-student-{{ $student->id }}').show()">{{ __('Deactivate (block login)') }}</flux:menu.item>
                                @else
                                    <x-row-actions.form :action="route('admin.students.activate', $student)" icon="play-circle">{{ __('Activate (allow login)') }}</x-row-actions.form>
                                @endif
                            </x-row-actions>

                            {{-- Deactivate modal --}}
                            @if ($student->is_active)
                                <flux:modal :name="'deactivate-student-' . $student->id" class="max-w-md">
                                    <form method="POST" action="{{ route('admin.students.deactivate', $student) }}" class="space-y-4">
                                        @csrf
                                        <div>
                                            <flux:heading size="lg">{{ __('Deactivate :name', ['name' => $student->name]) }}</flux:heading>
                                            <flux:text class="mt-1">{{ __('This student will not be able to log in until you reactivate their account.') }}</flux:text>
                                        </div>
                                        <flux:textarea
                                            name="deactivation_reason"
                                            :label="__('Reason for deactivation')"
                                            :placeholder="__('e.g. Student transferred to another school...')"
                                            required
                                            rows="3"
                                        />
                                        <div class="flex justify-end gap-2">
                                            <flux:modal.close>
                                                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                            </flux:modal.close>
                                            <flux:button type="submit" variant="danger">{{ __('Deactivate') }}</flux:button>
                                        </div>
                                    </form>
                                </flux:modal>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center py-8">
                            {{ __('No students found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{ $students->links() }}
    </div>
</x-layouts::app>
