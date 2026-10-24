<x-layouts::app :title="__('Term Reports')">
    <div class="space-y-6">
        <x-score-workflow-steps current="reports" />

        <x-admin-header
            :title="__('Term Report Cards')"
            :description="__('Review, approve, and publish student report cards.')"
        >
            <x-page-help topic="report-cards" />
        </x-admin-header>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif
        @if (session('error'))
            <flux:callout variant="danger" icon="x-circle">{{ session('error') }}</flux:callout>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.scores.reports') }}" class="flex flex-wrap items-end gap-4">
            <div>
                <flux:label>{{ __('Class') }}</flux:label>
                <flux:select name="class_id" onchange="this.form.submit()">
                    <option value="">{{ __('Select Class') }}</option>
                    @foreach ($levels as $level)
                        @if ($level->classes->isNotEmpty())
                            <optgroup label="{{ $level->name }}">
                                @foreach ($level->classes as $cls)
                                    <option value="{{ $cls->id }}" @selected($selectedClassId == $cls->id)>{{ $cls->name }}</option>
                                @endforeach
                            </optgroup>
                        @endif
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:label>{{ __('Session & Term') }}</flux:label>
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

        {{-- Export Actions — always visible when class is selected --}}
        @if ($selectedClassId && $reports->isNotEmpty())
            <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/50 px-4 py-3">
                <div class="flex items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                    <flux:icon name="arrow-down-tray" class="size-4" />
                    <span class="font-medium">{{ __('Export') }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ route('admin.scores.reports.export-csv', ['class_id' => $selectedClassId, 'term_id' => $selectedTermId, 'report_type' => $selectedReportType]) }}">
                        <flux:button variant="subtle" size="sm" icon="table-cells">
                            {{ __('Export CSV') }}
                        </flux:button>
                    </a>
                    <a href="{{ route('admin.scores.reports.download-all', ['class_id' => $selectedClassId, 'term_id' => $selectedTermId, 'report_type' => $selectedReportType]) }}">
                        <flux:button variant="primary" size="sm" icon="arrow-down-tray">
                            {{ __('Download All PDFs') }}
                        </flux:button>
                    </a>
                </div>
            </div>
        @endif

        {{-- Report Cards List --}}
        @if ($reports->isNotEmpty())
                {{-- Bulk Actions --}}
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2 text-sm text-zinc-500">
                        <flux:icon name="document-text" class="size-4" />
                        <span>{{ $reports->count() }} {{ __('reports') }}</span>
                        @php
                            $statusCounts = $reports->groupBy('status')->map->count();
                        @endphp
                        @foreach ($statusCounts as $status => $count)
                            <flux:badge size="sm" :color="match($status) { 'draft' => 'zinc', 'pending_approval' => 'amber', 'approved' => 'blue', 'published' => 'green', default => 'zinc' }">
                                {{ ucfirst(str_replace('_', ' ', $status)) }}: {{ $count }}
                            </flux:badge>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-2">
                        <div x-data="generateReportModal()" x-init="init()">
                            <flux:button variant="subtle" size="sm" icon="document-text" @click="openModal()">{{ __('Generate') }}</flux:button>

                            <div x-show="showModal" x-cloak @keydown.escape.window="showModal = false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                                <div @click.outside="showModal = false" class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-zinc-800 text-left max-h-[90vh] overflow-y-auto">
                                    <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Generate Report Cards') }}</h3>
                                    <p class="mt-1 text-sm text-zinc-500">{{ __('Choose class, scope, and report type.') }}</p>

                                    <form method="POST" action="{{ route('admin.scores.generate-reports') }}" class="mt-4 space-y-4">
                                        @csrf
                                        <div>
                                            <flux:label>{{ __('Class') }}</flux:label>
                                            <flux:select name="class_id" x-model="selectedClassId" x-on:change="onClassChange()">
                                                <option value="">{{ __('Select a class...') }}</option>
                                                @foreach ($levels as $level)
                                                    @if ($level->classes->isNotEmpty())
                                                        <optgroup label="{{ $level->name }}">
                                                            @foreach ($level->classes as $cls)
                                                                <option value="{{ $cls->id }}" @selected($selectedClassId == $cls->id)>{{ $cls->name }}</option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif
                                                @endforeach
                                            </flux:select>
                                        </div>
                                        <div x-show="selectedClassId">
                                            <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">{{ __('Generate For') }}</label>
                                            <div class="flex gap-3">
                                                <label class="flex-1 flex items-center gap-2 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50" :class="scope === 'bulk' && 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20'">
                                                    <input type="radio" name="generation_scope" value="bulk" x-model="scope" class="text-indigo-600">
                                                    <div>
                                                        <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('All Students') }}</div>
                                                        <div class="text-xs text-zinc-500">{{ __('Bulk') }}</div>
                                                    </div>
                                                </label>
                                                <label class="flex-1 flex items-center gap-2 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50" :class="scope === 'single' && 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20'">
                                                    <input type="radio" name="generation_scope" value="single" x-model="scope" class="text-indigo-600">
                                                    <div>
                                                        <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Single Student') }}</div>
                                                        <div class="text-xs text-zinc-500">{{ __('One student') }}</div>
                                                    </div>
                                                </label>
                                            </div>
                                        </div>
                                        <div x-show="scope === 'single' && selectedClassId" x-cloak>
                                            <flux:label>{{ __('Student') }}</flux:label>
                                            <div class="relative">
                                                <flux:input type="text" x-model="studentSearch" x-on:input="filterStudents()" placeholder="{{ __('Search student...') }}" />
                                            </div>
                                            <input type="hidden" name="student_id" :value="selectedStudentId">
                                            <div x-show="filteredStudents.length > 0 && !selectedStudentId" class="mt-1 max-h-40 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
                                                <template x-for="student in filteredStudents" :key="student.id">
                                                    <button type="button" @click="selectStudent(student)" class="w-full px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700/50 flex items-center justify-between">
                                                        <span x-text="student.name" class="font-medium text-zinc-900 dark:text-white"></span>
                                                        <span x-text="student.admission_number" class="text-xs text-zinc-400"></span>
                                                    </button>
                                                </template>
                                            </div>
                                            <div x-show="selectedStudentId" class="mt-2 flex items-center gap-2 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 px-3 py-2">
                                                <flux:icon name="user" class="size-4 text-indigo-500" />
                                                <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300" x-text="selectedStudentName"></span>
                                                <button type="button" @click="clearStudent()" class="ml-auto text-indigo-400 hover:text-indigo-600">
                                                    <flux:icon name="x-mark" class="size-4" />
                                                </button>
                                            </div>
                                        </div>
                                        <div x-show="selectedClassId">
                                            <fieldset class="space-y-2">
                                                <legend class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Report Type') }}</legend>
                                                @if (in_array('midterm', $enabledReportTypes))
                                                    <label class="flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50" :class="reportType === 'midterm' && 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20'">
                                                        <input type="radio" name="report_type" value="midterm" x-model="reportType" class="text-indigo-600">
                                                        <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Mid-Term') }}</span>
                                                    </label>
                                                @endif
                                                @if (in_array('full_term', $enabledReportTypes))
                                                    <label class="flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50" :class="reportType === 'full_term' && 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20'">
                                                        <input type="radio" name="report_type" value="full_term" x-model="reportType" class="text-indigo-600">
                                                        <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Full Term') }}</span>
                                                    </label>
                                                @endif
                                                @if (in_array('session', $enabledReportTypes))
                                                    <label class="flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50" :class="reportType === 'session' && 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20'">
                                                        <input type="radio" name="report_type" value="session" x-model="reportType" class="text-indigo-600">
                                                        <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Session') }}</span>
                                                    </label>
                                                @endif
                                            </fieldset>
                                        </div>
                                        <div x-show="selectedClassId && reportType !== 'session'">
                                            <input type="hidden" name="term_id" value="{{ $selectedTermId }}">
                                            <p class="text-sm text-zinc-500"><span class="font-medium">{{ __('Term:') }}</span> {{ $selectedTerm?->name ?? 'Current' }}</p>
                                        </div>
                                        <div x-show="selectedClassId && reportType === 'session'" x-cloak>
                                            <flux:label>{{ __('Session') }}</flux:label>
                                            <flux:select name="session_id">
                                                @foreach ($sessions as $session)
                                                    <option value="{{ $session->id }}" @selected($session->is_current)>{{ $session->name }}</option>
                                                @endforeach
                                            </flux:select>
                                        </div>
                                        <div class="flex justify-end gap-2 pt-2">
                                            <flux:button variant="subtle" size="sm" @click="showModal = false" type="button">{{ __('Cancel') }}</flux:button>
                                            <flux:button variant="primary" size="sm" icon="document-text" type="submit" x-bind:disabled="!selectedClassId || (scope === 'single' && !selectedStudentId)">{{ __('Generate') }}</flux:button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <flux:button variant="subtle" size="sm" icon="pencil-square" href="{{ route('admin.scores.reports.bulk-edit-data', ['class_id' => $selectedClassId, 'term_id' => $selectedTermId]) }}" wire:navigate>
                            {{ __('Bulk Edit Report Data') }}
                        </flux:button>
                        @if ($statusCounts->has('pending_approval'))
                            <form id="bulk-approve-form" method="POST" action="{{ route('admin.scores.reports.bulk-approve') }}">
                                @csrf
                                <input type="hidden" name="class_id" value="{{ $selectedClassId }}">
                                <input type="hidden" name="term_id" value="{{ $selectedTermId }}">
                                <flux:modal.trigger name="confirm-bulk-approve">
                                    <flux:button variant="primary" size="sm" icon="check-badge" type="button">
                                        {{ __('Bulk Approve') }} ({{ $statusCounts->get('pending_approval', 0) }})
                                    </flux:button>
                                </flux:modal.trigger>
                            </form>
                        @endif
                        @if ($statusCounts->has('approved'))
                            <form id="publish-form" method="POST" action="{{ route('admin.scores.reports.publish') }}">
                                @csrf
                                <input type="hidden" name="class_id" value="{{ $selectedClassId }}">
                                <input type="hidden" name="term_id" value="{{ $selectedTermId }}">
                                <flux:modal.trigger name="confirm-publish">
                                    <flux:button variant="filled" size="sm" icon="eye" type="button">
                                        {{ __('Publish') }} ({{ $statusCounts->get('approved', 0) }})
                                    </flux:button>
                                </flux:modal.trigger>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Reports Table --}}
                <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <table class="w-full text-sm">
                        <thead class="text-xs text-zinc-500 uppercase bg-zinc-50 dark:bg-zinc-800">
                            <tr>
                                @if ($config?->show_position)
                                    <th class="px-4 py-3 text-left">{{ __('Position') }}</th>
                                @endif
                                <th class="px-4 py-3 text-left">{{ __('Student') }}</th>
                                <th class="px-4 py-3 text-center">{{ __('Average') }}</th>
                                <th class="px-4 py-3 text-center">{{ __('Subjects') }}</th>
                                <th class="px-4 py-3 text-center">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-center">{{ __('Teacher Comment') }}</th>
                                <th class="px-4 py-3 text-right">{{ __('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700 bg-white dark:bg-zinc-900">
                            @foreach ($reports as $report)
                                <tr class="hover:bg-zinc-50/50 dark:hover:bg-zinc-800/50">
                                    @if ($config?->show_position)
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center justify-center size-8 rounded-full {{ $report->position <= 3 ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 font-bold' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300' }}">
                                            {{ $report->position ?? '—' }}
                                        </span>
                                    </td>
                                    @endif
                                    <td class="px-4 py-3">
                                        <div class="font-medium text-zinc-900 dark:text-white">{{ $report->student->name ?? 'Unknown' }}</div>
                                        <div class="text-xs text-zinc-400">{{ $report->student->studentProfile?->admission_number ?? '' }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="font-semibold text-indigo-600 dark:text-indigo-400">{{ number_format($report->average_weighted_score ?? 0, 1) }}%</span>
                                    </td>
                                    <td class="px-4 py-3 text-center text-zinc-600 dark:text-zinc-400">
                                        {{ is_array($report->subject_scores_snapshot) ? count($report->subject_scores_snapshot) : 0 }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @php
                                            $statusColor = match($report->status) {
                                                'draft' => 'zinc',
                                                'pending_approval' => 'amber',
                                                'approved' => 'blue',
                                                'published' => 'green',
                                                default => 'zinc',
                                            };
                                        @endphp
                                        <flux:badge size="sm" :color="$statusColor">
                                            {{ ucfirst(str_replace('_', ' ', $report->status)) }}
                                        </flux:badge>
                                        @if ($report->report_type && $report->report_type !== 'full_term')
                                            <flux:badge size="sm" :color="$report->report_type === 'midterm' ? 'sky' : 'violet'" class="ml-1">
                                                {{ ucwords(str_replace('_', ' ', $report->report_type)) }}
                                            </flux:badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if ($report->teacher_comment)
                                            <flux:icon name="check-circle" class="size-5 text-green-500 mx-auto" />
                                        @else
                                            <flux:icon name="minus-circle" class="size-5 text-zinc-300 mx-auto" />
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            {{-- Approving is the job waiting on this row, so it stays visible --}}
                                            @if ($report->status === 'pending_approval')
                                                <flux:button variant="primary" size="xs" icon="check-badge" x-data x-on:click="$dispatch('open-approve-modal', { reportId: {{ $report->id }}, studentName: {{ Js::from($report->student->name ?? '') }} })">{{ __('Approve') }}</flux:button>
                                            @endif
                                            <x-row-actions :label="__('Actions for :name\'s report card', ['name' => $report->student->name ?? __('student')])">
                                                <flux:menu.item icon="eye" href="{{ route('admin.scores.reports.show', $report) }}" wire:navigate>{{ __('View report card') }}</flux:menu.item>
                                                @if (!in_array($report->status, ['published']))
                                                    <flux:menu.item icon="pencil-square" href="{{ route('admin.scores.reports.edit-data', $report) }}" wire:navigate>{{ __('Edit comments & attendance') }}</flux:menu.item>
                                                @endif
                                                <flux:menu.item icon="arrow-down-tray" href="{{ route('admin.scores.reports.download', $report) }}">{{ __('Download PDF') }}</flux:menu.item>
                                            </x-row-actions>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Approve Modal --}}
                <div x-data="{ open: false, reportId: null, studentName: '' }"
                     x-on:open-approve-modal.window="open = true; reportId = $event.detail.reportId; studentName = $event.detail.studentName"
                     x-show="open" x-transition x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
                    <div class="w-full max-w-md rounded-xl bg-white dark:bg-zinc-800 shadow-2xl p-6 space-y-4" @click.away="open = false">
                        <div class="flex items-center gap-3">
                            <div class="flex items-center justify-center size-10 rounded-full bg-green-100 dark:bg-green-900/30">
                                <flux:icon name="check-badge" class="size-5 text-green-600 dark:text-green-400" />
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Approve Report') }}</h3>
                                <p class="text-sm text-zinc-500" x-text="'Report for ' + studentName"></p>
                            </div>
                        </div>

                        <form method="POST" :action="'/admin/scores/reports/' + reportId + '/approve'" class="space-y-4">
                            @csrf
                            <div>
                                <flux:label>{{ __("Principal's Comment") }}</flux:label>
                                <flux:textarea name="principal_comment" rows="3" :placeholder="__('Add a comment from the principal (optional)')"></flux:textarea>
                            </div>
                            <div class="flex justify-end gap-2">
                                <flux:button variant="subtle" @click="open = false">{{ __('Cancel') }}</flux:button>
                                <flux:button variant="primary" type="submit" icon="check-badge">{{ __('Approve') }}</flux:button>
                            </div>
                        </form>
                    </div>
                </div>
        @elseif ($selectedClassId)
                <div class="rounded-lg border border-dashed border-zinc-300 dark:border-zinc-600 p-8 text-center" x-data="generateReportModal()" x-init="init()">
                    <flux:icon name="document-text" class="mx-auto size-10 text-zinc-400 mb-3" />
                    <p class="text-sm text-zinc-500 mb-3">{{ __('No reports found for this selection.') }}</p>
                    <flux:button variant="primary" size="sm" icon="document-text" @click="openModal()">{{ __('Generate Reports') }}</flux:button>

                    <div x-show="showModal" x-cloak @keydown.escape.window="showModal = false" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                        <div @click.outside="showModal = false" class="w-full max-w-lg rounded-xl bg-white p-6 shadow-xl dark:bg-zinc-800 text-left max-h-[90vh] overflow-y-auto">
                            <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Generate Report Cards') }}</h3>
                            <p class="mt-1 text-sm text-zinc-500">{{ __('Choose class, scope, and report type.') }}</p>

                            <form method="POST" action="{{ route('admin.scores.generate-reports') }}" class="mt-4 space-y-4">
                                @csrf

                                {{-- Class selection grouped by level --}}
                                <div>
                                    <flux:label>{{ __('Class') }}</flux:label>
                                    <flux:select name="class_id" x-model="selectedClassId" x-on:change="onClassChange()">
                                        <option value="">{{ __('Select a class...') }}</option>
                                        @foreach ($levels as $level)
                                            @if ($level->classes->isNotEmpty())
                                                <optgroup label="{{ $level->name }}">
                                                    @foreach ($level->classes as $cls)
                                                        <option value="{{ $cls->id }}" @selected($selectedClassId == $cls->id)>{{ $cls->name }}</option>
                                                    @endforeach
                                                </optgroup>
                                            @endif
                                        @endforeach
                                    </flux:select>
                                </div>

                                {{-- Generation scope --}}
                                <div x-show="selectedClassId">
                                    <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-2">{{ __('Generate For') }}</label>
                                    <div class="flex gap-3">
                                        <label class="flex-1 flex items-center gap-2 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50" :class="scope === 'bulk' && 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20'">
                                            <input type="radio" name="generation_scope" value="bulk" x-model="scope" class="text-indigo-600">
                                            <div>
                                                <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('All Students') }}</div>
                                                <div class="text-xs text-zinc-500">{{ __('Bulk generate for entire class') }}</div>
                                            </div>
                                        </label>
                                        <label class="flex-1 flex items-center gap-2 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50" :class="scope === 'single' && 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20'">
                                            <input type="radio" name="generation_scope" value="single" x-model="scope" class="text-indigo-600">
                                            <div>
                                                <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Single Student') }}</div>
                                                <div class="text-xs text-zinc-500">{{ __('Generate for one student') }}</div>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                {{-- Student picker --}}
                                <div x-show="scope === 'single' && selectedClassId" x-cloak>
                                    <flux:label>{{ __('Student') }}</flux:label>
                                    <div class="relative">
                                        <flux:input type="text" x-model="studentSearch" x-on:input="filterStudents()" placeholder="{{ __('Search student...') }}" />
                                        <div x-show="loadingStudents" class="absolute right-3 top-2.5">
                                            <flux:icon name="arrow-path" class="size-4 text-zinc-400 animate-spin" />
                                        </div>
                                    </div>
                                    <input type="hidden" name="student_id" :value="selectedStudentId">
                                    <div x-show="filteredStudents.length > 0 && !selectedStudentId" class="mt-1 max-h-40 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
                                        <template x-for="student in filteredStudents" :key="student.id">
                                            <button type="button" @click="selectStudent(student)" class="w-full px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700/50 flex items-center justify-between">
                                                <span x-text="student.name" class="font-medium text-zinc-900 dark:text-white"></span>
                                                <span x-text="student.admission_number" class="text-xs text-zinc-400"></span>
                                            </button>
                                        </template>
                                    </div>
                                    <div x-show="selectedStudentId" class="mt-2 flex items-center gap-2 rounded-lg bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 px-3 py-2">
                                        <flux:icon name="user" class="size-4 text-indigo-500" />
                                        <span class="text-sm font-medium text-indigo-700 dark:text-indigo-300" x-text="selectedStudentName"></span>
                                        <button type="button" @click="clearStudent()" class="ml-auto text-indigo-400 hover:text-indigo-600">
                                            <flux:icon name="x-mark" class="size-4" />
                                        </button>
                                    </div>
                                </div>

                                {{-- Report type --}}
                                <div x-show="selectedClassId">
                                    <fieldset class="space-y-2">
                                        <legend class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Report Type') }}</legend>
                                        @if (in_array('midterm', $enabledReportTypes))
                                            <label class="flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50" :class="reportType === 'midterm' && 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20'">
                                                <input type="radio" name="report_type" value="midterm" x-model="reportType" x-on:change="onReportContextChange()" class="text-indigo-600">
                                                <div>
                                                    <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Mid-Term Report') }}</div>
                                                    <div class="text-xs text-zinc-500">{{ __('Based on current term scores') }}</div>
                                                </div>
                                            </label>
                                        @endif
                                        @if (in_array('full_term', $enabledReportTypes))
                                            <label class="flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50" :class="reportType === 'full_term' && 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20'">
                                                <input type="radio" name="report_type" value="full_term" x-model="reportType" x-on:change="onReportContextChange()" class="text-indigo-600">
                                                <div>
                                                    <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Full Term Report') }}</div>
                                                    <div class="text-xs text-zinc-500">{{ __('Complete term report with all components') }}</div>
                                                </div>
                                            </label>
                                        @endif
                                        @if (in_array('session', $enabledReportTypes))
                                            <label class="flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 p-3 cursor-pointer hover:bg-zinc-50 dark:hover:bg-zinc-700/50" :class="reportType === 'session' && 'border-indigo-500 bg-indigo-50/50 dark:bg-indigo-900/20'">
                                                <input type="radio" name="report_type" value="session" x-model="reportType" x-on:change="onReportContextChange()" class="text-indigo-600">
                                                <div>
                                                    <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Session Report') }}</div>
                                                    <div class="text-xs text-zinc-500">{{ __('Aggregated across all terms') }}</div>
                                                </div>
                                            </label>
                                        @endif
                                    </fieldset>
                                </div>

                                <div x-show="selectedClassId && reportType !== 'session'">
                                    <input type="hidden" name="term_id" value="{{ $selectedTermId }}">
                                    <p class="text-sm text-zinc-500"><span class="font-medium">{{ __('Term:') }}</span> {{ $selectedTerm?->name ?? 'Current' }}</p>
                                </div>
                                <div x-show="selectedClassId && reportType === 'session'" x-cloak>
                                    <flux:label>{{ __('Session') }}</flux:label>
                                    <flux:select name="session_id" x-model="selectedSessionId" x-on:change="onReportContextChange()">
                                        @foreach ($sessions as $session)
                                            <option value="{{ $session->id }}" @selected($session->is_current)>{{ $session->name }}</option>
                                        @endforeach
                                    </flux:select>
                                </div>
                                <div class="flex justify-end gap-2 pt-2">
                                    <flux:button variant="subtle" size="sm" @click="showModal = false" type="button">{{ __('Cancel') }}</flux:button>
                                    <flux:button variant="primary" size="sm" icon="document-text" type="submit" x-bind:disabled="!selectedClassId || (scope === 'single' && !selectedStudentId)">{{ __('Generate') }}</flux:button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
        @else
            <div class="rounded-lg border border-dashed border-zinc-300 dark:border-zinc-600 p-8 text-center">
                <flux:icon name="funnel" class="mx-auto size-10 text-zinc-400 mb-3" />
                <p class="text-sm text-zinc-500">{{ __('Select a class to view report cards.') }}</p>
            </div>
        @endif
    </div>
{{-- Confirmation Modals --}}
<flux:modal name="confirm-bulk-approve" class="max-w-md">
    <div class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Approve All Pending Reports?') }}</flux:heading>
            <flux:text class="mt-1">{{ __('This will approve all reports that are currently pending approval for this class and term.') }}</flux:text>
        </div>
        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="primary" icon="check-badge" x-on:click="document.getElementById('bulk-approve-form').submit()">{{ __('Approve All') }}</flux:button>
        </div>
    </div>
</flux:modal>

<flux:modal name="confirm-publish" class="max-w-md">
    <div class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Publish All Approved Reports?') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Students and parents will be able to see these reports once published. This action cannot be easily undone.') }}</flux:text>
        </div>
        <div class="flex justify-end gap-2">
            <flux:modal.close>
                <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
            </flux:modal.close>
            <flux:button variant="filled" icon="eye" x-on:click="document.getElementById('publish-form').submit()">{{ __('Publish All') }}</flux:button>
        </div>
    </div>
