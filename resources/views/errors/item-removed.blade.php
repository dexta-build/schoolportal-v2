<x-layouts::app :title="$title">
    <div class="mx-auto max-w-md py-12 text-center">
        <div class="mx-auto flex size-14 items-center justify-center rounded-full bg-zinc-100 dark:bg-zinc-800">
            <flux:icon.archive-box-x-mark class="size-7 text-zinc-400" />
        </div>
        <h1 class="mt-4 text-lg font-semibold text-zinc-900 dark:text-white">{{ $title }}</h1>
        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('The school has removed it, or it is no longer shared with you. Nothing is wrong with your account.') }}
        </p>
        <div class="mt-6">
            <flux:button variant="primary" icon="arrow-left" href="{{ $backUrl }}" wire:navigate>{{ $backLabel }}</flux:button>
        </div>
    </div>
</x-layouts::app>
