<x-layouts::app :title="__('Banner Announcements')">
    <div class="space-y-6">
        <x-admin-header
            :title="__('Banner Announcements')"
            :description="__('Send banner announcements to teachers, students, and parents.')"
            :action="route('admin.announcements.create')"
            :actionLabel="__('New Announcement')"
        />

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Title') }}</flux:table.column>
                <flux:table.column>{{ __('Priority') }}</flux:table.column>
                <flux:table.column class="hidden sm:table-cell">{{ __('Target') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column class="hidden md:table-cell">{{ __('Created') }}</flux:table.column>
                <flux:table.column class="w-32" />
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($announcements as $ann)
                    <flux:table.row>
                        <flux:table.cell class="font-medium">
                            <button type="button" @click="$dispatch('preview-announcement-{{ $ann->id }}')" class="text-left hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline transition-colors">
                                {{ Str::limit($ann->title, 50) }}
                            </button>
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
                        <flux:table.cell class="hidden sm:table-cell">
                            @if ($ann->target_roles)
                                <div class="flex flex-wrap gap-1">
                                    @foreach ($ann->target_roles as $role)
                                        <flux:badge color="zinc" size="sm">{{ ucfirst($role) }}</flux:badge>
                                    @endforeach
                                </div>
                            @else
                                <span class="text-zinc-500 text-sm">{{ __('All roles') }}</span>
                            @endif
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
                            <div class="flex justify-end">
                                {{-- Preview modal --}}
                                <div x-data="{ showPreview: false }" @preview-announcement-{{ $ann->id }}.window="showPreview = true">
                                    <x-row-actions :label="__('Actions for :name', ['name' => $ann->title])">
                                        <flux:menu.item icon="eye" x-on:click="showPreview = true">{{ __('Preview banner') }}</flux:menu.item>
                                        <flux:menu.item icon="pencil-square" href="{{ route('admin.announcements.edit', $ann) }}" wire:navigate>{{ __('Edit') }}</flux:menu.item>
                                        @if ($ann->is_active)
                                            <x-row-actions.form :action="route('admin.announcements.deactivate', $ann)" icon="pause">{{ __('Stop showing') }}</x-row-actions.form>
                                        @else
                                            <x-row-actions.form :action="route('admin.announcements.activate', $ann)" icon="play">{{ __('Start showing') }}</x-row-actions.form>
                                        @endif
                                        <flux:menu.separator />
                                        <x-row-actions.delete
                                            :action="route('admin.announcements.destroy', $ann)"
                                            :title="__('Delete this banner?')"
                                            :message="__('It will stop showing to everyone. You can undo this straight after deleting, or restore it from Recently deleted within 30 days.')"
                                        />
                                    </x-row-actions>

                                    <div x-show="showPreview" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showPreview = false" @keydown.escape.window="showPreview = false">
                                        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-xl max-w-lg w-full mx-auto max-h-[80vh] flex flex-col" @click.stop>
                                            <div class="flex items-center justify-between border-b border-zinc-200 dark:border-zinc-700 px-5 py-3">
                                                <div class="flex items-center gap-2">
                                                    <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $ann->title }}</h3>
                                                    @if ($ann->priority === 'critical')
                                                        <flux:badge color="red" size="sm">{{ __('Critical') }}</flux:badge>
                                                    @elseif ($ann->priority === 'warning')
                                                        <flux:badge color="yellow" size="sm">{{ __('Warning') }}</flux:badge>
                                                    @else
                                                        <flux:badge color="sky" size="sm">{{ __('Info') }}</flux:badge>
                                                    @endif
                                                </div>
                                                <button @click="showPreview = false" class="rounded p-1 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors">
                                                    <flux:icon name="x-mark" class="size-5" />
                                                </button>
                                            </div>
                                            <div class="overflow-y-auto p-5">
                                                <div class="prose dark:prose-invert max-w-none text-sm">
                                                    {!! nl2br(e($ann->content)) !!}
                                                </div>
                                                <div class="mt-4 flex flex-wrap gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                                    @if ($ann->target_roles)
                                                        <span>{{ __('To:') }} {{ collect($ann->target_roles)->map(fn($r) => ucfirst($r))->join(', ') }}</span>
                                                    @else
                                                        <span>{{ __('To: All roles') }}</span>
                                                    @endif
                                                    @if ($ann->starts_at)
                                                        <span>&middot; {{ __('From') }} {{ $ann->starts_at->format('M j') }}</span>
                                                    @endif
                                                    @if ($ann->expires_at)
                                                        <span>&middot; {{ __('Until') }} {{ $ann->expires_at->format('M j') }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <div class="rounded-full bg-zinc-100 dark:bg-zinc-700 p-3 mb-3">
                                    <flux:icon name="signal" class="size-6 text-zinc-400 dark:text-zinc-500" />
                                </div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('No announcements yet') }}</p>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Create banners visible on teacher, student, and parent dashboards.') }}</p>
                                <flux:button variant="primary" size="sm" href="{{ route('admin.announcements.create') }}" wire:navigate icon="plus" class="mt-4">
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
