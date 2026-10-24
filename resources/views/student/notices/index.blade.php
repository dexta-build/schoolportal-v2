<x-layouts::app :title="__('Notice Board')">
    <div class="space-y-6">
        <x-admin-header :title="__('Notice Board')" :description="__('School announcements and updates.')" />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($notices as $notice)
                <a href="{{ route('student.notices.show', $notice) }}" wire:navigate
                   class="group rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 hover:border-blue-300 dark:hover:border-blue-600 transition-colors overflow-hidden">
                    @if ($notice->image_url)
                        <div class="aspect-video w-full overflow-hidden bg-zinc-100 dark:bg-zinc-700">
                            <img
                                src="{{ $notice->imageCardUrl() }}"
                                alt="{{ $notice->title }}"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                loading="lazy"
                            />
                        </div>
                    @elseif ($notice->file_url && $notice->fileIsImage())
                        <div class="aspect-video w-full overflow-hidden bg-zinc-100 dark:bg-zinc-700">
                            <img
                                src="{{ $notice->file_url }}"
                                alt="{{ $notice->title }}"
                                class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                loading="lazy"
                            />
                        </div>
                    @endif
                    <div class="p-4">
                        <p class="font-medium text-zinc-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors line-clamp-2">
                            <span class="flex items-center gap-1.5">
                                {{ $notice->title }}
                                @if ($notice->file_url && !$notice->fileIsImage())
                                    <flux:icon.paper-clip class="w-4 h-4 text-zinc-400 shrink-0" />
                                @endif
                            </span>
                        </p>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400 mt-1 line-clamp-3">
                            {{ Str::limit(strip_tags($notice->content), 150) }}
                        </flux:text>
                        <div class="flex items-center justify-between mt-3">
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $notice->published_at->format('M j, Y') }}
                            </flux:text>
                            @if ($notice->expires_at)
                                <flux:badge size="sm" color="{{ $notice->expires_at->diffInDays(now()) <= 3 ? 'red' : 'zinc' }}">
                                    {{ __('Expires :date', ['date' => $notice->expires_at->format('M j')]) }}
                                </flux:badge>
                            @endif
                        </div>
                    </div>
                </a>
            @empty
                <div class="col-span-full py-12 text-center">
                    <flux:icon.megaphone class="w-12 h-12 mx-auto text-zinc-300 dark:text-zinc-600" />
                    <flux:heading size="sm" class="mt-4">{{ __('No notices yet') }}</flux:heading>
                    <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('School announcements will appear here when published.') }}
                    </flux:text>
                </div>
            @endforelse
        </div>

        {{ $notices->links() }}
    </div>
</x-layouts::app>
