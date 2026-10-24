{{--
    "Getting started" checklist for new schools. Each step is ticked off from the school's real
    data (SetupChecklistService) and the panel disappears once everything is done.
--}}
@php
    $doneCount = collect($setupSteps)->where('done', true)->count();
    $totalSteps = count($setupSteps);
    $nextStepKey = collect($setupSteps)->firstWhere('done', false)['key'] ?? null;
@endphp

<section class="rounded-xl border border-indigo-200 bg-white shadow-sm dark:border-indigo-900/60 dark:bg-zinc-900" aria-labelledby="setup-checklist-title">
    <div class="flex flex-col gap-3 border-b border-zinc-100 px-5 py-4 dark:border-zinc-800 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h2 id="setup-checklist-title" class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Getting started') }}</h2>
            <p class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Set up your school in this order. Each step is ticked automatically once it is done.') }}
            </p>
        </div>
        <p class="shrink-0 text-sm font-medium text-indigo-700 dark:text-indigo-300">
            {{ __(':done of :total done', ['done' => $doneCount, 'total' => $totalSteps]) }}
        </p>
    </div>

    <div class="px-5 pt-3">
        <div class="h-2 w-full overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800" role="progressbar" aria-valuemin="0" aria-valuemax="{{ $totalSteps }}" aria-valuenow="{{ $doneCount }}" aria-label="{{ __('Setup progress') }}">
            <div class="h-full rounded-full bg-indigo-500 transition-all" style="width: {{ $totalSteps ? round($doneCount / $totalSteps * 100) : 0 }}%"></div>
        </div>
    </div>

    <ol class="divide-y divide-zinc-100 px-2 py-2 dark:divide-zinc-800">
        @foreach ($setupSteps as $i => $step)
            @php $isNext = $step['key'] === $nextStepKey; @endphp
            <li @class(['flex items-center gap-3 rounded-lg px-3 py-3', 'bg-indigo-50/70 dark:bg-indigo-950/30' => $isNext])>
                @if ($step['done'])
                    <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white" aria-hidden="true">
                        <flux:icon.check class="size-4" />
                    </span>
                @else
                    <span @class([
                        'flex size-7 shrink-0 items-center justify-center rounded-full text-sm font-semibold',
                        'bg-indigo-600 text-white' => $isNext,
                        'bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400' => ! $isNext,
                    ]) aria-hidden="true">{{ $i + 1 }}</span>
                @endif

                <div class="min-w-0 flex-1">
                    <p @class(['text-sm font-medium', 'text-zinc-400 line-through dark:text-zinc-500' => $step['done'], 'text-zinc-900 dark:text-white' => ! $step['done']])>
                        <span class="sr-only">{{ $step['done'] ? __('Done:') : __('To do:') }}</span>
                        {{ $step['title'] }}
                    </p>
                    @unless ($step['done'])
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $step['description'] }}</p>
                    @endunless
                </div>

                @unless ($step['done'])
                    <flux:button size="sm" :variant="$isNext ? 'primary' : 'ghost'" :href="$step['url']" wire:navigate icon-trailing="arrow-right" class="shrink-0">
                        {{ $step['action'] }}
                    </flux:button>
                @endunless
            </li>
        @endforeach
    </ol>

    <div class="flex justify-end border-t border-zinc-100 px-5 py-3 dark:border-zinc-800">
        <form method="POST" action="{{ route('admin.setup-checklist.dismiss') }}">
            @csrf
            <button type="submit" class="text-xs font-medium text-zinc-500 underline-offset-2 hover:text-zinc-700 hover:underline dark:text-zinc-400 dark:hover:text-zinc-200">
                {{ __('Hide this checklist') }}
            </button>
        </form>
    </div>
</section>
