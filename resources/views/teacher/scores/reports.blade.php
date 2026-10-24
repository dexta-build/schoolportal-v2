<x-layouts::app :title="__('Term Reports')">
    <div class="space-y-6">
        <x-score-workflow-steps current="reports" />

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">{{ __('Term Reports') }}</h1>
                <p class="text-sm text-zinc-500">{{ __('Add comments and submit report cards for approval.') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <x-page-help topic="report-cards" />
                @if ($selectedClassId && $selectedTermId)
                    <flux:button variant="subtle" size="sm" icon="pencil-square" href="{{ route('teacher.scores.reports.bulk-edit-data', ['class_id' => $selectedClassId, 'term_id' => $selectedTermId]) }}" wire:navigate>
                        {{ __('Bulk Edit Report Data') }}
                    </flux:button>
                @endif
                @if ($selectedClassId)
                    <div x-data="{ showGenerateModal: false, reportType: 'full_term' }">
                        <flux:button variant="primary" size="sm" icon="sparkles" @click="showGenerateModal = true">
                            {{ __('Generate Report Cards') }}
                        </flux:button>

                        <div x-show="showGenerateModal" x-cloak @keydown.escape.window="showGenerateModal = false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                            <div @click.outside="showGenerateModal = false" class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-zinc-800 text-left">
                                <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Generate Report Cards') }}</h3>
                                <p class="mt-1 text-sm text-zinc-500">{{ __('Select the report type to generate for this class.') }}</p>

                                <form method="POST" action="{{ route('teacher.scores.reports.generate') }}" class="mt-4 space-y-4">
                                    @csrf
                                    <input type="hidden" name="class_id" value="{{ $selectedClassId }}">

                                    <fieldset class="space-y-2">
                                        <legend class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Report Type') }}</legend>
                                        @if (in_array('midterm', $enabledReportTypes))
                                            <label class="flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50" :class="reportType === 'midterm' && 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20'">
                                                <input type="radio" name="report_type" value="midterm" x-model="reportType" class="text-indigo-600">
                                                <div>
                                                    <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Mid-Term Report') }}</div>
                                                    <div class="text-xs text-zinc-500">{{ __('Based on current term scores only') }}</div>
                                                </div>
                                            </label>
                                        @endif
                                        @if (in_array('full_term', $enabledReportTypes))
                                            <label class="flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50" :class="reportType === 'full_term' && 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20'">
                                                <input type="radio" name="report_type" value="full_term" x-model="reportType" class="text-indigo-600">
                                                <div>
                                                    <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Full Term Report') }}</div>
                                                    <div class="text-xs text-zinc-500">{{ __('Complete term report with all components') }}</div>
                                                </div>
                                            </label>
                                        @endif
                                        @if (in_array('session', $enabledReportTypes))
                                            <label class="flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50" :class="reportType === 'session' && 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20'">
                                                <input type="radio" name="report_type" value="session" x-model="reportType" class="text-indigo-600">
                                                <div>
                                                    <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Session Report') }}</div>
                                                    <div class="text-xs text-zinc-500">{{ __('Aggregated report across all terms in a session') }}</div>
                                                </div>
                                            </label>
                                        @endif
                                    </fieldset>

                                    <div x-show="reportType !== 'session'">
                                        <input type="hidden" name="term_id" value="{{ $selectedTermId }}">
                                        <p class="text-sm text-zinc-500"><span class="font-medium">{{ __('Term:') }}</span> {{ $selectedTerm?->name ?? 'Current' }}</p>
                                    </div>

                                    <div x-show="reportType === 'session'" x-cloak>
                                        <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Session') }}</label>
                                        <select name="session_id" class="w-full rounded-lg border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 text-sm">
                                            @foreach ($sessions as $session)
                                                <option value="{{ $session->id }}" @selected($session->is_current)>{{ $session->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="flex justify-end gap-2 pt-2">
                                        <flux:button variant="subtle" size="sm" @click="showGenerateModal = false" type="button">{{ __('Cancel') }}</flux:button>
                                        <flux:button variant="primary" size="sm" icon="document-text" type="submit">{{ __('Generate') }}</flux:button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
                <flux:button variant="subtle" size="sm" icon="arrow-left" href="{{ route('teacher.scores.index', ['class_id' => $selectedClassId, 'term_id' => $selectedTermId]) }}" wire:navigate>
                    {{ __('Back to Scores') }}
                </flux:button>
            </div>
        </div>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif
        @if (session('error'))
            <flux:callout variant="danger" icon="x-circle">{{ session('error') }}</flux:callout>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('teacher.scores.reports') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <flux:label>{{ __('Class') }}</flux:label>
                <flux:select name="class_id" onchange="this.form.submit()">
                    <option value="">{{ __('Select Class') }}</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected($selectedClassId == $class->id)>{{ $class->name }}</option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:label>{{ __('Term') }}</flux:label>
                <flux:select name="term_id" onchange="this.form.submit()">
                    <option value="">{{ __('Select Term') }}</option>
                    @foreach ($sessions as $session)
                        @foreach ($session->terms as $term)
                            <option value="{{ $term->id }}" @selected($selectedTermId == $term->id)>{{ $session->name }} — {{ $term->name }}</option>
                        @endforeach
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:label>{{ __('Report Type') }}</flux:label>
                <flux:select name="report_type" onchange="this.form.submit()">
                    <option value="">{{ __('All Types') }}</option>
                    @foreach ($enabledReportTypes as $type)
                        <option value="{{ $type }}" @selected($selectedReportType === $type)>{{ __(ucwords(str_replace('_', ' ', $type))) }}</option>
                    @endforeach
                </flux:select>
            </div>
        </form>

        @if ($reports->isNotEmpty())
            {{-- Status summary & Export buttons --}}
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2 text-sm text-zinc-500">
                    @php $statusCounts = $reports->groupBy('status')->map->count(); @endphp
                    <span>{{ $reports->count() }} {{ __('reports') }}:</span>
                    @foreach ($statusCounts as $status => $count)
                        <flux:badge size="sm" :color="match($status) { 'draft' => 'zinc', 'pending_approval' => 'amber', 'approved' => 'blue', 'published' => 'green', default => 'zinc' }">
                            {{ ucfirst(str_replace('_', ' ', $status)) }}: {{ $count }}
                        </flux:badge>
                    @endforeach
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('teacher.scores.reports.export-csv', ['class_id' => $selectedClassId, 'term_id' => $selectedTermId, 'report_type' => $selectedReportType]) }}">
                        <flux:button variant="subtle" size="sm" icon="table-cells">
                            {{ __('Export CSV') }}
                        </flux:button>
                    </a>
                    <a href="{{ route('teacher.scores.reports.download-all', ['class_id' => $selectedClassId, 'term_id' => $selectedTermId, 'report_type' => $selectedReportType]) }}">
                        <flux:button variant="subtle" size="sm" icon="arrow-down-tray">
                            {{ __('Download All PDFs') }}
                        </flux:button>
                    </a>
                </div>
            </div>

            {{-- Bulk Comment Form --}}
            @if ($statusCounts->has('draft'))
                <form method="POST" action="{{ route('teacher.scores.reports.bulk-submit') }}" id="bulkForm">
                    @csrf
                    <input type="hidden" name="class_id" value="{{ $selectedClassId }}">
                    <input type="hidden" name="term_id" value="{{ $selectedTermId }}">
            @endif

            {{-- Reports List --}}
            <div class="space-y-3">
                @foreach ($reports as $report)
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 overflow-hidden">
                        <div class="flex items-center justify-between px-4 py-3 bg-zinc-50 dark:bg-zinc-700/50 border-b border-zinc-200 dark:border-zinc-700">
                            <div class="flex items-center gap-3">
                                @if ($config?->show_position)
                                <span class="inline-flex items-center justify-center size-8 rounded-full {{ ($report->position ?? 99) <= 3 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 font-bold' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' }} text-sm">
                                    {{ $report->position ?? '—' }}
                                </span>
                                @endif
                                <div>
                                    <span class="font-medium text-zinc-900 dark:text-white">{{ $report->student->name ?? 'Unknown' }}</span>
                                    <span class="ml-2 text-sm text-indigo-600 dark:text-indigo-400 font-semibold">{{ number_format($report->average_weighted_score ?? 0, 1) }}%</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                @php
                                    $statusColor = match($report->status) {
                                        'draft' => 'zinc',
                                        'pending_approval' => 'amber',
                                        'approved' => 'blue',
                                        'published' => 'green',
                                        default => 'zinc',
                                    };
                                @endphp
                                <flux:badge size="sm" :color="$statusColor">{{ ucfirst(str_replace('_', ' ', $report->status)) }}</flux:badge>
                                @if ($report->report_type && $report->report_type !== 'full_term')
                                    <flux:badge size="sm" :color="$report->report_type === 'midterm' ? 'sky' : 'violet'">
                                        {{ ucwords(str_replace('_', ' ', $report->report_type)) }}
                                    </flux:badge>
                                @endif
                                <x-row-actions :label="__('Actions for :name\'s report card', ['name' => $report->student->name ?? __('student')])">
                                    <flux:menu.item icon="eye" href="{{ route('teacher.scores.reports.show', $report) }}" wire:navigate>{{ __('View report card') }}</flux:menu.item>
                                    @if (!in_array($report->status, ['approved', 'published']))
                                        <flux:menu.item icon="pencil-square" href="{{ route('teacher.scores.reports.edit-data', $report) }}" wire:navigate>{{ __('Edit comments & attendance') }}</flux:menu.item>
                                    @endif
                                    <flux:menu.item icon="arrow-down-tray" href="{{ route('teacher.scores.reports.download', $report) }}">{{ __('Download PDF') }}</flux:menu.item>
                                </x-row-actions>
                            </div>
                        </div>

                        <div class="px-4 py-3">
                            @if ($report->status === 'draft')
                                {{-- Comment input for draft reports --}}
                                <div class="space-y-2">
                                    <flux:label>{{ __('Your Comment') }}</flux:label>
                                    <div class="flex gap-2">
                                        <flux:textarea name="comments[{{ $report->student_id }}]" rows="2" class="flex-1" :placeholder="__('Add your comment about this student...')">{{ $report->teacher_comment }}</flux:textarea>
                                        <form method="POST" action="{{ route('teacher.scores.reports.comment', $report) }}" class="flex items-end">
                                            @csrf
                                            <input type="hidden" name="teacher_comment" value="" x-data x-init="$el.value = $el.closest('.space-y-2').querySelector('textarea').value" x-effect="$el.value = $el.closest('.space-y-2').querySelector('textarea').value">
                                            <flux:button variant="primary" size="sm" type="submit" icon="paper-airplane">{{ __('Submit') }}</flux:button>
                                        </form>
                                    </div>
                                </div>
                            @else
                                {{-- Show existing comments --}}
                                @if ($report->teacher_comment)
                                    <div class="text-sm">
                                        <span class="text-zinc-400 text-xs font-medium">{{ __("Teacher's Comment:") }}</span>
                                        <p class="text-zinc-600 dark:text-zinc-300 italic mt-0.5">{{ $report->teacher_comment }}</p>
                                    </div>
                                @endif
                                @if ($report->principal_comment)
                                    <div class="text-sm mt-2">
                                        <span class="text-zinc-400 text-xs font-medium">{{ __("Principal's Comment:") }}</span>
                                        <p class="text-zinc-600 dark:text-zinc-300 italic mt-0.5">{{ $report->principal_comment }}</p>
                                    </div>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($statusCounts->has('draft'))
                <div class="flex justify-end">
                    <flux:button variant="primary" icon="paper-airplane" type="submit" form="bulkForm">
                        {{ __('Submit All Draft Reports for Approval') }}
                    </flux:button>
                </div>
                </form>
            @endif
        @elseif ($selectedClassId)
            <div class="rounded-lg border border-dashed border-zinc-300 dark:border-zinc-600 p-8 text-center">
                <flux:icon name="document-text" class="mx-auto size-10 text-zinc-400 mb-3" />
                <p class="text-sm text-zinc-500">{{ __('No reports found. Use the "Generate Report Cards" button above to create them.') }}</p>
            </div>
        @else
            <div class="rounded-lg border border-dashed border-zinc-300 dark:border-zinc-600 p-8 text-center">
                <flux:icon name="funnel" class="mx-auto size-10 text-zinc-400 mb-3" />
                <p class="text-sm text-zinc-500">{{ __('Select a class and term to view reports.') }}</p>
            </div>
        @endif
    </div>
</x-layouts::app>
