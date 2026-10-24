<x-layouts::app :title="__('Subjects')">
    <div class="space-y-6">
        <x-admin-header
            :title="__('Subjects')"
            :description="__('Manage academic subjects and assign them to classes.')"
            :action="route('admin.subjects.create')"
            :actionLabel="__('Add Subject')"
        >
            <flux:button variant="subtle" icon="squares-2x2" href="{{ route('admin.subjects.assignments') }}" wire:navigate>
                {{ __('Assign to Class') }}
            </flux:button>
        </x-admin-header>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif
        @if (session('error'))
            <flux:callout variant="danger" icon="x-circle">{{ session('error') }}</flux:callout>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Subject') }}</flux:table.column>
                <flux:table.column>{{ __('Short Name') }}</flux:table.column>
                <flux:table.column>{{ __('Category') }}</flux:table.column>
                <flux:table.column>{{ __('Classes') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column class="w-32" />
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($subjects as $subject)
                    <flux:table.row>
                        <flux:table.cell class="font-medium">{{ $subject->name }}</flux:table.cell>
                        <flux:table.cell>{{ $subject->short_name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($subject->category)
                                <flux:badge color="zinc" size="sm">{{ $subject->category }}</flux:badge>
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $subject->classes_count }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($subject->is_active)
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-end">
                            <x-row-actions :label="__('Actions for :name', ['name' => $subject->name])">
                                <flux:menu.item icon="pencil-square" href="{{ route('admin.subjects.edit', $subject) }}" wire:navigate>{{ __('Edit subject') }}</flux:menu.item>
                                <flux:menu.separator />
                                <x-row-actions.delete
                                    :action="route('admin.subjects.destroy', $subject)"
                                    :title="__('Delete :name?', ['name' => $subject->name])"
                                    :message="__('It will be removed from its assigned classes. Subjects with CBT or score history are protected and will not be deleted.')"
                                />
                            </x-row-actions>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center py-8">
                            <div class="flex flex-col items-center gap-2">
                                <flux:icon name="book-open" class="size-8 text-zinc-400" />
                                <p class="text-zinc-500">{{ __('No subjects yet. Add your first subject to get started.') }}</p>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{ $subjects->links() }}
    </div>
</x-layouts::app>
