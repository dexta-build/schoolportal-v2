<x-layouts::app :title="__('School Levels')">
    <div class="space-y-6">
        <x-admin-header
            :title="__('School Levels')"
            :description="__('Manage school levels like Nursery, Primary, Secondary.')"
            :action="route('admin.levels.create')"
            :actionLabel="__('Add Level')"
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
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Classes') }}</flux:table.column>
                    <flux:table.column>{{ __('Order') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column class="w-32" />
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($levels as $level)
                        <flux:table.row>
                            <flux:table.cell>
                                <input
                                    type="checkbox"
                                    value="{{ $level->id }}"
                                    x-model="selected"
                                    class="rounded border-zinc-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-900"
                                    aria-label="{{ __('Select :name', ['name' => $level->name]) }}"
                                >
                            </flux:table.cell>
                            <flux:table.cell class="font-medium">{{ $level->name }}</flux:table.cell>
                            <flux:table.cell>{{ $level->classes_count }}</flux:table.cell>
                            <flux:table.cell>{{ $level->sort_order }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($level->is_active)
                                    <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                                @else
                                    <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="text-end">
                                <x-row-actions :label="__('Actions for :name', ['name' => $level->name])">
                                    <flux:menu.item icon="pencil-square" href="{{ route('admin.levels.edit', $level) }}" wire:navigate>{{ __('Edit level') }}</flux:menu.item>
                                    <flux:menu.separator />
                                    @if ($level->classes_count === 0)
                                        <x-row-actions.delete
                                            :action="route('admin.levels.destroy', $level)"
                                            :title="__('Delete :name?', ['name' => $level->name])"
                                            :message="__('This level has no classes. Deleting it cannot be undone.')"
                                        />
                                    @else
                                        <flux:menu.item icon="trash" disabled>{{ __('Delete (remove its classes first)') }}</flux:menu.item>
                                    @endif
                                </x-row-actions>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6" class="text-center py-8">
                                {{ __('No school levels yet. Add levels like Nursery, Primary, Secondary.') }}
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
                    aria-label="{{ __('Delete Selected Levels') }}"
                    x-cloak
                >
                    <div class="fixed inset-0 bg-black/50 dark:bg-black/70" x-on:click="bulkOpen = false" aria-hidden="true"></div>
                    <div x-show="bulkOpen" x-transition class="relative w-full max-w-md rounded-xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-700 dark:bg-zinc-800">
                        <div class="text-center">
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Delete Selected Levels') }}</h3>
                            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Delete selected empty levels? Levels with classes will be skipped.') }}
                            </p>
                        </div>
                        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-center">
                            <flux:button type="button" variant="ghost" x-on:click="bulkOpen = false" x-bind:disabled="deleting">{{ __('Cancel') }}</flux:button>
                            <form method="POST" action="{{ route('admin.levels.bulk-destroy') }}" x-on:submit="deleting = true">
                                @csrf
                                @method('DELETE')
                                <template x-for="id in selected" :key="id">
                                    <input type="hidden" name="level_ids[]" :value="id">
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
    </div>
</x-layouts::app>
