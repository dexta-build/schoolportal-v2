<x-layouts::app :title="__('Sessions & Terms')">
    <div class="space-y-6">
        <x-admin-header
            :title="__('Academic Sessions & Terms')"
            :description="__('Manage academic sessions and their terms.')"
            :action="route('admin.sessions.create')"
            :actionLabel="__('New Session')"
        />

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        @if (session('error'))
            <flux:callout variant="danger" icon="x-circle">{{ session('error') }}</flux:callout>
        @endif

        @forelse ($sessions as $session)
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 overflow-hidden">
                {{-- Session Header --}}
                <div class="flex items-center justify-between p-4 border-b border-zinc-200 dark:border-zinc-700">
                    <div class="flex items-center gap-3">
                        <flux:heading size="sm">{{ $session->name }}</flux:heading>
                        @if ($session->is_current)
                            <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                        @else
                            <flux:badge color="zinc" size="sm">{{ ucfirst($session->status) }}</flux:badge>
                        @endif
                    </div>
                    <x-row-actions :label="__('Actions for :name', ['name' => $session->name])">
                        <flux:menu.item icon="pencil-square" href="{{ route('admin.sessions.edit', $session) }}" wire:navigate>{{ __('Edit dates') }}</flux:menu.item>
                        @unless ($session->is_current)
                            <x-row-actions.form
                                :action="route('admin.sessions.activate', $session)"
                                icon="check-circle"
                                :confirm="[
                                    'title' => __('Make :name the current session?', ['name' => $session->name]),
                                    'message' => __('Score entry, CBT, results and reports will use this session from now on, for everyone in the school.'),
                                    'confirmLabel' => __('Make current'),
                                ]"
                            >{{ __('Make current session') }}</x-row-actions.form>
                            <flux:menu.separator />
                            <x-row-actions.delete
                                :action="route('admin.sessions.destroy', $session)"
                                :title="__('Delete :name?', ['name' => $session->name])"
                                :message="__('This deletes the session and all its terms. This cannot be undone.')"
                            />
                        @endunless
                    </x-row-actions>
                </div>

                {{-- Session Details --}}
                <div class="px-4 py-2 text-sm text-zinc-500 dark:text-zinc-400 border-b border-zinc-200 dark:border-zinc-700">
                    {{ \Carbon\Carbon::parse($session->start_date)->format('M j, Y') }} &mdash; {{ \Carbon\Carbon::parse($session->end_date)->format('M j, Y') }}
                </div>

                {{-- Terms --}}
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @foreach ($session->terms ?? [] as $term)
                        <div class="flex items-center justify-between px-4 py-3">
                            <div class="flex items-center gap-3">
                                <flux:text class="font-medium">{{ $term->name }}</flux:text>
                                @if ($term->is_current)
                                    <flux:badge color="blue" size="sm">{{ __('Current') }}</flux:badge>
                                @endif
                                @if ($term->start_date)
                                    <flux:text class="text-xs text-zinc-400">
                                        {{ \Carbon\Carbon::parse($term->start_date)->format('M j') }}
                                        @if ($term->end_date)
                                            &mdash; {{ \Carbon\Carbon::parse($term->end_date)->format('M j') }}
                                        @endif
                                    </flux:text>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                @unless ($term->is_current)
                                    <form method="POST" action="{{ route('admin.terms.activate', $term) }}">
                                        @csrf
                                        <flux:button type="submit" variant="subtle" size="xs">{{ __('Set Current') }}</flux:button>
                                    </form>
                                @endunless
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-8 text-center">
                <flux:icon.calendar-days class="w-10 h-10 mx-auto text-zinc-400" />
                <flux:heading size="sm" class="mt-3">{{ __('No academic sessions yet') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Create your first academic session to get started.') }}</flux:text>
                <flux:button variant="primary" icon="plus" href="{{ route('admin.sessions.create') }}" class="mt-4" wire:navigate>
                    {{ __('Create Session') }}
                </flux:button>
            </div>
        @endforelse

        {{ $sessions->links() }}
    </div>
</x-layouts::app>
