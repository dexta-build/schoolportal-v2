{{--
    "Deleted — Undo" bar shown after a delete that can be reversed (notices, assignments).
    A controller flashes: ->with('undo', ['message' => __('Notice deleted.'), 'url' => route('…restore', $id)])
    Undo posts to that url. Stays until dismissed or the person moves on, so nobody has to race a timer.
--}}
@if (is_array($undo = session('undo')) && ! empty($undo['url']))
    <div
        x-data="{ show: true }"
        x-show="show"
        x-transition
        class="fixed inset-x-0 bottom-16 z-[55] mx-auto flex w-fit max-w-[calc(100%-2rem)] items-center gap-3 rounded-xl bg-zinc-900 py-2 pl-4 pr-2 text-sm text-white shadow-lg sm:bottom-8 dark:bg-white dark:text-zinc-900"
        role="status"
        aria-live="polite"
    >
        <flux:icon.trash class="size-4 shrink-0 opacity-70" />
        <span class="min-w-0">{{ $undo['message'] ?? __('Deleted.') }}</span>
        <form method="POST" action="{{ $undo['url'] }}" class="shrink-0">
            @csrf
            <button type="submit"
                class="min-h-9 rounded-lg px-3 font-semibold text-sky-300 hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300 dark:text-sky-700 dark:hover:bg-zinc-100">
                {{ __('Undo') }}
            </button>
        </form>
        <button type="button" x-on:click="show = false"
            class="flex size-9 shrink-0 items-center justify-center rounded-lg hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300 dark:hover:bg-zinc-100"
            aria-label="{{ __('Dismiss') }}">
            <flux:icon.x-mark class="size-4" />
        </button>
    </div>
@endif
