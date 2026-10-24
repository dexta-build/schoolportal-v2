<x-layouts::app :title="__('Emails to Schools')">
    <div class="space-y-6">
        <x-admin-header
            :title="__('Emails to Schools')"
            :description="__('Send rich-text emails directly to school admin email addresses.')"
            :action="route('super-admin.emails.create')"
            :actionLabel="__('Compose Email')"
        />

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Subject') }}</flux:table.column>
                <flux:table.column class="hidden sm:table-cell">{{ __('Recipients') }}</flux:table.column>
                <flux:table.column class="hidden sm:table-cell">{{ __('Delivery') }}</flux:table.column>
                <flux:table.column class="hidden md:table-cell">{{ __('Sent By') }}</flux:table.column>
                <flux:table.column>{{ __('Date') }}</flux:table.column>
                <flux:table.column class="w-16" />
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($emails as $email)
                    <flux:table.row>
                        <flux:table.cell class="font-medium">
                            <a href="{{ route('super-admin.emails.show', $email) }}" class="hover:underline" wire:navigate>
                                {{ Str::limit($email->subject, 50) }}
                            </a>
                        </flux:table.cell>
                        <flux:table.cell class="hidden sm:table-cell text-zinc-500">
                            {{ $email->total_recipients }} {{ Str::plural('school', $email->total_recipients) }}
                        </flux:table.cell>
                        <flux:table.cell class="hidden sm:table-cell">
                            <div class="flex items-center gap-1.5">
                                <span class="inline-flex items-center gap-1 text-green-600 dark:text-green-400">
                                    <span class="size-1.5 rounded-full bg-green-500"></span>
                                    {{ $email->sent_count }}
                                </span>
                                @if ($email->failed_count > 0)
                                    <span class="text-zinc-300 dark:text-zinc-600">/</span>
                                    <span class="inline-flex items-center gap-1 text-red-500 dark:text-red-400">
                                        <span class="size-1.5 rounded-full bg-red-500"></span>
                                        {{ $email->failed_count }}
                                    </span>
                                @endif
                            </div>
                        </flux:table.cell>
                        <flux:table.cell class="hidden md:table-cell text-zinc-500">
                            {{ $email->sender?->name ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">
                            {{ $email->sent_at?->format('M j, Y') ?? $email->created_at->format('M j, Y') }}
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button variant="subtle" size="xs" icon="eye" :href="route('super-admin.emails.show', $email)" wire:navigate>{{ __('View') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="6">
                            <div class="flex flex-col items-center justify-center py-12 text-center">
                                <div class="rounded-full bg-zinc-100 dark:bg-zinc-700 p-3 mb-3">
                                    <flux:icon name="envelope" class="size-6 text-zinc-400 dark:text-zinc-500" />
                                </div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('No emails sent yet') }}</p>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Compose a rich-text email to send directly to school admins.') }}</p>
                                <flux:button variant="primary" size="sm" href="{{ route('super-admin.emails.create') }}" wire:navigate icon="paper-airplane" class="mt-4">
                                    {{ __('Compose Email') }}
                                </flux:button>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{ $emails->links() }}
    </div>
</x-layouts::app>