</flux:modal>

@push('scripts')
<script>
    function generateReportModal() {
        const classStudentsBaseUrl = '{{ route('admin.scores.class-students', ['class' => '__CLASS_ID__'], false) }}'.replace('__CLASS_ID__', '');

        return {
            showModal: false,
            selectedClassId: '{{ $selectedClassId ?? '' }}',
            selectedTermId: '{{ $selectedTermId ?? '' }}',
            selectedSessionId: '{{ $sessions->firstWhere('is_current', true)?->id ?? '' }}',
            reportType: 'full_term',
            scope: 'bulk',
            students: [],
            filteredStudents: [],
            studentSearch: '',
            selectedStudentId: '',
            selectedStudentName: '',
            loadingStudents: false,

            init() {},

            openModal() {
                this.showModal = true;
                if (this.selectedClassId && this.students.length === 0) {
                    this.fetchStudents();
                }
            },

            async onClassChange() {
                this.clearStudent();
                this.students = [];
                this.filteredStudents = [];
                if (this.selectedClassId) {
                    await this.fetchStudents();
                }
            },

            async onReportContextChange() {
                this.clearStudent();
                this.students = [];
                this.filteredStudents = [];
                if (this.selectedClassId) {
                    await this.fetchStudents();
                }
            },

            async fetchStudents() {
                this.loadingStudents = true;
                try {
                    const params = new URLSearchParams();
                    if (this.reportType === 'session' && this.selectedSessionId) {
                        params.set('session_id', this.selectedSessionId);
                    } else if (this.selectedTermId) {
                        params.set('term_id', this.selectedTermId);
                    }
                    const response = await fetch(classStudentsBaseUrl + this.selectedClassId + '?' + params.toString());
                    this.students = await response.json();
                    this.filteredStudents = this.students;
                } catch (e) {
                    this.students = [];
                }
                this.loadingStudents = false;
            },

            filterStudents() {
                const q = this.studentSearch.toLowerCase();
                this.filteredStudents = this.students.filter(s =>
                    s.name.toLowerCase().includes(q) ||
                    (s.admission_number && s.admission_number.toLowerCase().includes(q))
                );
                this.selectedStudentId = '';
                this.selectedStudentName = '';
            },

            selectStudent(student) {
                this.selectedStudentId = student.id;
                this.selectedStudentName = student.name;
                this.studentSearch = '';
                this.filteredStudents = [];
            },

            clearStudent() {
                this.selectedStudentId = '';
                this.selectedStudentName = '';
                this.studentSearch = '';
                this.filteredStudents = this.students;
            }
        };
    }
</script>
@endpush
</x-layouts::app>
