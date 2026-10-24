<x-layouts::app :title="__('Recently deleted')">
    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('Recently deleted') }}</h1>
            <p class="mt-1 max-w-2xl text-sm text-zinc-500 dark:text-zinc-400">
                @if ($portal === 'teacher')
                    {{ __('Notices and CBT you deleted in the last :days days. Restore anything deleted by mistake — after :days days it is removed for good.', ['days' => \App\Models\Notice::RESTORE_DAYS]) }}
                @else
                    {{ __('Notices, assignments, CBT, result sheets, classes and banners deleted in the last :days days. Restore anything deleted by mistake — after :days days it is removed for good.', ['days' => \App\Models\Notice::RESTORE_DAYS]) }}
                @endif
            </p>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif
        @if (session('error'))
            <flux:callout variant="danger" icon="x-circle">{{ session('error') }}</flux:callout>
        @endif

        @if ($items->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 p-10 text-center dark:border-zinc-700">
                <flux:icon.trash class="mx-auto size-8 text-zinc-300 dark:text-zinc-600" />
                <p class="mt-3 font-medium text-zinc-900 dark:text-white">{{ __('Nothing deleted recently') }}</p>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('Deleted items appear here for :days days.', ['days' => \App\Models\Notice::RESTORE_DAYS]) }}</p>
            </div>
        @else
            <ul class="divide-y divide-zinc-100 overflow-hidden rounded-xl border border-zinc-200 bg-white dark:divide-zinc-700/60 dark:border-zinc-700 dark:bg-zinc-800" role="list">
                @foreach ($items as $item)
                    @php
                        $model = $item['model'];
                        $purgesAt = $model->purgesAt();
                        $daysLeft = max(0, (int) ceil(now()->diffInHours($purgesAt, false) / 24));
                    @endphp
                    <li class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge size="sm" color="zinc">{{ $item['label'] }}</flux:badge>
                                <p class="font-medium text-zinc-900 dark:text-white">{{ $model->undoableTitle() }}</p>
                            </div>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ __('Deleted :when', ['when' => $model->deleted_at->diffForHumans()]) }}
                                @if ($model->deleter)
                                    {{ __('by :name', ['name' => $model->deleter->name]) }}
                                @endif
                                &middot;
                                <span @class(['font-medium text-amber-700 dark:text-amber-400' => $daysLeft <= 3])>
                                    {{ trans_choice('Removed for good in :count day|Removed for good in :count days', $daysLeft, ['count' => $daysLeft]) }}
                                </span>
                            </p>
                        </div>
                        <form method="POST" action="{{ route($portal.'.recently-deleted.restore', ['type' => $item['type'], 'id' => $model->getKey()]) }}" class="shrink-0">
                            @csrf
                            <flux:button type="submit" size="sm" icon="arrow-uturn-left" class="w-full sm:w-auto">{{ __('Restore') }}</flux:button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts::app>
