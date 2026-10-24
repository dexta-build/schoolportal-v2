<x-layouts::app :title="__('Assignments')">
    <div class="space-y-6">
        <x-admin-header
            :title="__('Assignments')"
            :action="route('admin.assignments.create')"
            :actionLabel="__('Add Assignment')"
        />

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        <form method="GET" action="{{ route('admin.assignments.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <flux:select name="class_id" placeholder="{{ __('All Classes') }}">
                    <option value="">{{ __('All Classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:select name="term_id" placeholder="{{ __('All Terms') }}">
                    <option value="">{{ __('All Terms') }}</option>
                    @foreach ($terms as $term)
                        <option value="{{ $term->id }}" @selected(request('term_id') == $term->id)>{{ $term->name }}</option>
                    @endforeach
                </flux:select>
            </div>
            <flux:button type="submit" variant="filled" size="sm">{{ __('Filter') }}</flux:button>
            @if (request()->hasAny(['class_id', 'term_id']))
                <flux:button variant="subtle" size="sm" href="{{ route('admin.assignments.index') }}" wire:navigate>{{ __('Clear') }}</flux:button>
            @endif
        </form>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Title') }}</flux:table.column>
                <flux:table.column>{{ __('Class') }}</flux:table.column>
                <flux:table.column>{{ __('Week') }}</flux:table.column>
                <flux:table.column>{{ __('Term') }}</flux:table.column>
                <flux:table.column>{{ __('Due Date') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column class="w-14"><span class="sr-only">{{ __('Actions') }}</span></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($assignments as $assignment)
                    <flux:table.row>
                        <flux:table.cell class="font-medium">{{ $assignment->title ?? __('Week :num Assignment', ['num' => $assignment->week_number]) }}</flux:table.cell>
                        <flux:table.cell>{{ $assignment->class?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ __('Week :num', ['num' => $assignment->week_number]) }}</flux:table.cell>
                        <flux:table.cell>{{ $assignment->term?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $assignment->due_date?->format('M j, Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($assignment->status === 'approved')
                                <flux:badge color="green" size="sm">{{ __('Approved') }}</flux:badge>
                            @elseif ($assignment->status === 'pending')
                                <flux:badge color="yellow" size="sm">{{ __('Pending') }}</flux:badge>
                            @else
                                <flux:badge color="red" size="sm">{{ __('Rejected') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-end">
                            <x-row-actions :label="__('Actions for week :num assignment', ['num' => $assignment->week_number])">
                                @if ($assignment->file_url)
                                    <flux:menu.item icon="paper-clip" href="{{ $assignment->file_url }}" target="_blank" rel="noopener noreferrer">{{ __('Open file') }}</flux:menu.item>
                                @endif
                                <flux:menu.item icon="pencil-square" href="{{ route('admin.assignments.edit', $assignment) }}" wire:navigate>{{ __('Edit') }}</flux:menu.item>
                                <flux:menu.separator />
                                <x-row-actions.delete
                                    :action="route('admin.assignments.destroy', $assignment)"
                                    :title="__('Delete this assignment?')"
                                    :message="__('Students and parents will no longer see it. You can undo this straight after deleting, or restore it from Recently deleted within 30 days.')"
                                />
                            </x-row-actions>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-14 text-center">
                            <flux:icon.clipboard-document-list class="mx-auto w-10 h-10 text-zinc-300 dark:text-zinc-600" />
                            <p class="mt-3 text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('No assignments found.') }}</p>
                            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Assignments will appear here once uploaded for a class.') }}</p>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{ $assignments->links() }}
    </div>
</x-layouts::app>
