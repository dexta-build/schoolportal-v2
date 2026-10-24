<x-layouts::app :title="__('End of Term')">
    <div class="mx-auto max-w-5xl space-y-6">
        <x-admin-header
            :title="__('End of Term')"
            :description="__('Everything that has to happen before report cards reach students and parents — in order, with what is still missing.')"
        />

        {{-- Term selector --}}
        <form method="GET" action="{{ route('admin.end-of-term') }}" class="flex flex-wrap items-end gap-3">
            <div class="min-w-[220px]">
                <flux:select name="term_id" :label="__('Term')" onchange="this.form.submit()">
                    @foreach ($sessions as $session)
                        @foreach ($session->terms as $option)
                            <option value="{{ $option->id }}" @selected($term?->id === $option->id)>
                                {{ $session->name }} — {{ $option->name }}{{ $option->is_current ? ' ('.__('current').')' : '' }}
                            </option>
                        @endforeach
                    @endforeach
                </flux:select>
            </div>
            <noscript><flux:button type="submit" size="sm">{{ __('Show') }}</flux:button></noscript>
        </form>

        @if (! $overview)
            <flux:callout variant="warning" icon="exclamation-triangle">
                {{ __('There is no current term yet. Set one under Sessions & Terms.') }}
            </flux:callout>
        @elseif ($overview['classes']->isEmpty())
            <flux:callout icon="information-circle">
                {{ __('No class has students for this term yet.') }}
            </flux:callout>
        @else
            @php
                $steps = $overview['steps'];
                $currentKey = collect($steps)->firstWhere('status', '!==', \App\Services\EndOfTermService::STATUS_DONE)['key'] ?? null;
            @endphp

            {{-- Progress strip --}}
            <ol class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6" aria-label="{{ __('End of term steps') }}">
                @foreach ($steps as $i => $step)
                    @php $done = $step['status'] === \App\Services\EndOfTermService::STATUS_DONE; @endphp
                    <li>
                        <a href="#step-{{ $step['key'] }}" @class([
                            'flex h-full items-center gap-2 rounded-lg border px-3 py-2 text-sm transition',
                            'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/40 dark:text-emerald-200' => $done,
                            'border-indigo-300 bg-indigo-50 font-semibold text-indigo-800 ring-1 ring-indigo-300 dark:border-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-200' => ! $done && $step['key'] === $currentKey,
                            'border-zinc-200 bg-white text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-400' => ! $done && $step['key'] !== $currentKey,
                        ])>
                            @if ($done)
                                <flux:icon.check-circle class="size-5 shrink-0" />
                            @else
                                <span class="flex size-5 shrink-0 items-center justify-center rounded-full border border-current text-xs">{{ $i + 1 }}</span>
                            @endif
                            <span class="leading-tight">{{ $step['title'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ol>

            {{-- Steps --}}
            <div class="space-y-4">
                @foreach ($steps as $i => $step)
                    @php
                        $status = $step['status'];
                        $isCurrent = $step['key'] === $currentKey;
                    @endphp
                    <section id="step-{{ $step['key'] }}" @class([
                        'scroll-mt-20 rounded-xl border bg-white dark:bg-zinc-900',
                        'border-indigo-300 ring-1 ring-indigo-200 dark:border-indigo-700 dark:ring-indigo-900' => $isCurrent,
                        'border-zinc-200 dark:border-zinc-700' => ! $isCurrent,
                    ]) aria-labelledby="step-{{ $step['key'] }}-title">
                        <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 id="step-{{ $step['key'] }}-title" class="text-base font-semibold text-zinc-900 dark:text-white">
                                        {{ $i + 1 }}. {{ $step['title'] }}
                                    </h2>
                                    @if ($status === \App\Services\EndOfTermService::STATUS_DONE)
                                        <flux:badge color="green" size="sm" icon="check">{{ __('Done') }}</flux:badge>
                                    @elseif ($status === \App\Services\EndOfTermService::STATUS_IN_PROGRESS)
                                        <flux:badge color="amber" size="sm">{{ __('In progress') }}</flux:badge>
                                    @elseif ($step['key'] !== 'promote')
                                        <flux:badge color="zinc" size="sm">{{ __('Not started') }}</flux:badge>
                                    @endif
                                    @if ($isCurrent)
                                        <flux:badge color="indigo" size="sm">{{ __('Next step') }}</flux:badge>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $step['description'] }}</p>
                                <p class="mt-1 text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $step['summary'] }}</p>
                            </div>
                            <flux:button :href="$step['action']['url']" wire:navigate size="sm" :variant="$isCurrent ? 'primary' : 'subtle'" icon-trailing="arrow-right" class="shrink-0">
                                {{ $step['action']['label'] }}
                            </flux:button>
                        </div>

                        @if (! empty($step['items']))
                            <div class="border-t border-zinc-100 dark:border-zinc-800">
                                <p class="px-5 pt-3 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Still to do') }}</p>
                                <ul class="divide-y divide-zinc-100 px-2 pb-2 dark:divide-zinc-800">
                                    @foreach (array_slice($step['items'], 0, 15) as $item)
                                        <li>
                                            <a href="{{ $item['url'] }}" wire:navigate class="flex items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800/60">
                                                <span class="min-w-0">
                                                    <span class="text-zinc-800 dark:text-zinc-200">{{ $item['text'] }}</span>
                                                    @if ($item['who'])
                                                        <span class="block text-xs text-zinc-500 dark:text-zinc-400">{{ __('Teacher: :name', ['name' => $item['who']]) }}</span>
                                                    @endif
                                                </span>
                                                <flux:icon.chevron-right class="size-4 shrink-0 text-zinc-400" />
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                                @if (count($step['items']) > 15)
                                    <p class="px-5 pb-3 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('… and :count more.', ['count' => count($step['items']) - 15]) }}
                                    </p>
                                @endif
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts::app>
