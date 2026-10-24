<x-layouts::app :title="__('Classes')">
    <div class="space-y-6">
        <x-admin-header
            :title="__('Classes')"
            :description="__('Manage classes and assign teachers.')"
            :action="route('admin.classes.create')"
            :actionLabel="__('Add Class')"
        />

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif
        @if (session('error'))
            <flux:callout variant="danger" icon="x-circle">{{ session('error') }}</flux:callout>
        @endif

        <div x-data="{ selected: [], bulkOpen: false, deleting: false }">
            <div class="mb-3 flex justify-end">
                <flux:button type="button" variant="danger" size="sm" icon="trash" x-bind:disabled="selected.length === 0" x-on:click="bulkOpen = true">
                    {{ __('Delete Selected') }}
                </flux:button>
            </div>

            <flux:table>
                <flux:table.columns>
                    <flux:table.column class="w-10" />
                    <flux:table.column>{{ __('Class') }}</flux:table.column>
                    <flux:table.column>{{ __('Level') }}</flux:table.column>
                    <flux:table.column>{{ __('Teacher') }}</flux:table.column>
                    <flux:table.column>{{ __('Students') }}</flux:table.column>
                    <flux:table.column>{{ __('Capacity') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column class="w-32" />
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($classes as $class)
                        <flux:table.row>
                            <flux:table.cell>
                                <input
                                    type="checkbox"
                                    value="{{ $class->id }}"
                                    x-model="selected"
                                    class="rounded border-zinc-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-900"
                                    aria-label="{{ __('Select :name', ['name' => $class->name]) }}"
                                >
                            </flux:table.cell>
                            <flux:table.cell class="font-medium">{{ $class->name }}</flux:table.cell>
                            <flux:table.cell>{{ $class->level?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $class->teacher?->name ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $class->students_count }}</flux:table.cell>
                            <flux:table.cell>{{ $class->capacity ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($class->is_active)
                                    <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-end">
                                <x-row-actions :label="__('Actions for :name', ['name' => $class->name])">
                                    <flux:menu.item icon="pencil-square" href="{{ route('admin.classes.edit', $class) }}" wire:navigate>{{ __('Edit class') }}</flux:menu.item>
                                    <flux:menu.separator />
                                    @if ($class->students_count === 0)
                                        <x-row-actions.delete
                                            :action="route('admin.classes.destroy', $class)"
                                            :title="__('Delete :name?', ['name' => $class->name])"
                                            :message="__('This class has no students. You can undo this straight after deleting, or restore it from Recently deleted within 30 days.')"
                                        />
                                    @else
                                        <flux:menu.item icon="trash" disabled>{{ __('Delete (move students out first)') }}</flux:menu.item>
                                    @endif
                                </x-row-actions>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="8" class="text-center py-8">
                                {{ __('No classes yet. Create school levels first, then add classes.') }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <template x-teleport="body">
                <div
                    x-show="bulkOpen"
                    x-transition.opacity
                    x-on:keydown.escape.window="bulkOpen = false"
                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                    role="dialog"
                    aria-modal="true"
                    aria-label="{{ __('Delete Selected Classes') }}"
                    x-cloak
                >
                    <div class="fixed inset-0 bg-black/50 dark:bg-black/70" x-on:click="bulkOpen = false" aria-hidden="true"></div>
                    <div x-show="bulkOpen" x-transition class="relative w-full max-w-md rounded-xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-700 dark:bg-zinc-800">
                        <div class="text-center">
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Delete Selected Classes') }}</h3>
                            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Delete selected empty classes? Classes with students will be skipped.') }}
                            </p>
                        </div>
                        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-center">
                            <flux:button type="button" variant="ghost" x-on:click="bulkOpen = false" x-bind:disabled="deleting">{{ __('Cancel') }}</flux:button>
                            <form method="POST" action="{{ route('admin.classes.bulk-destroy') }}" x-on:submit="deleting = true">
                                @csrf
                                @method('DELETE')
                                <template x-for="id in selected" :key="id">
                                    <input type="hidden" name="class_ids[]" :value="id">
                                </template>
                                <flux:button type="submit" variant="danger" icon="trash" x-bind:disabled="deleting || selected.length === 0">
                                    {{ __('Delete Selected') }}
                                </flux:button>
                            </form>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{ $classes->links() }}
    </div>
</x-layouts::app>
