<x-layouts::app :title="__('Result Sheets (PDF)')">
    <div class="space-y-6">
        <x-admin-header
            :title="__('Result Sheets (PDF)')"
            :description="__('Result sheets prepared outside the portal and uploaded as PDF files. Report cards made from scores entered in the portal are under Report Cards.')"
            :action="route('admin.results.create')"
            :actionLabel="__('Upload Result')"
        >
            <flux:button variant="subtle" size="sm" href="{{ route('admin.results.bulk') }}" wire:navigate icon="arrow-up-tray">
                {{ __('Bulk Upload') }}
            </flux:button>
        </x-admin-header>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        <form method="GET" action="{{ route('admin.results.index') }}" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 max-w-sm">
                <flux:input name="search" :value="request('search')" placeholder="{{ __('Search student name...') }}" icon="magnifying-glass" aria-label="{{ __('Search results') }}" />
            </div>
            <div>
                <flux:select name="class_id" aria-label="{{ __('Filter by class') }}">
                    <option value="">{{ __('All Classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:select name="term_id" aria-label="{{ __('Filter by term') }}">
                    <option value="">{{ __('All Terms') }}</option>
                    @foreach ($terms as $term)
                        <option value="{{ $term->id }}" @selected(request('term_id') == $term->id)>{{ $term->name }}</option>
                    @endforeach
                </flux:select>
            </div>
            <flux:button type="submit" variant="filled" size="sm">{{ __('Filter') }}</flux:button>
            @if (request()->hasAny(['search', 'class_id', 'term_id']))
                <flux:button variant="subtle" size="sm" href="{{ route('admin.results.index') }}" wire:navigate>{{ __('Clear') }}</flux:button>
            @endif
        </form>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Student') }}</flux:table.column>
                <flux:table.column>{{ __('Class') }}</flux:table.column>
                <flux:table.column>{{ __('Session') }}</flux:table.column>
                <flux:table.column>{{ __('Term') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column>{{ __('Uploaded') }}</flux:table.column>
                <flux:table.column class="w-32" />
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($results as $result)
                    <flux:table.row>
                        <flux:table.cell class="font-medium">{{ $result->student?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $result->class?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $result->session?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $result->term?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($result->status === 'approved')
                                <flux:badge color="green" size="sm">{{ __('Approved') }}</flux:badge>
                            @elseif ($result->status === 'pending')
                                <flux:badge color="yellow" size="sm">{{ __('Pending') }}</flux:badge>
                            @else
                                <flux:badge color="red" size="sm">{{ __('Rejected') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $result->created_at->format('M j, Y') }}</flux:table.cell>
                        <flux:table.cell class="text-end">
                            <x-row-actions :label="__('Actions for :name\'s result sheet', ['name' => $result->student?->name ?? __('student')])">
                                <flux:menu.item icon="eye" href="{{ route('admin.results.show', $result) }}" wire:navigate>{{ __('View result sheet') }}</flux:menu.item>
                                <flux:menu.item icon="arrow-path" href="{{ route('admin.results.edit', $result) }}" wire:navigate>{{ __('Replace PDF') }}</flux:menu.item>
                                <flux:menu.separator />
                                <x-row-actions.delete
                                    :action="route('admin.results.destroy', $result)"
                                    :title="__('Delete this result sheet?')"
                                    :message="__('The student and parents will no longer see it. You can undo this straight after deleting, or restore it from Recently deleted within 30 days.')"
                                />
                            </x-row-actions>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center py-8">
                            {{ __('No results found.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{ $results->links() }}
    </div>
</x-layouts::app>
