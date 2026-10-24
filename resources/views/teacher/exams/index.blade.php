<x-layouts::app :title="__('CBT')">
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white">{{ __('CBT') }}</h1>
                <p class="mt-1 text-sm text-zinc-500">{{ __('Create and manage exams, assessments, and assignments for your assigned classes.') }}</p>
            </div>
            <flux:dropdown>
                <flux:button variant="primary" icon-trailing="chevron-down">{{ __('Create') }}</flux:button>
                <flux:menu>
                    <flux:menu.item icon="document-text" href="{{ route('teacher.exams.create') }}" wire:navigate>{{ __('Create Exam') }}</flux:menu.item>
                    <flux:menu.item icon="clipboard-document-check" href="{{ route('teacher.exams.create', ['category' => 'assessment']) }}" wire:navigate>{{ __('Create Assessment') }}</flux:menu.item>
                    <flux:menu.item icon="document-arrow-up" href="{{ route('teacher.exams.create', ['category' => 'assignment']) }}" wire:navigate>{{ __('Create Assignment') }}</flux:menu.item>
                </flux:menu>
            </flux:dropdown>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif
        @if (session('error'))
            <flux:callout variant="danger" icon="x-circle">{{ session('error') }}</flux:callout>
        @endif

        {{-- Category Tabs --}}
        @php
            $currentCategory = request('category');
            $tabs = [
                null => __('All'),
                'exam' => __('Exams'),
                'assessment' => __('Assessments'),
                'assignment' => __('Assignments'),
            ];
        @endphp
        <div class="flex gap-1 overflow-x-auto border-b border-zinc-200 dark:border-zinc-700" role="tablist" aria-label="{{ __('Category filter') }}">
            @foreach ($tabs as $tabValue => $tabLabel)
                @php
                    $isActive = $currentCategory === ($tabValue ?: null);
                    $tabUrl = route('teacher.exams.index', array_filter(array_merge(request()->except('category', 'page'), $tabValue ? ['category' => $tabValue] : [])));
                @endphp
                <a href="{{ $tabUrl }}"
                   role="tab"
                   aria-selected="{{ $isActive ? 'true' : 'false' }}"
                   @if ($isActive) aria-current="page" @endif
                   class="shrink-0 px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors {{ $isActive ? 'border-indigo-600 text-indigo-600 dark:border-indigo-400 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300' }}"
                   wire:navigate>
                    {{ $tabLabel }}
                </a>
            @endforeach
        </div>

        {{-- Filters --}}
        <div class="flex flex-wrap gap-3">
            <form method="GET" class="flex flex-wrap items-end gap-3">
                @if ($currentCategory)
                    <input type="hidden" name="category" value="{{ $currentCategory }}">
                @endif
                <flux:select name="class_id" label="{{ __('Class') }}" class="w-40">
                    <option value="">{{ __('All Classes') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </flux:select>
                <flux:select name="status" label="{{ __('Status') }}" class="w-32">
                    <option value="">{{ __('All') }}</option>
                    <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                    <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                    <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                    <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
                </flux:select>
                <flux:button type="submit" variant="subtle" icon="funnel" size="sm">{{ __('Filter') }}</flux:button>
                @if (request()->hasAny(['class_id', 'status']))
                    <flux:button href="{{ route('teacher.exams.index', $currentCategory ? ['category' => $currentCategory] : []) }}" variant="ghost" size="sm">{{ __('Clear') }}</flux:button>
                @endif
            </form>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Title') }}</flux:table.column>
                @unless ($currentCategory)
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                @endunless
                <flux:table.column>{{ __('Subject') }}</flux:table.column>
                <flux:table.column>{{ __('Class') }}</flux:table.column>
                <flux:table.column class="text-center">{{ __('Questions') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column class="w-32" />
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($exams as $exam)
                    @php
                        $examRoutePrefix = 'teacher.exams';
                        $examTypeLabel = match ($exam->category) {
                            'assessment' => __('Assessment'),
                            'assignment' => __('Assignment'),
                            default => __('Exam'),
                        };
                        $typeColors = ['assessment' => 'sky', 'assignment' => 'amber', 'exam' => 'indigo'];
                    @endphp
                    <flux:table.row>
                        <flux:table.cell class="font-medium">
                            <a href="{{ route($examRoutePrefix . '.show', $exam) }}" class="hover:underline" wire:navigate>{{ $exam->title }}</a>
                            @if ($exam->is_published)
                                <flux:badge color="green" size="sm" class="ml-1">{{ __('Live') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        @unless ($currentCategory)
                            <flux:table.cell>
                                <flux:badge :color="$typeColors[$exam->category] ?? 'zinc'" size="sm">{{ $examTypeLabel }}</flux:badge>
                            </flux:table.cell>
                        @endunless
                        <flux:table.cell>{{ $exam->subject?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>{{ $exam->class?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell class="text-center">{{ $exam->questions_count }}</flux:table.cell>
                        <flux:table.cell>
                            @php
                                $statusColors = ['draft' => 'zinc', 'pending' => 'amber', 'approved' => 'green', 'rejected' => 'red'];
                            @endphp
                            <flux:badge :color="$statusColors[$exam->status] ?? 'zinc'" size="sm">{{ ucfirst($exam->status) }}</flux:badge>
                            @if ($exam->latestTeacherAction?->status === 'rejected')
                                <span class="text-xs text-red-500 block mt-0.5" title="{{ $exam->latestTeacherAction->rejection_reason }}">{{ Str::limit($exam->latestTeacherAction->rejection_reason, 40) }}</span>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-end">
                            <div class="flex items-center justify-end gap-1">
                                {{-- Results stay one tap away: it's what teachers open most --}}
                                @if ($exam->is_published)
                                    <flux:button variant="primary" size="xs" icon="chart-bar" href="{{ route($examRoutePrefix . '.results', $exam) }}" wire:navigate>{{ __('Results') }}</flux:button>
                                @endif
                                <x-row-actions :label="__('Actions for :name', ['name' => $exam->title])">
                                    <flux:menu.item icon="eye" href="{{ route($examRoutePrefix . '.show', $exam) }}" wire:navigate>{{ __('View questions') }}</flux:menu.item>
                                    @if (in_array($exam->status, ['draft', 'pending', 'rejected']))
                                        <flux:menu.item icon="pencil-square" href="{{ route($examRoutePrefix . '.edit', $exam) }}" wire:navigate>{{ __('Edit') }}</flux:menu.item>
                                    @endif
                                    @unless ($exam->is_published)
                                        <flux:menu.separator />
                                        <x-row-actions.delete
                                            :action="route($examRoutePrefix . '.destroy', $exam)"
                                            :title="__('Delete :name?', ['name' => $exam->title])"
                                            :message="__('Students will no longer see it. You can undo this straight after deleting, or restore it from Recently deleted within 30 days.')"
                                        />
                                    @endunless
                                </x-row-actions>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell :colspan="$currentCategory ? 6 : 7" class="text-center py-8 text-zinc-500">
                            {{ __('No items found.') }}
                            <a href="{{ route('teacher.exams.index') }}" class="text-indigo-600 hover:underline" wire:navigate>{{ __('View all') }}</a>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <div class="mt-4">{{ $exams->links() }}</div>
    </div>
</x-layouts::app>
