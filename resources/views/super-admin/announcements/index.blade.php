<x-layouts::app :title="__('Platform Announcements')">
    <div class="space-y-6">
        <x-admin-header
            :title="__('Platform Announcements')"
            :description="__('Broadcast announcements shown as banners on all school admin dashboards.')"
            :action="route('super-admin.announcements.create')"
            :actionLabel="__('New Announcement')"
        />

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Title') }}</flux:table.column>
                <flux:table.column>{{ __('Priority') }}</flux:table.column>
                <flux:table.column class="hidden sm:table-cell">{{ __('Read') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column class="hidden md:table-cell">{{ __('Created') }}</flux:table.column>
                <flux:table.column class="w-32" />
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($announcements as $ann)
                    <flux:table.row>
                        <flux:table.cell class="font-medium">
                            <a href="{{ route('super-admin.announcements.show', $ann) }}" class="hover:underline" wire:navigate>
                                {{ Str::limit($ann->title, 50) }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($ann->priority === 'critical')
                                <flux:badge color="red" size="sm">{{ __('Critical') }}</flux:badge>
                            @elseif ($ann->priority === 'warning')
                                <flux:badge color="yellow" size="sm">{{ __('Warning') }}</flux:badge>
                            @else
                                <flux:badge color="sky" size="sm">{{ __('Info') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="hidden sm:table-cell text-zinc-500">
                            {{ $ann->reads_count }} / {{ $totalSchools }}
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($ann->is_active)
                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Inactive') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="hidden md:table-cell text-zinc-500">
                            {{ $ann->created_at->format('M j, Y') }}
                        </flux:table.cell>
                        <flux:table.cell class="text-end">
                            <x-row-actions :label="__('Actions for :name', ['name' => $ann->title])">
                                <flux:menu.item icon="eye" href="{{ route('super-admin.announcements.show', $ann) }}" wire:navigate>{{ __('View') }}</flux:menu.item>
                                <flux:menu.item icon="pencil-square" href="{{ route('super-admin.announcements.edit', $ann) }}" wire:navigate>{{ __('Edit') }}</flux:menu.item>
                                @if ($ann->is_active)
                                    <x-row-actions.form :action="route('super-admin.announcements.deactivate', $ann)" icon="pause">{{ __('Stop showing') }}</x-row-actions.form>
                                @else
                                    <x-row-actions.form :action="route('super-admin.announcements.activate', $ann)" icon="play">{{ __('Start showing') }}</x-row-actions.form>
                                @endif
                            </x-row-actions>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <div class="rounded-full bg-zinc-100 dark:bg-zinc-700 p-3 mb-3">
                                    <flux:icon name="megaphone" class="size-6 text-zinc-400 dark:text-zinc-500" />
                                </div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('No announcements yet') }}</p>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Create one to broadcast a banner to all school admins.') }}</p>
                                <flux:button variant="primary" size="sm" href="{{ route('super-admin.announcements.create') }}" wire:navigate icon="plus" class="mt-4">
                                    {{ __('New Announcement') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{ $announcements->links() }}
    </div>
</x-layouts::app>
