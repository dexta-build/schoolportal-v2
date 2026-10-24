<x-layouts::app :title="__('Result Details')">
    <div class="space-y-6">
        <x-admin-header :title="__('Result Details')">
            <flux:button variant="subtle" size="sm" href="{{ route('admin.results.index') }}" wire:navigate icon="arrow-left">
                {{ __('Back to Result Sheets') }}
            </flux:button>
            <flux:button variant="subtle" size="sm" href="{{ route('admin.results.edit', $result) }}" wire:navigate icon="arrow-path">
                {{ __('Replace Result') }}
            </flux:button>
        </x-admin-header>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif
        @if (session('error'))
            <flux:callout variant="danger" icon="exclamation-circle">{{ session('error') }}</flux:callout>
        @endif

        <div class="max-w-2xl rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
            <dl class="space-y-4 text-sm">
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Student') }}</dt>
                    <dd class="font-medium">{{ $result->student?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Class') }}</dt>
                    <dd class="font-medium">{{ $result->class?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Session') }}</dt>
                    <dd class="font-medium">{{ $result->session?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Term') }}</dt>
                    <dd class="font-medium">{{ $result->term?->name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Status') }}</dt>
                    <dd>
                        @if ($result->status === 'approved')
                            <flux:badge color="green" size="sm">{{ __('Approved') }}</flux:badge>
                        @elseif ($result->status === 'pending')
                            <flux:badge color="yellow" size="sm">{{ __('Pending') }}</flux:badge>
                        @else
                            <flux:badge color="red" size="sm">{{ __('Rejected') }}</flux:badge>
                        @endif
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Uploaded By') }}</dt>
                    <dd class="font-medium">{{ $result->uploader?->name ?? '—' }}</dd>
                </div>
                @if ($result->approver)
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Approved By') }}</dt>
                        <dd class="font-medium">{{ $result->approver->name }}</dd>
                    </div>
                @endif
                <div class="flex justify-between">
                    <dt class="text-zinc-500">{{ __('Uploaded') }}</dt>
                    <dd class="font-medium">{{ $result->created_at->format('M j, Y g:i A') }}</dd>
                </div>
                @if ($result->notes)
                    <div>
                        <dt class="text-zinc-500 mb-1">{{ __('Notes / Replacement Reason') }}</dt>
                        <dd class="font-medium">{{ $result->notes }}</dd>
                    </div>
                @endif
            </dl>

            @if ($result->file_url)
                <div class="mt-6 pt-6 border-t border-zinc-200 dark:border-zinc-700">
                    <flux:button variant="primary" href="{{ $result->file_url }}" target="_blank" icon="arrow-down-tray">
                        {{ __('View/Download Result PDF') }}
                    </flux:button>
                </div>
            @endif
        </div>
    </div>
</x-layouts::app>
