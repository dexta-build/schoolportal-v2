<x-layouts::app :title="__('My Notices')">
    <div class="space-y-6">
        <x-admin-header
            :title="__('My Notices')"
            :action="route('teacher.notices.create')"
            :actionLabel="__('Post Notice')"
            actionIcon="megaphone"
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
                <flux:table.column class="hidden sm:table-cell">{{ __('Published') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column class="hidden md:table-cell">{{ __('Expires') }}</flux:table.column>
                <flux:table.column class="hidden sm:table-cell">{{ __('Created') }}</flux:table.column>
                <flux:table.column class="w-28">{{ __('Actions') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($notices as $notice)
                    <flux:table.row>
                        <flux:table.cell class="font-medium">
                            <div>
                                <span class="flex items-center gap-1.5">
                                    {{ Str::limit($notice->title, 50) }}
                                    @if ($notice->file_url)
                                        <flux:icon.paper-clip class="w-4 h-4 text-zinc-400 shrink-0" />
                                    @endif
                                </span>
                                @if ($notice->status === 'rejected' && isset($rejectionReasons[$notice->id]))
                                    <p class="text-xs text-red-600 mt-1">
                                        <span class="font-medium">{{ __('Reason:') }}</span> {{ $rejectionReasons[$notice->id] }}
                                    </p>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="hidden sm:table-cell">
                            @if ($notice->is_published)
                                <flux:badge color="green" size="sm">{{ __('Yes') }}</flux:badge>
                            @else
                                <flux:badge color="zinc" size="sm">{{ __('No') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($notice->status === 'approved')
                                <flux:badge color="green" size="sm">{{ __('Approved') }}</flux:badge>
                            @elseif ($notice->status === 'pending')
                                <flux:badge color="yellow" size="sm">{{ __('Pending') }}</flux:badge>
                            @else
                                <flux:badge color="red" size="sm">{{ __('Rejected') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="hidden md:table-cell text-zinc-500">
                            {{ $notice->expires_at?->format('M j, Y') ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell class="hidden sm:table-cell text-zinc-500">{{ $notice->created_at->format('M j, Y') }}</flux:table.cell>
                        <flux:table.cell class="text-end">
                            <x-row-actions :label="__('Actions for :name', ['name' => $notice->title])">
                                <flux:menu.item icon="eye" href="{{ route('teacher.notices.show', $notice) }}" wire:navigate>{{ __('View') }}</flux:menu.item>
                                <flux:menu.item icon="pencil-square" href="{{ route('teacher.notices.edit', $notice) }}" wire:navigate>{{ __('Edit') }}</flux:menu.item>

                                @if ($notice->is_published)
                                    <x-row-actions.form :action="route('teacher.notices.unpublish', $notice)" icon="eye-slash">{{ __('Unpublish (hide from readers)') }}</x-row-actions.form>
                                @elseif ($notice->status === 'approved')
                                    <x-row-actions.form :action="route('teacher.notices.publish', $notice)" icon="megaphone">{{ __('Publish') }}</x-row-actions.form>
                                @endif

                                <flux:menu.separator />
                                <x-row-actions.delete
                                    :action="route('teacher.notices.destroy', $notice)"
                                    :title="__('Delete this notice?')"
                                    :message="__('Readers will no longer see it. You can undo this straight after deleting, or restore it from Recently deleted within 30 days.')"
                                />
                            </x-row-actions>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6" class="text-center py-8">
                            <flux:icon.megaphone class="w-8 h-8 mx-auto text-zinc-300 dark:text-zinc-600" />
                            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No notices posted yet. Share an announcement with your students.') }}</p>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{ $notices->links() }}
    </div>
</x-layouts::app>
