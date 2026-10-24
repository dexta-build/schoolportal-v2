<x-layouts::app :title="$child->name">
    <div class="space-y-6">
        {{-- Breadcrumb --}}
        <div class="flex items-center gap-2">
            <flux:link href="{{ route('parent.dashboard') }}" wire:navigate class="text-sm text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300">
                {{ __('Dashboard') }}
            </flux:link>
            <flux:icon.chevron-right class="w-3 h-3 text-zinc-400" />
            <flux:text class="text-sm">{{ $child->name }}</flux:text>
        </div>

        {{-- Profile Card --}}
        <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 overflow-hidden max-w-2xl">
            <div class="p-6">
                <div class="flex items-center gap-4">
                    <flux:avatar size="xl" :src="$child->avatarProfileUrl()" :name="$child->name" :initials="$child->initials()" />
                    <div class="min-w-0 flex-1">
                        <flux:heading size="xl">{{ $child->name }}</flux:heading>
                        <flux:text class="mt-1 text-zinc-500 dark:text-zinc-400">{{ $child->username }}</flux:text>
                    </div>
                </div>
            </div>

            <div class="border-t border-zinc-200 dark:border-zinc-700">
                <dl class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @if ($child->studentProfile?->class)
                        <div class="px-6 py-3 flex justify-between gap-4">
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Class') }}</dt>
                            <dd class="text-sm text-zinc-900 dark:text-white text-right">{{ $child->studentProfile->class->name }}</dd>
                        </div>
                    @endif

                    @if ($child->studentProfile?->class?->level)
                        <div class="px-6 py-3 flex justify-between gap-4">
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Level') }}</dt>
                            <dd class="text-sm text-zinc-900 dark:text-white text-right">{{ $child->studentProfile->class->level->name }}</dd>
                        </div>
                    @endif

                    @if ($child->studentProfile?->class?->teacher)
                        <div class="px-6 py-3 flex justify-between gap-4">
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Class Teacher') }}</dt>
                            <dd class="text-sm text-zinc-900 dark:text-white text-right">{{ $child->studentProfile->class->teacher->name }}</dd>
                        </div>
                    @endif

                    @if ($child->gender)
                        <div class="px-6 py-3 flex justify-between gap-4">
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Gender') }}</dt>
                            <dd class="text-sm text-zinc-900 dark:text-white text-right capitalize">{{ $child->gender }}</dd>
                        </div>
                    @endif

                    @if ($child->studentProfile?->admission_number)
                        <div class="px-6 py-3 flex justify-between gap-4">
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Admission No.') }}</dt>
                            <dd class="text-sm text-zinc-900 dark:text-white text-right">{{ $child->studentProfile->admission_number }}</dd>
                        </div>
                    @endif

                    @if ($child->studentProfile?->date_of_birth)
                        <div class="px-6 py-3 flex justify-between gap-4">
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Date of Birth') }}</dt>
                            <dd class="text-sm text-zinc-900 dark:text-white text-right">{{ $child->studentProfile->date_of_birth->format('F j, Y') }}</dd>
                        </div>
                    @endif

                    @if ($child->studentProfile?->enrolledSession)
                        <div class="px-6 py-3 flex justify-between gap-4">
                            <dt class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ __('Enrolled Session') }}</dt>
                            <dd class="text-sm text-zinc-900 dark:text-white text-right">{{ $child->studentProfile->enrolledSession->name }}</dd>
                        </div>
                    @endif
                </dl>
            </div>
        </div>

        {{-- Quick Links --}}
        <div class="flex flex-wrap gap-2">
            <flux:button variant="filled" size="sm" icon="document-text" href="{{ route('parent.children.results', $child) }}" wire:navigate>
                {{ __('View Result Sheets') }}
            </flux:button>
            <flux:button variant="filled" size="sm" icon="document-chart-bar" href="{{ route('parent.children.report-cards', $child) }}" wire:navigate>
                {{ __('Report Cards') }}
            </flux:button>
            <flux:button variant="filled" size="sm" icon="clipboard-document-list" href="{{ route('parent.children.assignments', $child) }}" wire:navigate>
                {{ __('View Assignments') }}
            </flux:button>
            <flux:button variant="filled" size="sm" icon="academic-cap" href="{{ route('parent.children.quizzes', $child) }}" wire:navigate>
                {{ __('View Quizzes') }}
            </flux:button>
            <flux:button variant="filled" size="sm" icon="puzzle-piece" href="{{ route('parent.children.games', $child) }}" wire:navigate>
                {{ __('View Games') }}
            </flux:button>
            @if (app('current.school')->featureEnabled('enable_cbt_results_for_parents'))
            <flux:button variant="filled" size="sm" icon="computer-desktop" href="{{ route('parent.children.cbt-results', $child) }}" wire:navigate>
                {{ __('CBT Results') }}
            </flux:button>
            @endif
        </div>
    </div>
</x-layouts::app>
