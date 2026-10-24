{{-- AI Narrative: Key Insights card for Admin Analytics page --}}
<div>
    {{-- ── State: No narrative yet ──────────────────────────────── --}}
    @if (! $narrative)
        {{-- hide immediately when the Livewire action is in-flight --}}
        <div wire:loading.remove wire:target="generate" class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">
            <div class="flex flex-col items-center justify-center gap-4 px-6 py-12 text-center">
                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 dark:bg-indigo-950 ring-1 ring-indigo-200 dark:ring-indigo-800">
                    <svg class="size-7 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">AI Key Insights</h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400 max-w-xs">
                        Get a plain-English analysis of this term's performance — strengths, concerns, and what to do next.
                    </p>
                </div>

                @if ($creditsAvailable > 0)
                    <button
                        wire:click="generate"
                        wire:loading.attr="disabled"
                        wire:target="generate"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                        </svg>
                        Generate Analysis
                        <span class="ml-0.5 rounded-full bg-indigo-500 px-1.5 py-0.5 text-[10px] font-bold leading-none">
                            1 credit
                        </span>
                    </button>
                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ $creditsAvailable }} credit{{ $creditsAvailable !== 1 ? 's' : '' }} available</p>
                @else
                    <div class="rounded-lg bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800 px-4 py-3 text-sm text-amber-800 dark:text-amber-300 max-w-sm">
                        <strong>No credits remaining.</strong> Ask your admin to purchase more, or wait for the free monthly reset.
                    </div>
                @endif

                @if ($error)
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
                @endif
            </div>
        </div>
    @endif

    {{-- ── State: Generating (client-side wire:loading — fires instantly on click) ── --}}
    @if (! $narrative)
        <div wire:loading wire:target="generate" class="rounded-xl border border-indigo-200 dark:border-indigo-800 bg-white dark:bg-zinc-900 overflow-hidden">
            <div class="flex flex-col items-center justify-center gap-5 px-6 py-12 text-center">
                {{-- Pulsing icon ring + spinner --}}
                <div class="relative flex h-16 w-16 items-center justify-center">
                    <span class="absolute inset-0 rounded-full bg-indigo-100 dark:bg-indigo-950 animate-ping opacity-40"></span>
                    <span class="relative flex h-16 w-16 items-center justify-center rounded-full bg-indigo-50 dark:bg-indigo-950 ring-1 ring-indigo-200 dark:ring-indigo-800">
                        <svg class="size-7 text-indigo-500 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                    </span>
                </div>
                <div>
                    <p class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">Generating AI Analysis…</p>
                    <p class="mt-1.5 text-sm text-zinc-500 dark:text-zinc-400">Reading your term data and writing insights.</p>
                    <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">This may take 15–30 seconds — please don't close the page.</p>
                </div>
                {{-- Progress-like shimmer bar --}}
                <div class="w-48 h-1.5 rounded-full bg-zinc-100 dark:bg-zinc-800 overflow-hidden">
                    <div class="h-full rounded-full bg-indigo-400 dark:bg-indigo-600 animate-[shimmer_2s_ease-in-out_infinite]" style="width:60%"></div>
                </div>
            </div>
        </div>
    @endif

    {{-- ── State: Narrative loaded ──────────────────────────────── --}}
    @if ($narrative && ! $generating)
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 overflow-hidden">

            {{-- Header --}}
            <div class="flex items-start justify-between gap-4 border-b border-zinc-100 dark:border-zinc-800 px-6 py-4">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex-shrink-0 flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 dark:bg-indigo-950 ring-1 ring-indigo-200 dark:ring-indigo-800">
                        <svg class="size-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[11px] font-medium uppercase tracking-wider text-indigo-600 dark:text-indigo-400">AI Key Insights</p>
                        <h3 class="mt-0.5 text-base font-semibold text-zinc-900 dark:text-zinc-100 leading-snug">
                            {{ $narrative['headline'] ?? 'Term Analysis' }}
                        </h3>
                    </div>
                </div>
                <div class="flex flex-shrink-0 items-center gap-2">
                    @if ($generatedAt)
                        <span class="text-xs text-zinc-400 dark:text-zinc-500 whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($generatedAt)->diffForHumans() }}
                        </span>
                    @endif
                    <button
                        x-on:click="appConfirm({
                            title: {{ Js::from(__('Regenerate AI analysis?')) }},
                            message: {{ Js::from(__('This uses 1 AI credit.')) }},
                            confirmLabel: {{ Js::from(__('Regenerate')) }},
                        }).then(ok => ok && $wire.generate())"
                        wire:loading.attr="disabled"
                        wire:target="generate"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-2.5 py-1.5 text-xs font-medium text-zinc-600 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-700 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                        title="Regenerate (1 credit)"
                    >
                        <svg wire:loading.remove wire:target="generate" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <svg wire:loading wire:target="generate" class="size-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        <span wire:loading.remove wire:target="generate">Regenerate</span>
                        <span wire:loading wire:target="generate">Generating…</span>
                        <span class="rounded-full bg-zinc-100 dark:bg-zinc-700 px-1 py-0.5 text-[10px] font-bold leading-none text-zinc-500 dark:text-zinc-400">
                            1 cr
                        </span>
                    </button>
                </div>
            </div>

            {{-- Executive Summary --}}
            @if (! empty($narrative['executive_summary']))
                <div class="px-6 py-4 border-b border-zinc-100 dark:border-zinc-800">
                    <p class="text-sm text-zinc-700 dark:text-zinc-300 leading-relaxed">
                        {{ $narrative['executive_summary'] }}
                    </p>
                </div>
            @endif

            {{-- Three-column grid: Strengths / Concerns / Recommendations --}}
            <div class="grid grid-cols-1 divide-y divide-zinc-100 dark:divide-zinc-800 sm:grid-cols-3 sm:divide-y-0 sm:divide-x">

                {{-- Strengths --}}
                <div class="px-5 py-4">
                    <h4 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-emerald-700 dark:text-emerald-400 mb-3">
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Strengths
                    </h4>
                    @if (! empty($narrative['strengths']))
                        <ul class="space-y-2">
                            @foreach ($narrative['strengths'] as $strength)
                                <li class="flex items-start gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                                    <span class="mt-1 flex-shrink-0 size-1.5 rounded-full bg-emerald-500"></span>
                                    {{ $strength }}
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-xs text-zinc-400 italic">Nothing to highlight.</p>
                    @endif
                </div>

                {{-- Concerns --}}
                <div class="px-5 py-4">
                    <h4 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-400 mb-3">
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                        Concerns
                    </h4>
                    @if (! empty($narrative['concerns']))
                        <ul class="space-y-3">
                            @foreach ($narrative['concerns'] as $concern)
                                @php
                                    $severity = $concern['severity'] ?? 'low';
                                    $dot = match($severity) {
                                        'high' => 'bg-red-500',
                                        'medium' => 'bg-amber-400',
                                        default => 'bg-zinc-400',
                                    };
                                @endphp
                                <li class="flex items-start gap-2">
                                    <span class="mt-1.5 flex-shrink-0 size-1.5 rounded-full {{ $dot }}"></span>
                                    <div>
                                        <p class="text-xs font-semibold text-zinc-700 dark:text-zinc-200">{{ $concern['area'] ?? '' }}</p>
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $concern['detail'] ?? '' }}</p>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-xs text-zinc-400 italic">No major concerns identified.</p>
                    @endif
                </div>

                {{-- Recommendations --}}
                <div class="px-5 py-4">
                    <h4 class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wider text-indigo-700 dark:text-indigo-400 mb-3">
                        <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 0 0 1.5-.189m-1.5.189a6.01 6.01 0 0 1-1.5-.189m3.75 7.478a12.06 12.06 0 0 1-4.5 0m3.75 2.354a15.055 15.055 0 0 1-4.5 0M12 2.25c-2.429 0-4.817.178-7.152.521C3.87 3.061 3 4.172 3 5.453v4.286c0 .995.435 1.92 1.153 2.553l.63.56a4.5 4.5 0 0 0 5.894.213l.45-.36a1.5 1.5 0 0 1 1.846 0l.45.36a4.5 4.5 0 0 0 5.894-.213l.63-.56C20.565 11.459 21 10.534 21 9.539V5.453c0-1.281-.87-2.392-2.348-2.682A41.53 41.53 0 0 0 12 2.25Z" />
                        </svg>
                        Next Steps
                    </h4>
                    @if (! empty($narrative['recommendations']))
                        <ul class="space-y-2">
                            @foreach ($narrative['recommendations'] as $i => $rec)
                                <li class="flex items-start gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                                    <span class="flex-shrink-0 mt-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900 text-[10px] font-bold text-indigo-700 dark:text-indigo-300">
                                        {{ $i + 1 }}
                                    </span>
                                    {{ $rec }}
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-xs text-zinc-400 italic">No recommendations available.</p>
                    @endif
                </div>
            </div>

            {{-- Footer: Data quality note --}}
            @if (! empty($narrative['data_quality_note']))
                <div class="border-t border-zinc-100 dark:border-zinc-800 px-6 py-3 flex items-center gap-2">
                    <svg class="size-3.5 flex-shrink-0 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                    </svg>
                    <p class="text-xs text-zinc-400 dark:text-zinc-500">{{ $narrative['data_quality_note'] }}</p>
                </div>
            @endif

        </div>

        {{-- Error shown alongside an existing narrative (e.g. regenerate failure) --}}
        @if ($error)
            <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $error }}</p>
        @endif
    @endif
</div>
