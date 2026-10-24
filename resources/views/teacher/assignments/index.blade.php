<x-layouts::app :title="__('My Assignments')">
    <div class="space-y-6">
        <x-admin-header
            :title="__('My Assignments')"
            :action="route('teacher.assignments.create')"
            :actionLabel="__('Upload Assignment')"
            actionIcon="arrow-up-tray"
        />

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        @if (session('error'))
            <flux:callout variant="danger" icon="exclamation-circle">{{ session('error') }}</flux:callout>
        @endif

        <form method="GET" action="{{ route('teacher.assignments.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <flux:select name="class_id">
                    <option value="">{{ __('All Classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:select name="status">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="pending" @selected(request('status') === 'pending')>{{ __('Pending') }}</option>
                    <option value="approved" @selected(request('status') === 'approved')>{{ __('Approved') }}</option>
                    <option value="rejected" @selected(request('status') === 'rejected')>{{ __('Rejected') }}</option>
                </flux:select>
            </div>
            <flux:button type="submit" variant="filled" size="sm">{{ __('Filter') }}</flux:button>
            @if (request()->hasAny(['class_id', 'status']))
                <flux:button variant="subtle" size="sm" href="{{ route('teacher.assignments.index') }}" wire:navigate>{{ __('Clear') }}</flux:button>
            @endif
        </form>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Title') }}</flux:table.column>
                <flux:table.column class="hidden sm:table-cell">{{ __('Class') }}</flux:table.column>
                <flux:table.column>{{ __('Week') }}</flux:table.column>
                <flux:table.column class="hidden md:table-cell">{{ __('Due Date') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column class="w-20" />
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($assignments as $assignment)
                    <flux:table.row>
                        <flux:table.cell class="font-medium">
                            {{ $assignment->title ?: __('Week :week Assignment', ['week' => $assignment->week_number]) }}
                        </flux:table.cell>
                        <flux:table.cell class="hidden sm:table-cell">{{ $assignment->class?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $assignment->week_number }}</flux:table.cell>
                        <flux:table.cell class="hidden md:table-cell text-zinc-500">
                            {{ $assignment->due_date?->format('M j, Y') ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($assignment->status === 'approved')
                                <flux:badge color="green" size="sm">{{ __('Approved') }}</flux:badge>
                            @elseif ($assignment->status === 'pending')
                                <flux:badge color="yellow" size="sm">{{ __('Pending') }}</flux:badge>
                            @else
                                <flux:badge color="red" size="sm">{{ __('Rejected') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($assignment->status !== 'approved')
                                <flux:button variant="subtle" size="xs" icon="pencil-square" href="{{ route('teacher.assignments.edit', $assignment) }}" wire:navigate>{{ __('Edit') }}</flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center py-8">
                            <flux:icon.clipboard-document-list class="w-8 h-8 mx-auto text-zinc-300 dark:text-zinc-600" />
                            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No assignments uploaded yet. Create your first assignment for your class.') }}</p>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{ $assignments->links() }}
    </div>
</x-layouts::app>
