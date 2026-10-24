{{--
    Shown instead of Livewire's browser confirm() when a request comes back as 419
    (session expired, or a request the server could not accept). Opened from resources/js/app.js.
--}}
<div x-data="{ open: false }" x-on:livewire-page-expired.window="open = true">
    <template x-teleport="body">
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            class="fixed inset-0 z-[60] flex items-center justify-center p-4"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="page-expired-title"
            aria-describedby="page-expired-message"
            x-cloak
        >
            <div class="fixed inset-0 bg-black/50 dark:bg-black/70" aria-hidden="true"></div>

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-trap.noscroll="open"
                class="relative w-full max-w-md rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-6 shadow-xl"
            >
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30 mb-4">
                    <flux:icon name="arrow-path" class="h-6 w-6 text-amber-600 dark:text-amber-400" />
                </div>

                <div class="text-center">
                    <h3 id="page-expired-title" class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Please reload the page') }}</h3>
                    <p id="page-expired-message" class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('This page is out of date — your session may have expired. Reload to continue. If you were saving a change, check it after reloading.') }}
                    </p>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-center">
                    <flux:button variant="ghost" x-on:click="open = false">{{ __('Not now') }}</flux:button>
                    <flux:button variant="primary" icon="arrow-path" x-on:click="window.location.reload()">{{ __('Reload page') }}</flux:button>
                </div>
            </div>
        </div>
    </template>
</div>
