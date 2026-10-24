{{--
    The app's confirm dialog for script-driven confirmations (Livewire actions, conditional
    checks) — use instead of the browser's confirm(). Mounted once per page in the app layouts.

    Usage (Alpine / Livewire):
        x-on:click="appConfirm({
            title: 'Lock all scores?',
            message: 'Locked scores can only be changed through a recorded correction.',
            confirmLabel: 'Lock scores',
            variant: 'danger',            // 'primary' (default) | 'danger'
        }).then(ok => ok && $wire.lockAll())"

    For a plain delete button on a form, keep using <x-confirm-delete>.
--}}
<div
    x-data="{
        open: false,
        title: '',
        message: '',
        confirmLabel: '',
        cancelLabel: '',
        variant: 'primary',
        resolve: null,
        show(options) {
            this.title = options.title ?? @js(__('Are you sure?'));
            this.message = options.message ?? '';
            this.confirmLabel = options.confirmLabel ?? @js(__('Confirm'));
            this.cancelLabel = options.cancelLabel ?? @js(__('Cancel'));
            this.variant = options.variant ?? 'primary';
            this.resolve = options.resolve;
            this.open = true;
            this.$nextTick(() => this.$refs.confirm?.focus());
        },
        answer(value) {
            this.open = false;
            this.resolve?.(value);
            this.resolve = null;
        },
    }"
    x-on:app-confirm.window="show($event.detail)"
>
    <template x-teleport="body">
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            x-on:keydown.escape.window="open && answer(false)"
            class="fixed inset-0 z-[70] flex items-center justify-center p-4"
            role="alertdialog"
            aria-modal="true"
            aria-labelledby="app-confirm-title"
            aria-describedby="app-confirm-message"
            x-cloak
        >
            <div class="fixed inset-0 bg-black/50 dark:bg-black/70" x-on:click="answer(false)" aria-hidden="true"></div>

            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-150"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-trap.noscroll="open"
                class="relative w-full max-w-md rounded-xl border border-zinc-200 bg-white p-6 shadow-xl dark:border-zinc-700 dark:bg-zinc-800"
            >
                <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full"
                     :class="variant === 'danger' ? 'bg-red-100 dark:bg-red-900/30' : 'bg-blue-100 dark:bg-blue-900/30'">
                    <svg class="h-6 w-6" :class="variant === 'danger' ? 'text-red-600 dark:text-red-400' : 'text-blue-600 dark:text-blue-400'" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                    </svg>
                </div>

                <div class="text-center">
                    <h3 id="app-confirm-title" class="text-lg font-semibold text-zinc-900 dark:text-white" x-text="title"></h3>
                    <p id="app-confirm-message" class="mt-2 text-sm text-zinc-500 dark:text-zinc-400" x-text="message" x-show="message"></p>
                </div>

                <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-center">
                    <flux:button variant="ghost" x-on:click="answer(false)"><span x-text="cancelLabel"></span></flux:button>
                    <button
                        type="button"
                        x-ref="confirm"
                        x-on:click="answer(true)"
                        class="inline-flex min-h-10 items-center justify-center rounded-lg px-4 text-sm font-medium text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2"
                        :class="variant === 'danger' ? 'bg-red-600 hover:bg-red-700 focus-visible:ring-red-500' : 'bg-zinc-900 hover:bg-zinc-700 focus-visible:ring-zinc-500 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-200'"
                        x-text="confirmLabel"
                    ></button>
                </div>
            </div>
        </div>
    </template>
</div>
