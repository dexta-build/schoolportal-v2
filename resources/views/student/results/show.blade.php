<x-layouts::app :title="__('View Result')">
    <div class="space-y-6">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2 mb-1">
                    <flux:link href="{{ route('student.results.index') }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                        {{ __('My Result Sheets (PDF)') }}
                    </flux:link>
                    <flux:icon.chevron-right class="w-3 h-3 text-zinc-400" />
                    <flux:text class="text-sm">{{ $result->term?->name }}</flux:text>
                </div>
                <flux:heading size="xl">
                    {{ $result->session?->name }} &mdash; {{ $result->term?->name }}
                </flux:heading>
                <flux:text class="mt-1">
                    {{ $result->class?->name }}
                    &mdash;
                    {{ __('Uploaded :date', ['date' => $result->created_at->format('M j, Y')]) }}
                </flux:text>
            </div>
            <div class="flex gap-2">
                <flux:button variant="filled" size="sm" icon="arrow-down-tray" href="{{ $result->file_url }}" target="_blank" rel="noopener noreferrer">
                    {{ __('Download PDF') }}
                </flux:button>
            </div>
        </div>

        @if ($result->notes)
            <div class="p-4 rounded-lg border border-blue-200 dark:border-blue-700 bg-blue-50 dark:bg-blue-900/20">
                <div class="flex items-start gap-3">
                    <flux:icon.information-circle class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5 shrink-0" />
                    <div>
                        <p class="font-medium text-blue-800 dark:text-blue-300 text-sm">{{ __('Note from school') }}</p>
                        <flux:text class="text-sm text-blue-700 dark:text-blue-300/80 mt-1">{{ $result->notes }}</flux:text>
                    </div>
                </div>
            </div>
        @endif

        {{-- PDF Viewer --}}
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 overflow-hidden">
            <div class="aspect-[3/4] sm:aspect-[4/5] md:aspect-[16/10] w-full">
                <iframe
                    src="{{ $result->file_url }}#toolbar=1&navpanes=0"
                    class="w-full h-full"
                    title="{{ __('Result PDF for :session :term', ['session' => $result->session?->name, 'term' => $result->term?->name]) }}"
                    loading="lazy"
                ></iframe>
            </div>
        </div>

        {{-- Fallback for mobile/unsupported browsers --}}
        <div class="text-center sm:hidden">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 mb-2">
                {{ __("Can't see the document?") }}
            </flux:text>
            <flux:button variant="subtle" size="sm" icon="arrow-top-right-on-square" href="{{ $result->file_url }}" target="_blank" rel="noopener noreferrer">
                {{ __('Open in new tab') }}
            </flux:button>
        </div>
    </div>
</x-layouts::app>
