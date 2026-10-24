@props(['current' => 'grading'])

@php
    $isAdmin = str_starts_with(request()->route()?->getName() ?? '', 'admin.');
    $steps = [
        'grading' => [
            'label' => __('Grading & Score Setup'),
            'route' => $isAdmin ? route('admin.grading.index') : null,
            'number' => 1,
        ],
        'scores' => [
            'label' => __('Score Entry'),
            'route' => $isAdmin ? route('admin.scores.index') : route('teacher.scores.index'),
            'number' => 2,
        ],
        'reports' => [
            'label' => __('Report Cards'),
            'route' => $isAdmin ? route('admin.scores.reports') : route('teacher.scores.reports'),
            'number' => 3,
        ],
    ];
@endphp

<nav aria-label="{{ __('Score workflow') }}" class="flex flex-wrap items-center gap-1.5 text-sm">
    @foreach ($steps as $key => $step)
        @if (!$loop->first)
            <flux:icon name="chevron-right" class="size-3.5 text-zinc-400 dark:text-zinc-500 shrink-0" />
        @endif

        @if ($key === $current)
            <span class="inline-flex items-center gap-1 font-semibold text-indigo-600 dark:text-indigo-400">
                <span class="inline-flex items-center justify-center size-5 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-xs font-bold text-indigo-600 dark:text-indigo-400">{{ $step['number'] }}</span>
                {{ $step['label'] }}
            </span>
        @elseif ($step['route'])
            <a href="{{ $step['route'] }}" class="inline-flex items-center gap-1 text-zinc-400 dark:text-zinc-500 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors" wire:navigate>
                <span class="inline-flex items-center justify-center size-5 rounded-full bg-zinc-100 dark:bg-zinc-700 text-xs text-zinc-500 dark:text-zinc-400">{{ $step['number'] }}</span>
                {{ $step['label'] }}
            </a>
        @else
            <span class="inline-flex items-center gap-1 text-zinc-400 dark:text-zinc-500">
                <span class="inline-flex items-center justify-center size-5 rounded-full bg-zinc-100 dark:bg-zinc-700 text-xs text-zinc-500 dark:text-zinc-400">{{ $step['number'] }}</span>
                {{ $step['label'] }}
            </span>
        @endif
    @endforeach

    @if ($isAdmin)
        <span class="mx-1 h-4 w-px bg-zinc-200 dark:bg-zinc-700" aria-hidden="true"></span>
        <a href="{{ route('admin.end-of-term') }}" wire:navigate class="inline-flex items-center gap-1 font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
            <flux:icon.flag class="size-4" />
            {{ __('End of Term checklist') }}
        </a>
    @endif
</nav>
