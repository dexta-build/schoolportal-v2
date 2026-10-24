@props(['topic'])

{{--
    "How this page works" button for the hardest pages: numbered steps, tips, and a video when the
    super admin has added one in Platform Settings → Help Videos. Text lives in App\Support\PageHelp.

    Usage: <x-page-help topic="gradebook" />   (gradebook | cbt-create | report-cards)
--}}
@php
    $portal = str_starts_with(request()->route()?->getName() ?? '', 'teacher.') ? 'teacher' : 'admin';
    $guide = \App\Support\PageHelp::guide($topic, $portal);
    $videoUrl = \App\Support\PageHelp::videoEmbedUrl($topic);
    $modalName = 'page-help-'.$topic;
@endphp

{{-- wire:ignore: on Livewire pages a re-render must not close the guide while it is open --}}
<div wire:ignore class="inline-flex" x-data="{ showVideo: false }">
    <flux:modal.trigger :name="$modalName">
        <flux:button variant="subtle" size="sm" icon="question-mark-circle">{{ __('How this page works') }}</flux:button>
    </flux:modal.trigger>

    <flux:modal :name="$modalName" class="w-full max-w-2xl" x-on:close="showVideo = false">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ $guide['title'] }}</flux:heading>
                @if ($videoUrl)
                    <flux:button class="mt-3" size="sm" icon="play-circle" x-on:click="showVideo = ! showVideo">
                        <span x-text="showVideo ? @js(__('Show the written steps')) : @js(__('Watch video'))">{{ __('Watch video') }}</span>
                    </flux:button>
                @endif
            </div>

            @if ($videoUrl)
                {{-- The player is only created when asked for, and removed again on close so the video stops --}}
                <template x-if="showVideo">
                    <div class="aspect-video w-full overflow-hidden rounded-lg bg-black">
                        <iframe src="{{ $videoUrl }}" title="{{ $guide['title'] }}" class="h-full w-full" allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowfullscreen loading="lazy"></iframe>
                    </div>
                </template>
            @endif

            <div x-show="! showVideo" class="space-y-5">
                <ol class="space-y-3">
                    @foreach ($guide['steps'] as $step)
                        <li class="flex gap-3">
                            <span class="flex size-6 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-xs font-bold text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300">{{ $loop->iteration }}</span>
                            <span class="text-sm leading-6 text-zinc-700 dark:text-zinc-300">{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>

                @if ($guide['tips'])
                    <div class="rounded-lg bg-amber-50 p-4 dark:bg-amber-900/20">
                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-800 dark:text-amber-300">{{ __('Good to know') }}</p>
                        <ul class="mt-2 list-disc space-y-1.5 pl-5 text-sm text-amber-900 dark:text-amber-200">
                            @foreach ($guide['tips'] as $tip)
                                <li>{{ $tip }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            <div class="flex justify-end">
                <flux:modal.close>
                    <flux:button variant="primary">{{ __('Got it') }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
