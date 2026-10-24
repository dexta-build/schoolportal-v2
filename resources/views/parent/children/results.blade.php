<x-layouts::app :title="__(':name — Result Sheets (PDF)', ['name' => $child->name])">
    <div class="space-y-6">
        {{-- Breadcrumb --}}
        <div class="flex items-center gap-2 flex-wrap">
            <flux:link href="{{ route('parent.dashboard') }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                {{ __('Dashboard') }}
            </flux:link>
            <flux:icon.chevron-right class="w-3 h-3 text-zinc-400" />
            <flux:link href="{{ route('parent.children.show', $child) }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                {{ $child->name }}
            </flux:link>
            <flux:icon.chevron-right class="w-3 h-3 text-zinc-400" />
            <flux:text class="text-sm">{{ __('Result Sheets (PDF)') }}</flux:text>
        </div>

        <x-admin-header
            :title="__(':name\'s Result Sheets (PDF)', ['name' => $child->name])"
            :description="$child->studentProfile?->class?->name"
        />

        {{-- Filters --}}
        <form method="GET" action="{{ route('parent.children.results', $child) }}" class="flex flex-wrap items-end gap-3">
            <div>
                <flux:select name="session_id" label="{{ __('Session') }}">
                    <option value="">{{ __('All Sessions') }}</option>
                    @foreach ($sessions as $session)
                        <option value="{{ $session->id }}" @selected($selectedSessionId == $session->id)>
                            {{ $session->name }}
                        </option>
                    @endforeach
                </flux:select>
            </div>

            @if ($terms->isNotEmpty())
                <div>
                    <flux:select name="term_id" label="{{ __('Term') }}">
                        <option value="">{{ __('All Terms') }}</option>
                        @foreach ($terms as $term)
                            <option value="{{ $term->id }}" @selected($selectedTermId == $term->id)>
                                {{ $term->name }}
                            </option>
                        @endforeach
                    </flux:select>
                </div>
            @endif

            <flux:button type="submit" variant="filled" size="sm">{{ __('Filter') }}</flux:button>

            @if (request()->hasAny(['session_id', 'term_id']))
                <flux:button variant="subtle" size="sm" href="{{ route('parent.children.results', $child) }}" wire:navigate>
                    {{ __('Clear') }}
                </flux:button>
            @endif
        </form>

        {{-- Results Grid --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($results as $result)
                <a href="{{ route('parent.children.results.show', [$child, $result]) }}" wire:navigate
                   class="group rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:border-blue-300 dark:hover:border-blue-600 transition-colors overflow-hidden">
                    <div class="p-4">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 shrink-0">
                                <flux:icon.document-text class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-zinc-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                    {{ $result->term?->name }}
                                </p>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $result->session?->name }}
                                </flux:text>
                            </div>
                            <flux:icon.chevron-right class="w-4 h-4 text-zinc-400 group-hover:text-blue-500 transition-colors shrink-0" />
                        </div>
                    </div>
                    <div class="px-4 py-2 border-t border-zinc-100 dark:border-zinc-700/50 bg-zinc-50 dark:bg-zinc-800/50">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $result->class?->name }}
                            &mdash;
                            {{ __('Uploaded :date', ['date' => $result->created_at->format('M j, Y')]) }}
                        </flux:text>
                    </div>
                </a>
            @empty
                <div class="col-span-full py-12 text-center">
                    <flux:icon.document-text class="w-12 h-12 mx-auto text-zinc-300 dark:text-zinc-600" />
                    <flux:heading size="sm" class="mt-4">{{ __('No results found') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Results will appear here once they are uploaded and approved by the school.') }}
                    </flux:text>
                </div>
            @endforelse
        </div>

        {{ $results->links() }}
    </div>
</x-layouts::app>
