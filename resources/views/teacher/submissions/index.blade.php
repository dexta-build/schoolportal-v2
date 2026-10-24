<x-layouts::app :title="__('My Submissions')">
    <div class="space-y-6">
        <x-admin-header :title="__('My Submissions')" :description="__('Track the approval status of all your submitted content.')" />

        {{-- Status tabs --}}
        <div class="flex flex-wrap gap-2" role="tablist" aria-label="{{ __('Status filter') }}">
            <flux:button
                variant="{{ !request('status') ? 'filled' : 'subtle' }}"
                size="sm"
                href="{{ route('teacher.submissions.index') }}"
                wire:navigate
                :aria-selected="!request('status') ? 'true' : 'false'"
            >
                {{ __('All') }} ({{ $counts['all'] }})
            </flux:button>
            <flux:button
                variant="{{ request('status') === 'pending' ? 'filled' : 'subtle' }}"
                size="sm"
                href="{{ route('teacher.submissions.index', ['status' => 'pending']) }}"
                wire:navigate
            >
                {{ __('Pending') }} ({{ $counts['pending'] }})
            </flux:button>
            <flux:button
                variant="{{ request('status') === 'approved' ? 'filled' : 'subtle' }}"
                size="sm"
                href="{{ route('teacher.submissions.index', ['status' => 'approved']) }}"
                wire:navigate
            >
                {{ __('Approved') }} ({{ $counts['approved'] }})
            </flux:button>
            <flux:button
                variant="{{ request('status') === 'rejected' ? 'filled' : 'subtle' }}"
                size="sm"
                href="{{ route('teacher.submissions.index', ['status' => 'rejected']) }}"
                wire:navigate
            >
                {{ __('Rejected') }} ({{ $counts['rejected'] }})
            </flux:button>
        </div>

        {{-- Type filter --}}
        <div class="flex flex-wrap items-end gap-3">
            <flux:select name="type" onchange="window.location.href = this.value ? '{{ route('teacher.submissions.index') }}?type=' + this.value + '{{ request('status') ? '&status=' . request('status') : '' }}' : '{{ route('teacher.submissions.index') }}{{ request('status') ? '?status=' . request('status') : '' }}'">
                <option value="">{{ __('All Types') }}</option>
                <option value="result" @selected(request('type') === 'result')>{{ __('Result Sheets (PDF)') }}</option>
                <option value="assignment" @selected(request('type') === 'assignment')>{{ __('Assignments') }}</option>
                <option value="notice" @selected(request('type') === 'notice')>{{ __('Notices') }}</option>
                <option value="quiz" @selected(request('type') === 'quiz')>{{ __('Quizzes') }}</option>
                <option value="game" @selected(request('type') === 'game')>{{ __('Games') }}</option>
                <option value="exam" @selected(request('type') === 'exam')>{{ __('CBT Exams / Assessments / Assignments') }}</option>
                <option value="report_card" @selected(request('type') === 'report_card')>{{ __('Report Cards') }}</option>
            </flux:select>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Type') }}</flux:table.column>
                <flux:table.column>{{ __('Action') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column class="hidden sm:table-cell">{{ __('Reviewed By') }}</flux:table.column>
                <flux:table.column class="hidden md:table-cell">{{ __('Reason') }}</flux:table.column>
                <flux:table.column>{{ __('Submitted') }}</flux:table.column>
                <flux:table.column class="text-right">{{ __('') }}</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($submissions as $submission)
                    <flux:table.row>
                        <flux:table.cell>
                            @switch($submission->entity_type)
                                @case('result')
                                    <flux:badge color="blue" size="sm">{{ __('Result') }}</flux:badge>
                                    @break
                                @case('assignment')
                                    <flux:badge color="purple" size="sm">{{ __('Assignment') }}</flux:badge>
                                    @break
                                @case('notice')
                                    <flux:badge color="cyan" size="sm">{{ __('Notice') }}</flux:badge>
                                    @break
                                @case('quiz')
                                    <flux:badge color="amber" size="sm">{{ __('Quiz') }}</flux:badge>
                                    @break
                                @case('game')
                                    <flux:badge color="emerald" size="sm">{{ __('Game') }}</flux:badge>
                                    @break
                                @case('exam')
                                    @php
                                        $examEntity = $examEntities->get($submission->entity_id);
                                        $examCategoryLabel = match ($examEntity?->category ?? 'exam') {
                                            'assessment' => __('Assessment'),
                                            'assignment' => __('CBT Assignment'),
                                            default => __('CBT Exam'),
                                        };
                                    @endphp
                                    <flux:badge color="indigo" size="sm">{{ $examCategoryLabel }}</flux:badge>
                                    @break
                                @case('report_card')
                                    <flux:badge color="teal" size="sm">{{ __('Report Card') }}</flux:badge>
                                    @break
                            @endswitch
                        </flux:table.cell>
                        <flux:table.cell class="capitalize">{{ str_replace('_', ' ', $submission->action_type) }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($submission->status === 'approved')
                                <flux:badge color="green" size="sm">{{ __('Approved') }}</flux:badge>
                            @elseif ($submission->status === 'pending')
                                <flux:badge color="yellow" size="sm">{{ __('Pending') }}</flux:badge>
                            @else
                                <flux:badge color="red" size="sm">{{ __('Rejected') }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="hidden sm:table-cell text-zinc-500">
                            {{ $submission->reviewer?->name ?? '—' }}
                        </flux:table.cell>
                        <flux:table.cell class="hidden md:table-cell text-zinc-500 text-sm">
                            @if ($submission->rejection_reason)
                                {{ Str::limit($submission->rejection_reason, 80) }}
                            @else
                                —
                            @endif
                        </flux:table.cell>
                        <flux:table.cell class="text-zinc-500">{{ $submission->created_at->format('M j, Y') }}</flux:table.cell>
                        <flux:table.cell class="text-right">
                            @php
                                $viewRoute = match($submission->entity_type) {
                                    'result' => route('teacher.results.index'),
                                    'assignment' => route('teacher.assignments.index'),
                                    'notice' => route('teacher.notices.index'),
                                    'quiz' => route('teacher.quizzes.show', $submission->entity_id),
                                    'game' => route('teacher.games.show', $submission->entity_id),
                                    'exam' => ($examEntities->get($submission->entity_id)
                                        ? route('teacher.exams.show', $examEntities->get($submission->entity_id))
                                        : null),
                                    'report_card' => route('teacher.scores.reports'),
                                    default => null,
                                };
                            @endphp
                            @if ($viewRoute)
                                <flux:button variant="subtle" size="xs" icon="eye" :href="$viewRoute" wire:navigate>
                                    {{ __('View') }}
                                </flux:button>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="text-center py-8">
                            {{ __('No submissions yet.') }}
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        {{ $submissions->links() }}
    </div>
</x-layouts::app>
