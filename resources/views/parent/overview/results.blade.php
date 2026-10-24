<x-layouts::app :title="__('Result Sheets (PDF)')">
    <div class="space-y-6">
        <x-admin-header
            :title="__('Result Sheets (PDF)')"
            :description="__('View your children\'s uploaded academic results')"
        />

        @if ($children->isEmpty())
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-12 text-center">
                <flux:icon.heart class="w-12 h-12 mx-auto text-zinc-300 dark:text-zinc-600" />
                <flux:heading size="sm" class="mt-4">{{ __('No children linked') }}</flux:heading>
                <flux:text class="mt-1 text-sm text-zinc-500">{{ __('Your account hasn\'t been linked to any students yet. Please contact your school administrator.') }}</flux:text>
            </div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($children as $child)
                    <a href="{{ route('parent.children.results', $child) }}" wire:navigate
                       class="group rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:border-indigo-300 dark:hover:border-indigo-600 transition-all hover:shadow-md overflow-hidden">
                        <div class="p-5">
                            {{-- Child Info --}}
                            <div class="flex items-center gap-3 mb-4">
                                <div class="flex items-center justify-center w-11 h-11 rounded-full bg-indigo-100 dark:bg-indigo-900/30 shrink-0">
                                    @if ($child->avatar_url)
                                        <img src="{{ $child->avatarTableUrl() }}" alt="{{ $child->name }}" class="w-11 h-11 rounded-full object-cover">
                                    @else
                                        <span class="text-sm font-semibold text-indigo-600 dark:text-indigo-400">{{ Str::of($child->name)->explode(' ')->map(fn($n) => Str::substr($n, 0, 1))->take(2)->implode('') }}</span>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold text-zinc-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors truncate">
                                        {{ $child->name }}
                                    </p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $child->studentProfile?->class?->name ?? __('No class') }}
                                        @if ($child->studentProfile?->class?->level)
                                            &middot; {{ $child->studentProfile->class->level->name }}
                                        @endif
                                    </p>
                                </div>
                                <flux:icon.chevron-right class="w-5 h-5 text-zinc-400 group-hover:text-indigo-500 transition-colors shrink-0" />
                            </div>

                            {{-- Stats --}}
                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-md bg-zinc-50 dark:bg-zinc-700/30 px-3 py-2">
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Total Uploaded') }}</p>
                                    <p class="text-lg font-bold text-zinc-900 dark:text-white">{{ $child->stat_results_count }}</p>
                                </div>
                                <div class="rounded-md bg-zinc-50 dark:bg-zinc-700/30 px-3 py-2">
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Latest') }}</p>
                                    @if ($child->stat_latest_result)
                                        <p class="text-sm font-medium text-zinc-900 dark:text-white truncate">{{ $child->stat_latest_result->term?->name }}</p>
                                        <p class="text-xs text-zinc-500">{{ $child->stat_latest_result->session?->name }}</p>
                                    @else
                                        <p class="text-sm text-zinc-400">&mdash;</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="px-5 py-2.5 border-t border-zinc-100 dark:border-zinc-700/50 bg-zinc-50/50 dark:bg-zinc-800/50">
                            <span class="text-xs font-medium text-indigo-600 dark:text-indigo-400 group-hover:underline">{{ __('View all uploaded results') }} &rarr;</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts::app>
