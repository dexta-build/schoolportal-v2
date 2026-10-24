<x-layouts::app :title="__('Notice Board')">
    <div class="space-y-6">
        <x-admin-header
            :title="__('Notice Board')"
            :description="__('Posts for the school notice board, with pictures or files. Everyone it is meant for gets a notification.')"
            :action="route('admin.notices.create')"
            :actionLabel="__('Add Notice')"
        />

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        @if (session('error'))
            <flux:callout variant="danger" icon="exclamation-triangle">{{ session('error') }}</flux:callout>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Title') }}</flux:table.column>
                <flux:table.column class="hidden sm:table-cell">{{ __('Created By') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column class="hidden md:table-cell">{{ __('Expires') }}</flux:table.column>
                <flux:table.column class="hidden sm:table-cell">{{ __('Date') }}</flux:table.column>
                <flux:table.column class="w-40" />
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($notices as $notice)
                    <flux:table.row>
                        <flux:table.cell class="font-medium">
                            <span class="flex items-center gap-1.5">
                                {{ Str::limit($notice->title, 50) }}
                                @if ($notice->file_url)
                                    <flux:icon.paper-clip class="w-4 h-4 text-zinc-400" />
                                @endif
                            </span>
                        </flux:table.cell>
                        <flux:table.cell class="hidden sm:table-cell text-zinc-500">{{ $notice->creator?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($notice->is_published)
                                <flux:badge color="green" size="sm">{{ __('Published') }}</flux:badge>
                            @elseif ($notice->status === 'approved')
                                <flux:badge color="sky" size="sm">{{ __('Unpublished') }}</flux:badge>
                            @elseif ($notice->status === 'pending')
                                <flux:badge color="yellow" size="sm">{{ __('Pending') }}</flux:badge>
                            @elseif ($notice->status === 'rejected')
                                <flux:badge color="red" size="sm">{{ __('Rejected') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('Draft') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="hidden md:table-cell text-zinc-500">{{ $notice->expires_at?->format('M j, Y') ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="hidden sm:table-cell text-zinc-500">{{ $notice->created_at->format('M j, Y') }}</flux:table.cell>
                        <flux:table.cell class="text-end">
                            <div x-data="{ showPreview: false }" class="flex justify-end">
                                <x-row-actions :label="__('Actions for :name', ['name' => $notice->title])">
                                    <flux:menu.item icon="eye" x-on:click="showPreview = true">{{ __('Preview') }}</flux:menu.item>

                                    {{-- Edit & actions only for notices the admin created --}}
                                    @if ($notice->created_by === auth()->id())
                                        <flux:menu.item icon="pencil-square" href="{{ route('admin.notices.edit', $notice) }}" wire:navigate>{{ __('Edit') }}</flux:menu.item>

                                        @if ($notice->is_published)
                                            <x-row-actions.form :action="route('admin.notices.unpublish', $notice)" icon="eye-slash">{{ __('Unpublish (hide from readers)') }}</x-row-actions.form>
                                        @elseif ($notice->status === 'approved')
                                            <x-row-actions.form :action="route('admin.notices.publish', $notice)" icon="megaphone">{{ __('Publish') }}</x-row-actions.form>
                                        @endif

                                        <flux:menu.separator />
                                        <x-row-actions.delete
                                            :action="route('admin.notices.destroy', $notice)"
                                            :title="__('Delete this notice?')"
                                            :message="__('Readers will no longer see it. You can undo this straight after deleting, or restore it from Recently deleted within 30 days.')"
                                        />
                                    @endif
                                </x-row-actions>

                                    <div x-show="showPreview" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showPreview = false" @keydown.escape.window="showPreview = false">
                                        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-xl max-w-2xl w-full mx-auto max-h-[85vh] flex flex-col" @click.stop>
                                            <div class="flex items-start justify-between gap-4 p-5 border-b border-zinc-200 dark:border-zinc-700">
                                                <div>
                                                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $notice->title }}</h3>
                                                    <p class="text-xs text-zinc-500 mt-1">
                                                        {{ __('By') }} {{ $notice->creator?->name ?? __('Unknown') }} &middot; {{ $notice->created_at->format('M j, Y g:i A') }}
                                                    </p>
                                                </div>
                                                <button @click="showPreview = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 p-1">
                                                    <flux:icon name="x-mark" class="size-5" />
                                                </button>
                                            </div>
                                            <div class="p-5 overflow-y-auto flex-1 space-y-4">
                                                <div class="prose prose-sm dark:prose-invert max-w-none text-zinc-700 dark:text-zinc-300">
                                                    {!! nl2br(e($notice->content)) !!}
                                                </div>
                                                @if ($notice->file_url)
                                                    <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                                                        @if ($notice->fileIsImage())
                                                            <img src="{{ $notice->file_url }}" alt="{{ $notice->file_name ?? __('Notice attachment') }}" class="w-full max-h-80 object-contain bg-zinc-50 dark:bg-zinc-900" />
                                                        @else
                                                            <div class="flex items-center gap-3 p-3 bg-zinc-50 dark:bg-zinc-700/50">
                                                                <flux:icon.paper-clip class="size-5 text-zinc-500 shrink-0" />
                                                                <span class="text-sm text-zinc-700 dark:text-zinc-300 truncate flex-1">{{ $notice->file_name ?? __('Attached file') }}</span>
                                                                <a href="{{ $notice->file_url }}" target="_blank" rel="noopener noreferrer" class="text-xs font-medium text-blue-600 hover:underline whitespace-nowrap">{{ __('Open file') }}</a>
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endif
                                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                                                    <div>
                                                        <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">{{ __('Levels') }}</span>
                                                        <p class="mt-0.5 text-zinc-700 dark:text-zinc-300">
                                                            @if (!empty($notice->target_levels))
                                                                {{ \App\Models\SchoolLevel::whereIn('id', $notice->target_levels)->pluck('name')->join(', ') }}
                                                            @else
                                                                {{ __('All levels') }}
                                                            @endif
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">{{ __('Classes') }}</span>
                                                        <p class="mt-0.5 text-zinc-700 dark:text-zinc-300">
                                                            @if (!empty($notice->target_classes))
                                                                {{ \App\Models\SchoolClass::whereIn('id', $notice->target_classes)->pluck('name')->join(', ') }}
                                                            @else
                                                                {{ __('All classes') }}
                                                            @endif
                                                        </p>
                                                    </div>
                                                    <div>
                                                        <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">{{ __('Audience') }}</span>
                                                        <p class="mt-0.5 text-zinc-700 dark:text-zinc-300">
                                                            @if (!empty($notice->target_roles))
                                                                {{ collect($notice->target_roles)->map(fn ($r) => ucfirst($r) . 's')->join(', ') }}
                                                            @else
                                                                {{ __('Everyone') }}
                                                            @endif
                                                        </p>
                                                    </div>
                                                </div>
                                                @if ($notice->expires_at)
                                                    <p class="text-xs text-zinc-500">{{ __('Expires:') }} {{ $notice->expires_at->format('M j, Y') }}</p>
                                                @endif
                                            </div>
                                            <div class="flex justify-end gap-2 p-5 border-t border-zinc-200 dark:border-zinc-700">
                                                <flux:button variant="subtle" size="sm" @click="showPreview = false">{{ __('Close') }}</flux:button>
                                            </div>
                                        </div>
                                    </div>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="py-14 text-center">
                            <flux:icon.megaphone class="mx-auto w-10 h-10 text-zinc-300 dark:text-zinc-600" />
                            <p class="mt-3 text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('No notices found.') }}</p>
                            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">{{ __('Post a notice to share updates with students and parents.') }}</p>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{ $notices->links() }}
    </div>
</x-layouts::app>
