<x-layouts::app :title="__('Notifications')">
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <flux:heading size="xl">{{ __('Notifications') }}</flux:heading>
                <flux:text class="mt-1">
                    {{ trans_choice(':count unread notification|:count unread notifications', $unreadCount, ['count' => $unreadCount]) }}
                </flux:text>
            </div>

            <div class="flex items-center gap-2">
                @if ($unreadCount > 0)
                    <form method="POST" action="{{ url('/portal/notifications/mark-all-read') }}">
                        @csrf
                        <flux:button type="submit" variant="subtle" size="sm" icon="check">
                            {{ __('Mark all read') }}
                        </flux:button>
                    </form>
                @endif

                @if ($notifications->total() > 0)
                    <flux:modal.trigger name="clear-all-notifications">
                        <flux:button variant="subtle" size="sm" icon="trash" class="text-red-600 hover:text-red-700 dark:text-red-400">
                            {{ __('Clear all') }}
                        </flux:button>
                    </flux:modal.trigger>
                @endif
            </div>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        {{-- Filter tabs --}}
        <div class="flex items-center gap-1 rounded-lg bg-zinc-100 p-1 dark:bg-zinc-900" role="tablist">
            <a
                href="{{ url('/portal/notifications?filter=all') }}"
                wire:navigate
                role="tab"
                aria-selected="{{ $filter === 'all' ? 'true' : 'false' }}"
                @class([
                    'rounded-md px-3 py-1.5 text-sm font-medium transition',
                    'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' => $filter === 'all',
                    'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' => $filter !== 'all',
                ])
            >
                {{ __('All') }}
            </a>
            <a
                href="{{ url('/portal/notifications?filter=unread') }}"
                wire:navigate
                role="tab"
                aria-selected="{{ $filter === 'unread' ? 'true' : 'false' }}"
                @class([
                    'rounded-md px-3 py-1.5 text-sm font-medium transition',
                    'bg-white text-zinc-900 shadow-sm dark:bg-zinc-700 dark:text-white' => $filter === 'unread',
                    'text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200' => $filter !== 'unread',
                ])
            >
                {{ __('Unread') }}
                @if ($unreadCount > 0)
                    <span class="ml-1 inline-flex items-center rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-700 dark:bg-red-500/20 dark:text-red-400">
                        {{ $unreadCount }}
                    </span>
                @endif
            </a>
        </div>

        {{-- Notification list --}}
        <div class="overflow-hidden rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-800">
            @forelse ($notifications as $notification)
                @php
                    $data = $notification->data;
                    $isUnread = $notification->read_at === null;
                @endphp
                <div @class([
                    'group flex items-start gap-3 border-b border-zinc-100 px-4 py-3 last:border-b-0 dark:border-zinc-700/50 transition',
                    'bg-indigo-50/50 dark:bg-indigo-500/5' => $isUnread,
                ])>
                    {{-- Icon --}}
                    <span @class([
                        'mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full',
                        'bg-indigo-100 text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400' => in_array($data['type_label'] ?? '', ['result', 'assignment', 'quiz', 'notice', 'exam']),
                        'bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400' => ($data['type_label'] ?? '') === 'approval',
                        'bg-red-100 text-red-600 dark:bg-red-500/20 dark:text-red-400' => ($data['type_label'] ?? '') === 'rejection',
                        'bg-purple-100 text-purple-600 dark:bg-purple-500/20 dark:text-purple-400' => ($data['type_label'] ?? '') === 'game',
                        'bg-amber-100 text-amber-600 dark:bg-amber-500/20 dark:text-amber-400' => in_array($data['type_label'] ?? '', ['school', 'credit_purchase']),
                        'bg-zinc-100 text-zinc-600 dark:bg-zinc-600 dark:text-zinc-300' => ! in_array($data['type_label'] ?? '', ['result', 'assignment', 'quiz', 'notice', 'approval', 'rejection', 'game', 'school', 'credit_purchase']),
                    ])>
                        @switch($data['icon'] ?? 'bell')
                            @case('document-text')
                                <flux:icon.document-text class="size-4" />
                                @break
                            @case('academic-cap')
                                <flux:icon.academic-cap class="size-4" />
                                @break
                            @case('megaphone')
                                <flux:icon.megaphone class="size-4" />
                                @break
                            @case('question-mark-circle')
                                <flux:icon.question-mark-circle class="size-4" />
                                @break
                            @case('puzzle-piece')
                                <flux:icon.puzzle-piece class="size-4" />
                                @break
                            @case('check-circle')
                                <flux:icon.check-circle class="size-4" />
                                @break
                            @case('x-circle')
                                <flux:icon.x-circle class="size-4" />
                                @break
                            @case('building-office-2')
                                <flux:icon.building-office-2 class="size-4" />
                                @break
                            @case('sparkles')
                                <flux:icon.sparkles class="size-4" />
                                @break
                            @default
                                <flux:icon.bell class="size-4" />
                        @endswitch
                    </span>

                    {{-- Content --}}
                    <div class="min-w-0 flex-1">
                        <p class="text-sm leading-snug {{ $isUnread ? 'font-medium text-zinc-900 dark:text-white' : 'text-zinc-600 dark:text-zinc-300' }}">
                            {{ $data['message'] ?? '' }}
                        </p>
                        <div class="mt-1 flex items-center gap-2">
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $notification->created_at->diffForHumans() }}
                            </p>
                            @if (isset($data['type_label']))
                                <span class="text-xs text-zinc-300 dark:text-zinc-600">&bull;</span>
                                <flux:badge size="sm" color="zinc">
                                    {{ ucfirst(str_replace('_', ' ', $data['type_label'])) }}
                                </flux:badge>
                            @endif
                        </div>
                    </div>

                    {{-- Actions --}}
                    {{-- Always visible: phones have no hover --}}
                    <div class="shrink-0">
                        <x-row-actions :label="__('Actions for this notification')">
                            @if ($data['action_url'] ?? null)
                                <x-row-actions.form :action="url('/portal/notifications/' . $notification->id . '/read')" icon="arrow-top-right-on-square">{{ __('Open') }}</x-row-actions.form>
                            @endif
                            {{-- Opening also marks it read, so this is only offered when there is nothing to open --}}
                            @if ($isUnread && ! ($data['action_url'] ?? null))
                                <x-row-actions.form :action="url('/portal/notifications/' . $notification->id . '/read')" icon="check">{{ __('Mark as read') }}</x-row-actions.form>
                            @endif
                            <flux:menu.separator />
                            <x-row-actions.form :action="url('/portal/notifications/' . $notification->id)" method="DELETE" icon="trash" variant="danger">{{ __('Remove from list') }}</x-row-actions.form>
                        </x-row-actions>
                    </div>

                    {{-- Unread dot (always visible, unlike actions) --}}
                    @if ($isUnread)
                        <span class="mt-2.5 h-2 w-2 shrink-0 rounded-full bg-indigo-500" aria-label="{{ __('Unread') }}"></span>
                    @endif
                </div>
            @empty
                <div class="px-4 py-16 text-center">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-700">
                        <flux:icon.bell class="size-7 text-zinc-500 dark:text-zinc-400" />
                    </div>
                    <flux:heading size="lg" class="mt-4">{{ __('No notifications') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ $filter === 'unread' ? __("You're all caught up! No unread notifications.") : __("You don't have any notifications yet.") }}
                    </flux:text>
                </div>
            @endforelse
        </div>

        {{ $notifications->links() }}
    </div>

    {{-- Clear all confirmation modal --}}
    <flux:modal name="clear-all-notifications" class="max-w-md">
        <form method="POST" action="{{ url('/portal/notifications/clear-all') }}" class="space-y-4">
            @csrf
            @method('DELETE')
            <div>
                <flux:heading size="lg">{{ __('Clear all notifications?') }}</flux:heading>
                <flux:text class="mt-1">{{ __('This will permanently delete all your notifications. This action cannot be undone.') }}</flux:text>
            </div>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Clear all') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</x-layouts::app>
