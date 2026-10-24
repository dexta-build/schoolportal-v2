@php
    // Build initial score map for Alpine: { "studentId-subjectId-compId": score|null }
    $initialScores = [];
    foreach ($this->grid as $row) {
        foreach ($this->subjects as $subj) {
            foreach ($this->components as $comp) {
                $cell = $row['subjects'][$subj['id']]['components'][$comp['id']] ?? null;
                $key  = "{$row['student_id']}-{$subj['id']}-{$comp['id']}";
                $initialScores[$key] = $cell['score'] !== null ? (float) $cell['score'] : null;
            }
        }
    }
    // Components meta for Alpine live-total computation
    $compsMeta = array_values(array_map(fn ($c) => [
        'id'     => $c['id'],
        'max'    => $c['max_score'],
        'weight' => $c['weight'],
    ], $this->components));

    // One subject at a time when a subject is picked (always on phones)
    $visibleSubjects = $this->subjectId
        ? array_values(array_filter($this->subjects, fn ($s) => $s['id'] === $this->subjectId))
        : $this->subjects;
    $hasGrid    = ! empty($this->grid);
    $hasSubjects = ! empty($this->subjects);
    $hasComps   = ! empty($this->components);
@endphp

<div
    x-data="gradebook({{ Js::from($initialScores) }}, {{ Js::from($compsMeta) }})"
    x-init="window.matchMedia('(max-width: 767px)').matches && $wire.useOneSubjectMode()"
    x-on:scoresSaved.window="onSaved()"
    class="flex flex-col gap-0"
>
    {{-- ── Top Control Bar ─────────────────────────────────────────────── --}}
    <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 shadow-sm overflow-hidden">

        {{-- Header --}}
        <div class="flex flex-wrap items-start justify-between gap-4 px-5 py-4 border-b border-zinc-100 dark:border-zinc-700/60">
            <div>
                <h1 class="text-xl font-bold text-zinc-900 dark:text-white tracking-tight">{{ __('Gradebook') }}</h1>
                <p class="mt-0.5 text-sm text-zinc-500">
                    {{ __('Spreadsheet-style score entry for all subjects and components.') }}
                </p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <x-page-help topic="gradebook" />

                {{-- Export CSV --}}
                @if ($this->classId && $this->termId)
                    <a href="{{ $this->role === 'teacher'
                        ? route('teacher.scores.export', ['class_id' => $this->classId, 'term_id' => $this->termId])
                        : route('admin.scores.export', ['class_id' => $this->classId, 'term_id' => $this->termId]) }}">
                        <flux:button variant="subtle" size="sm" icon="arrow-down-tray">{{ __('Export CSV') }}</flux:button>
                    </a>
                @endif

                {{-- Lock / Unlock (admin only) --}}
                @if ($this->role === 'admin' && $this->classId && $this->termId)
                    @if ($this->unlockedFilledCells > 0)
                        <flux:button variant="subtle" size="sm" icon="lock-closed" type="button"
                            x-on:click="appConfirm({
                                title: {{ Js::from(__('Lock all scores?')) }},
                                message: {{ Js::from(__('Scores for this class and term become final. After locking, a score can only be changed through a recorded correction.')) }},
                                confirmLabel: {{ Js::from(__('Lock scores')) }},
                            }).then(ok => ok && $wire.lockAll())">
                            {{ __('Lock All') }}
                        </flux:button>
                    @endif
                    @if ($this->lockedCells > 0)
                        <flux:button variant="subtle" size="sm" icon="lock-open" type="button" wire:click="$set('showUnlock', true)">
                            {{ __('Unlock') }}
                        </flux:button>
                    @endif
                @endif

                {{-- Reports --}}
                @if ($this->classId && $this->termId)
                    <a href="{{ $this->role === 'teacher'
                        ? route('teacher.scores.reports', ['class_id' => $this->classId, 'term_id' => $this->termId])
                        : route('admin.scores.reports', ['class_id' => $this->classId, 'term_id' => $this->termId]) }}">
                        <flux:button variant="primary" size="sm" icon="document-text">{{ __('Report Cards') }}</flux:button>
                    </a>
                @endif
            </div>
        </div>

        {{-- Selectors row --}}
        <div class="flex flex-wrap items-end gap-4 px-5 py-4 bg-zinc-50/60 dark:bg-zinc-800/40">
            {{-- Class selector --}}
            <div class="min-w-[180px]">
                <flux:label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Class') }}</flux:label>
                @if ($this->role === 'teacher')
                    <flux:select wire:model.live="classId" class="w-full">
                        <option value="">{{ __('Select class…') }}</option>
                        @foreach ($classes as $cls)
                            <option value="{{ $cls->id }}">{{ $cls->name }}</option>
                        @endforeach
                    </flux:select>
                @else
                    <flux:select wire:model.live="classId" class="w-full">
                        <option value="">{{ __('Select class…') }}</option>
                        @foreach ($levels as $level)
                            @if ($level->classes->isNotEmpty())
                                <optgroup label="{{ $level->name }}">
                                    @foreach ($level->classes as $cls)
                                        <option value="{{ $cls->id }}">{{ $cls->name }}</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        @endforeach
                    </flux:select>
                @endif
            </div>

            {{-- Term selector --}}
            <div class="min-w-[200px]">
                <flux:label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Session & Term') }}</flux:label>
                <flux:select wire:model.live="termId" class="w-full">
                    <option value="">{{ __('Select term…') }}</option>
                    @foreach ($sessions as $session)
                        @foreach ($session->terms as $term)
                            <option value="{{ $term->id }}">{{ $session->name }} — {{ $term->name }}</option>
                        @endforeach
                    @endforeach
                </flux:select>
            </div>

            {{-- Subject filter: one subject at a time (chosen automatically on phones) --}}
            @if ($hasSubjects)
                <div class="w-full sm:w-auto sm:min-w-[180px]">
                    <flux:label for="gradebook-subject" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __('Subject') }}</flux:label>
                    <flux:select id="gradebook-subject" class="w-full"
                        x-effect="$el.value = $wire.subjectId ?? ''"
                        x-on:change="switchSubject($event.target, {
                            title: {{ Js::from(__('Save your scores first?')) }},
                            message: {{ Js::from(__('You have score changes that are not saved yet. They will be saved before switching subject.')) }},
                            confirmLabel: {{ Js::from(__('Save and switch')) }},
                        })">
                        <option value="">{{ __('All subjects') }}</option>
                        @foreach ($this->subjects as $subj)
                            <option value="{{ $subj['id'] }}">{{ $subj['name'] }}</option>
                        @endforeach
                    </flux:select>
                </div>
            @endif

            {{-- Stats chips (only when grid loaded) --}}
            @if ($hasGrid)
                <div class="flex flex-wrap items-center gap-3 pb-0.5">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 dark:bg-zinc-700 px-3 py-1 text-xs font-medium text-zinc-600 dark:text-zinc-300">
                        <flux:icon name="users" class="size-3.5" />
                        {{ $this->studentCount }} {{ __('students') }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 dark:bg-zinc-700 px-3 py-1 text-xs font-medium text-zinc-600 dark:text-zinc-300">
                        <flux:icon name="book-open" class="size-3.5" />
                        {{ count($this->subjects) }} {{ __('subjects') }}
                    </span>
                    @if ($this->cbtCells > 0)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 dark:bg-blue-900/30 px-3 py-1 text-xs font-medium text-blue-600 dark:text-blue-400">
                            <flux:icon name="computer-desktop" class="size-3.5" />
                            {{ $this->cbtCells }} {{ __('CBT') }}
                        </span>
                    @endif
                    @if ($this->lockedCells > 0)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 dark:bg-zinc-700 px-3 py-1 text-xs font-medium text-zinc-500">
                            <flux:icon name="lock-closed" class="size-3.5" />
                            {{ $this->lockedCells }} {{ __('locked') }}
                        </span>
                    @endif
                </div>
            @endif
        </div>

        {{-- Flash messages --}}
        @if ($this->successMessage)
            <div class="px-5 py-3 bg-emerald-50 dark:bg-emerald-900/20 border-t border-emerald-100 dark:border-emerald-800 flex items-center gap-2">
                <flux:icon name="check-circle" class="size-4 text-emerald-600 shrink-0" />
                <span class="text-sm text-emerald-700 dark:text-emerald-400">{{ $this->successMessage }}</span>
            </div>
        @endif
        @if ($this->errorMessage)
            <div class="px-5 py-3 bg-red-50 dark:bg-red-900/20 border-t border-red-100 dark:border-red-800 flex items-center gap-2">
                <flux:icon name="exclamation-circle" class="size-4 text-red-500 shrink-0" />
                <span class="text-sm text-red-600 dark:text-red-400">{{ $this->errorMessage }}</span>
            </div>
        @endif

        {{-- Who locked these scores --}}
        @if ($this->lockInfo)
            <div class="px-5 py-2.5 border-t border-zinc-100 dark:border-zinc-700/60 flex flex-wrap items-center gap-2 text-xs text-zinc-600 dark:text-zinc-400">
                <flux:icon name="lock-closed" class="size-3.5 shrink-0" />
                <span>
                    {{ trans_choice(':count score is locked|:count scores are locked', $this->lockInfo['count'], ['count' => $this->lockInfo['count']]) }}@if ($this->lockInfo['by']) — {{ __('locked by :name on :date', ['name' => $this->lockInfo['by'], 'date' => $this->lockInfo['at']]) }}@endif.
                    {{ $this->role === 'admin'
                        ? __('Click a locked score to correct it.')
                        : __('Click a locked score to request a correction from the school admin.') }}
                </span>
            </div>
        @endif

        {{-- Report cards made out of date by score changes --}}
        @if ($this->staleReportCount > 0)
            <div class="px-5 py-3 border-t border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 flex flex-wrap items-center justify-between gap-3" role="status">
                <div class="flex items-start gap-2 text-sm text-amber-800 dark:text-amber-300">
                    <flux:icon name="exclamation-triangle" class="size-4 mt-0.5 shrink-0" />
                    <span>{{ trans_choice(':count report card is out of date because scores changed after it was generated.|:count report cards are out of date because scores changed after they were generated.', $this->staleReportCount, ['count' => $this->staleReportCount]) }}</span>
                </div>
                <flux:button size="sm" variant="primary" icon="arrow-path" type="button"
                    x-on:click="appConfirm({
                        title: {{ Js::from(__('Update report cards?')) }},
                        message: {{ Js::from(__('Positions are recalculated for the whole class. Published report cards stay published, the previous version is kept, and students and parents are told when theirs changed.')) }},
                        confirmLabel: {{ Js::from(__('Update report cards')) }},
                    }).then(ok => ok && $wire.regenerateReports())">
                    {{ __('Update report cards') }}
                </flux:button>
            </div>
        @endif

        {{-- Corrections waiting for an admin decision --}}
        @if (! empty($this->pendingAmendments))
            <div class="border-t border-orange-200 dark:border-orange-800">
                <div class="px-5 py-2.5 bg-orange-50 dark:bg-orange-900/20 flex items-center gap-2 text-sm font-semibold text-orange-800 dark:text-orange-300">
                    <flux:icon name="clipboard-document-check" class="size-4" />
                    {{ trans_choice(':count score correction waiting for approval|:count score corrections waiting for approval', count($this->pendingAmendments), ['count' => count($this->pendingAmendments)]) }}
                </div>
                <ul class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
                    @foreach ($this->pendingAmendments as $pending)
                        <li class="px-5 py-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" wire:key="pending-{{ $pending['id'] }}">
                            <div class="min-w-0 text-sm">
                                <p class="font-medium text-zinc-900 dark:text-white">
                                    {{ $pending['student'] }} · {{ $pending['subject'] }} ({{ $pending['component'] }})
                                </p>
                                <p class="mt-0.5">
                                    <span class="font-mono text-zinc-500 line-through">{{ $pending['old'] ?? '—' }}</span>
                                    <span class="text-zinc-400" aria-hidden="true">→</span>
                                    <span class="sr-only">{{ __('changed to') }}</span>
                                    <span class="font-mono font-semibold text-zinc-900 dark:text-white">{{ $pending['new'] }}</span>
                                    <span class="text-xs text-zinc-500">/ {{ $pending['max'] }}</span>
                                </p>
                                <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $pending['source'] }}@if ($pending['requested_by']) · {{ $pending['requested_by'] }}@endif · {{ $pending['when'] }} — “{{ $pending['reason'] }}”
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <flux:button size="sm" variant="primary" icon="check" type="button" wire:click="approveAmendment({{ $pending['id'] }})">{{ __('Approve') }}</flux:button>
                                <flux:button size="sm" variant="subtle" icon="x-mark" type="button" wire:click="startReject({{ $pending['id'] }})">{{ __('Reject') }}</flux:button>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- No components warning --}}
        @if ($this->classId && $this->termId && ! $hasComps && $hasSubjects)
            <div class="px-5 py-4 border-t border-zinc-100 dark:border-zinc-700">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2 text-sm text-amber-700 dark:text-amber-400">
                        <flux:icon name="exclamation-triangle" class="size-4 shrink-0 text-amber-500" />
                        {{ __('No score components configured. Set them up in Grading Setup first.') }}
                    </div>
                    @if ($this->role === 'admin')
                        <a href="{{ route('admin.grading.index', ['tab' => 'components']) }}">
                            <flux:button size="sm" variant="primary" icon-trailing="arrow-right">{{ __('Grading & Score Setup') }}</flux:button>
                        </a>
                    @endif
                </div>
            </div>
        @endif

        {{-- No subjects warning --}}
        @if ($this->classId && $this->termId && ! $hasSubjects && $this->classId)
            <div class="px-5 py-4 border-t border-zinc-100 dark:border-zinc-700">
                <div class="flex items-center gap-2 text-sm text-amber-700 dark:text-amber-400">
                    <flux:icon name="exclamation-triangle" class="size-4 shrink-0 text-amber-500" />
                    {{ __('No subjects are assigned to this class.') }}
                </div>
            </div>
        @endif

        {{-- Completion progress (shown when grid loaded) --}}
        @if ($hasGrid && $this->totalCells > 0)
            <div class="px-5 py-3 border-t border-zinc-100 dark:border-zinc-700/60">
                <div class="flex items-center justify-between mb-1.5">
                    <span class="text-xs font-medium text-zinc-500">
                        {{ __('Completion') }} — {{ $this->filledCells }} / {{ $this->totalCells }} {{ __('cells filled') }}
                        @if ($this->totalCells - $this->filledCells > 0)
                            <span class="text-red-500 font-semibold ml-1">({{ $this->totalCells - $this->filledCells }} {{ __('missing') }})</span>
                        @endif
                    </span>
                    <span class="text-xs font-bold {{ $fillPercent === 100 ? 'text-emerald-600' : ($fillPercent >= 60 ? 'text-amber-500' : 'text-red-500') }}">
                        {{ $fillPercent }}%
                    </span>
                </div>
                <div class="h-1.5 w-full rounded-full bg-zinc-100 dark:bg-zinc-700 overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all duration-500 {{ $fillPercent === 100 ? 'bg-emerald-500' : ($fillPercent >= 60 ? 'bg-amber-400' : 'bg-red-400') }}"
                        style="width: {{ $fillPercent }}%"
                    ></div>
                </div>
            </div>
        @endif
    </div>

    {{-- ── Score Grid ─────────────────────────────────────────────────────── --}}
    @if ($hasGrid && $hasSubjects && $hasComps)

        {{-- Legend --}}
        <div class="flex flex-wrap items-center gap-x-5 gap-y-1 px-1 pt-4 pb-2 text-xs text-zinc-400">
            <span class="font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">{{ __('Legend') }}</span>
            <span class="flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-sm bg-amber-100 dark:bg-amber-900/40 ring-1 ring-amber-300 dark:ring-amber-700"></span>{{ __('Unsaved change') }}</span>
            <span class="flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-sm bg-blue-100 dark:bg-blue-900/40 ring-1 ring-blue-300 dark:ring-blue-700"></span>{{ __('CBT (auto-graded)') }}</span>
            <span class="flex items-center gap-1.5"><span class="inline-block size-2.5 rounded-sm bg-zinc-100 dark:bg-zinc-800 ring-1 ring-zinc-300 dark:ring-zinc-600"></span>{{ __('Locked') }}</span>
            <span class="flex items-center gap-1.5"><span class="inline-block size-2 rounded-full bg-indigo-500"></span>{{ __('Corrected') }}</span>
            <span class="flex items-center gap-1.5"><span class="inline-block size-2 rounded-full bg-orange-500"></span>{{ __('Correction pending') }}</span>
            <span class="flex items-center gap-1.5 ml-auto text-zinc-400">
                <flux:icon name="information-circle" class="size-3.5" />
                {{ __('Tab/Arrow keys to navigate · Enter to move down') }}
            </span>
        </div>

        <form method="POST" action="{{ route($this->role === 'teacher' ? 'teacher.scores.save' : 'admin.scores.save') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="class_id" value="{{ $this->classId }}">
            <input type="hidden" name="term_id" value="{{ $this->termId }}">

            <div class="flex justify-end px-1">
                <flux:button
                    type="submit"
                    variant="primary"
                    size="sm"
                    icon="check"
                >
                    {{ __('Save Scores') }}
                </flux:button>
            </div>

        {{-- Scrollable wrapper --}}
        <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
            <div class="overflow-x-auto" @keydown="handleKeydown($event)">
                <table class="w-full border-collapse text-sm" style="min-width: max-content">
                    {{-- ── Header ────────────────────────────────────────── --}}
                    <thead>
                        {{-- Row 1: Subject groups --}}
                        <tr class="bg-gradient-to-b from-zinc-50 to-zinc-100/80 dark:from-zinc-800 dark:to-zinc-800">
                            {{-- Sticky student column header --}}
                            <th rowspan="2"
                                class="sticky left-0 z-20 bg-zinc-50 dark:bg-zinc-800 border-b-2 border-r border-zinc-200 dark:border-zinc-700 px-3 sm:px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-zinc-900 dark:text-zinc-100 min-w-[140px] sm:min-w-[200px] align-bottom">
                                <div class="flex items-center gap-1.5">
                                    <flux:icon name="user" class="size-3.5" />
                                    {{ __('Student') }}
                                    <span class="ml-1 font-normal text-zinc-400">({{ $this->studentCount }})</span>
                                </div>
                            </th>

                            {{-- Subject name headers (spanning components + 1 total) --}}
                            @foreach ($visibleSubjects as $subj)
                                <th colspan="{{ count($this->components) + 1 }}"
                                    class="border-b border-l border-zinc-200 dark:border-zinc-700 px-3 py-2 text-center text-xs font-bold uppercase tracking-wide text-zinc-900 dark:text-zinc-100 whitespace-nowrap bg-zinc-50 dark:bg-zinc-800">
                                    {{ $subj['name'] }}
                                </th>
                            @endforeach
                        </tr>

                        {{-- Row 2: Component names + Total --}}
                        <tr>
                            @foreach ($visibleSubjects as $subj)
                                @foreach ($this->components as $comp)
                                    <th class="bg-zinc-100 dark:bg-zinc-800 border-b-2 border-l border-zinc-200 dark:border-zinc-700 px-2 py-2 text-center text-[11px] font-semibold uppercase tracking-wide min-w-[72px]"
                                        title="{{ $subj['name'] }} — {{ $comp['name'] }}">
                                        <div class="text-zinc-900 dark:text-zinc-100">{{ $comp['short_name'] }}</div>
                                        <div class="text-[10px] font-normal text-zinc-600 dark:text-zinc-400 mt-0.5">/ {{ (int) $comp['max_score'] }}</div>
                                    </th>
                                @endforeach
                                {{-- Total column header --}}
                                <th class="border-b-2 border-l-2 border-zinc-300 dark:border-zinc-600 px-2 py-2 text-center text-[11px] font-bold uppercase tracking-wide text-indigo-600 dark:text-indigo-400 bg-indigo-50/60 dark:bg-indigo-900/20 min-w-[64px]">
                                    <div>{{ __('Total') }}</div>
                                    <div class="text-[10px] font-normal text-indigo-400 mt-0.5">%</div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>

                    {{-- ── Body ──────────────────────────────────────────── --}}
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-700/80 bg-white dark:bg-zinc-900">
                        @foreach ($this->grid as $i => $row)
                            @php $sid = $row['student_id']; @endphp
                            <tr class="group {{ $i % 2 === 1 ? 'bg-zinc-50/50 dark:bg-zinc-800/20' : '' }} hover:bg-sky-50/40 dark:hover:bg-sky-900/10 transition-colors duration-75">

                                {{-- Sticky student cell --}}
                                <td class="sticky left-0 z-10 border-r border-zinc-200 dark:border-zinc-700 px-3 sm:px-4 py-2 {{ $i % 2 === 1 ? 'bg-zinc-50/80 dark:bg-zinc-800/40' : 'bg-white dark:bg-zinc-900' }} group-hover:bg-sky-50/60 dark:group-hover:bg-sky-900/20 transition-colors duration-75">
                                    <div class="flex items-center gap-2.5 min-w-0">
                                        {{-- Avatar circle --}}
                                        <div class="size-7 shrink-0 rounded-full bg-gradient-to-br from-indigo-400 to-violet-500 flex items-center justify-center text-white text-[10px] font-bold">
                                            {{ mb_strtoupper(mb_substr($row['student_name'], 0, 1)) }}
                                        </div>
                                        <div class="min-w-0">
                                            <div class="truncate max-w-[100px] sm:max-w-[155px] font-medium text-zinc-800 dark:text-zinc-100 text-xs leading-snug">{{ $row['student_name'] }}</div>
                                            @if ($row['admission_number'])
                                                <div class="text-[10px] text-zinc-400 font-mono">{{ $row['admission_number'] }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                {{-- Score cells per subject per component --}}
                                @foreach ($visibleSubjects as $subj)
                                    @php $subid = $subj['id']; @endphp
                                    @foreach ($this->components as $comp)
                                        @php
                                            $cid      = $comp['id'];
                                            $cellKey  = "{$sid}-{$subid}-{$cid}";
                                            $cell     = $row['subjects'][$subid]['components'][$cid] ?? null;
                                            $score    = $cell['score'] ?? '';
                                            $isLocked = $cell['is_locked'] ?? false;
                                            $source   = $cell['source_type'] ?? null;
                                        @endphp
                                        <td class="border-l border-zinc-100 dark:border-zinc-700/60 p-1 text-center">
                                            @if ($isLocked)
                                                {{-- Locked: final. Click to correct (admin) or request a correction (teacher). --}}
                                                @php
                                                    $cellTitle = $cell['last_amendment']
                                                        ?? ($this->role === 'admin' ? __('Locked — click to correct') : __('Locked — click to request a correction'));
                                                    if ($cell['has_pending'] ?? false) {
                                                        $cellTitle = __('Correction pending approval').' · '.$cellTitle;
                                                    }
                                                @endphp
                                                <button type="button"
                                                    x-on:click="(changeCount === 0 ? Promise.resolve(true) : appConfirm({
                                                        title: {{ Js::from(__('Discard unsaved scores?')) }},
                                                        message: {{ Js::from(__('You have score changes that are not saved yet. Save them first, or continue and lose them.')) }},
                                                        confirmLabel: {{ Js::from(__('Discard and continue')) }},
                                                        variant: 'danger',
                                                    })).then(ok => ok && $wire.openCorrection({{ $cell['score_id'] }}))"
                                                    title="{{ $cellTitle }}"
                                                    aria-label="{{ $row['student_name'] }}, {{ $subj['name'] }} {{ $comp['short_name'] }}: {{ $score !== '' ? $score + 0 : '—' }}. {{ $cellTitle }}"
                                                    class="relative mx-auto flex min-h-8 w-[62px] items-center justify-center gap-1 rounded bg-zinc-100 dark:bg-zinc-800 px-2 py-1.5 text-xs text-zinc-600 dark:text-zinc-300 ring-1 ring-transparent hover:ring-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 transition">
                                                    <span>{{ $score !== '' ? $score + 0 : '—' }}</span>
                                                    <flux:icon name="lock-closed" class="size-3 text-zinc-400" />
                                                    @if ($cell['has_pending'] ?? false)
                                                        <span class="absolute -right-0.5 -top-0.5 size-2 rounded-full bg-orange-500 ring-2 ring-white dark:ring-zinc-900" aria-hidden="true"></span>
                                                    @elseif (($cell['amended_count'] ?? 0) > 0)
                                                        <span class="absolute -right-0.5 -top-0.5 size-2 rounded-full bg-indigo-500 ring-2 ring-white dark:ring-zinc-900" aria-hidden="true"></span>
                                                    @endif
                                                </button>
                                            @else
                                                {{-- Editable input --}}
                                                <input
                                                    type="number"
                                                    class="cell-input w-[62px] rounded-md border px-1.5 py-1.5 text-center text-xs font-medium transition-all duration-75
                                                        focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-400 focus:bg-white dark:focus:bg-zinc-800
                                                        {{ $source === 'cbt'
                                                            ? 'border-blue-300 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-700 text-blue-700 dark:text-blue-300'
                                                            : 'border-zinc-200 dark:border-zinc-700 bg-transparent dark:text-zinc-200 hover:border-zinc-300 dark:hover:border-zinc-500' }}"
                                                    :class="isDirty('{{ $cellKey }}')
                                                        ? 'border-amber-400 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-600 !text-amber-700 dark:!text-amber-300'
                                                        : ''"
                                                    value="{{ $score }}"
                                                    min="0"
                                                    max="{{ (int) $comp['max_score'] }}"
                                                    step="0.5"
                                                    data-key="{{ $cellKey }}"
                                                    data-student="{{ $sid }}"
                                                    data-subject="{{ $subid }}"
                                                    data-comp="{{ $cid }}"
                                                    data-max="{{ $comp['max_score'] }}"
                                                    data-weight="{{ $comp['weight'] }}"
                                                    name="scores[{{ $sid }}][{{ $subid }}][{{ $cid }}]"
                                                    @input="handleInput($event, {{ $sid }}, {{ $subid }}, {{ $cid }})"
                                                    @focus="$el.select()"
                                                    @blur="clampValue($event)"
                                                    placeholder="—"
                                                >
                                            @endif
                                        </td>
                                    @endforeach

                                    {{-- Subject total (live via Alpine) --}}
                                    <td class="border-l-2 border-zinc-200 dark:border-zinc-600 bg-indigo-50/40 dark:bg-indigo-900/10 px-2 py-1 text-center min-w-[64px]">
                                        <div
                                            class="text-xs font-bold text-indigo-600 dark:text-indigo-400"
                                            x-text="formatTotal(liveTotal({{ $sid }}, {{ $subid }}))"
                                        ></div>
                                        @if (isset($row['subjects'][$subid]['grade']) && $row['subjects'][$subid]['grade'])
                                            <div class="text-[10px] text-indigo-400 dark:text-indigo-500 leading-none mt-0.5">
                                                {{ $row['subjects'][$subid]['grade'] }}
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        </form>

    @elseif ($this->classId && $this->termId)
        {{-- Loading or empty --}}
        <div wire:loading class="flex items-center justify-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-16">
            <flux:icon name="arrow-path" class="size-6 animate-spin text-indigo-500" />
            <span class="text-sm text-zinc-500">{{ __('Loading gradebook…') }}</span>
        </div>
        <div wire:loading.remove>
            @if (! $hasSubjects)
                <div class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 p-16 text-center mt-4">
                    <flux:icon name="book-open" class="size-10 text-zinc-300" />
                    <p class="text-sm font-medium text-zinc-500">{{ __('No subjects assigned to this class.') }}</p>
                    <p class="text-xs text-zinc-400">{{ __('Assign subjects to this class before entering scores.') }}</p>
                </div>
            @elseif (! $hasComps)
                <div class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-amber-300 dark:border-amber-700 bg-amber-50/40 dark:bg-amber-900/10 p-16 text-center mt-4">
                    <flux:icon name="exclamation-triangle" class="size-10 text-amber-400" />
                    <p class="text-sm font-medium text-amber-600 dark:text-amber-400">{{ __('No score components configured.') }}</p>
                    @if ($this->role === 'admin')
                        <a href="{{ route('admin.grading.index', ['tab' => 'components']) }}">
                            <flux:button size="sm" variant="primary" icon-trailing="arrow-right">{{ __('Set up Score Components') }}</flux:button>
                        </a>
                    @endif
                </div>
            @else
                <div class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 p-16 text-center mt-4">
                    <flux:icon name="users" class="size-10 text-zinc-300" />
                    <p class="text-sm font-medium text-zinc-500">{{ __('No students in this class.') }}</p>
                </div>
            @endif
        </div>

    @else
        {{-- Prompt state --}}
        <div class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 p-20 text-center mt-4">
            <div class="size-14 rounded-xl bg-gradient-to-br from-indigo-100 to-violet-100 dark:from-indigo-900/40 dark:to-violet-900/30 flex items-center justify-center">
                <flux:icon name="table-cells" class="size-7 text-indigo-500" />
            </div>
            <div>
                <p class="text-base font-semibold text-zinc-700 dark:text-zinc-200">{{ __('Select a class and term') }}</p>
                <p class="mt-1 text-sm text-zinc-400">{{ __('The full gradebook will load below — all subjects and score components in one view.') }}</p>
            </div>
        </div>
    @endif

    {{-- Loading overlay while Livewire is processing --}}
    <div wire:loading.delay class="fixed inset-0 z-40 bg-black/5 dark:bg-black/20 backdrop-blur-[1px] flex items-end justify-center pb-10 pointer-events-none">
        <div class="bg-white dark:bg-zinc-800 rounded-full px-5 py-2.5 shadow-lg border border-zinc-200 dark:border-zinc-700 flex items-center gap-2.5 text-sm text-zinc-600 dark:text-zinc-300">
            <flux:icon name="arrow-path" class="size-4 animate-spin text-indigo-500" />
            {{ __('Updating…') }}
        </div>
    </div>

    {{-- ── Correct a locked score (admin) / request a correction (teacher) ── --}}
    @if ($this->correctionScoreId && $this->correctionCell)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-3 sm:items-center"
             role="dialog" aria-modal="true" aria-labelledby="correction-title"
             x-on:keydown.escape.window="$wire.cancelCorrection()">
            <form wire:submit="submitCorrection" class="w-full max-w-md rounded-xl border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                <h2 id="correction-title" class="text-base font-semibold text-zinc-900 dark:text-white">
                    {{ $this->role === 'admin' ? __('Correct locked score') : __('Request a score correction') }}
                </h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $this->correctionCell['student'] }} · {{ $this->correctionCell['subject'] }} · {{ $this->correctionCell['component'] }}
                </p>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div>
                        <p class="text-xs font-medium text-zinc-500">{{ __('Current score') }}</p>
                        <p class="mt-1 font-mono text-lg text-zinc-900 dark:text-white">{{ $this->correctionCell['current'] }} <span class="text-sm text-zinc-400">/ {{ $this->correctionCell['max'] + 0 }}</span></p>
                    </div>
                    <flux:input type="number" step="0.5" min="0" max="{{ $this->correctionCell['max'] }}"
                        wire:model="correctionNewScore" label="{{ __('Correct score') }}" required autofocus />
                </div>

                <div class="mt-3">
                    <flux:textarea wire:model="correctionReason" rows="3" required
                        label="{{ __('Reason for the correction') }}"
                        placeholder="{{ __('For example: typing error — the marked script shows 21, not 12') }}" />
                </div>

                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ $this->role === 'admin'
                        ? __('The score stays locked. The old score, new score, your reason and your name are recorded, and this class\'s report cards are marked out of date.')
                        : __('The school admin will review your request. The score only changes if they approve it.') }}
                </p>

                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:button type="button" variant="ghost" wire:click="cancelCorrection">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="primary">
                        {{ $this->role === 'admin' ? __('Save correction') : __('Send request') }}
                    </flux:button>
                </div>
            </form>
        </div>
    @endif

    {{-- ── Unlock scores (admin) ───────────────────────────────────────── --}}
    @if ($this->showUnlock)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-3 sm:items-center"
             role="dialog" aria-modal="true" aria-labelledby="unlock-title"
             x-on:keydown.escape.window="$wire.set('showUnlock', false)">
            <form wire:submit="unlockAll" class="w-full max-w-md rounded-xl border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                <h2 id="unlock-title" class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Unlock scores for this class?') }}</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Use this for large mistakes, such as a whole column entered wrongly. For a single wrong score, click the score and correct it instead — it stays locked.') }}
                </p>
                <div class="mt-4">
                    <flux:textarea wire:model="unlockReason" rows="3" required autofocus
                        label="{{ __('Reason for unlocking') }}"
                        placeholder="{{ __('For example: CA 2 scores were entered in the CA 1 column') }}" />
                </div>
                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Your reason and your name are recorded. Teachers can edit the scores until you lock them again.') }}</p>
                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:button type="button" variant="ghost" wire:click="$set('showUnlock', false)">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="danger" icon="lock-open">{{ __('Unlock scores') }}</flux:button>
                </div>
            </form>
        </div>
    @endif

    {{-- ── Reject a pending correction (admin) ─────────────────────────── --}}
    @if ($this->rejectingAmendmentId)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-3 sm:items-center"
             role="dialog" aria-modal="true" aria-labelledby="reject-title"
             x-on:keydown.escape.window="$wire.cancelReject()">
            <form wire:submit="confirmReject" class="w-full max-w-md rounded-xl border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                <h2 id="reject-title" class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Reject this correction?') }}</h2>
                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __('The score will not change. The teacher who asked is told your reason.') }}</p>
                <div class="mt-4">
                    <flux:textarea wire:model="rejectReason" rows="3" required autofocus label="{{ __('Reason') }}" />
                </div>
                <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <flux:button type="button" variant="ghost" wire:click="cancelReject">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="danger">{{ __('Reject correction') }}</flux:button>
                </div>
            </form>
        </div>
    @endif

    {{-- ── Floating Save Bar ────────────────────────────────────────────── --}}
    <div
        x-show="changeCount > 0"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-4"
        class="fixed bottom-6 left-1/2 z-50 -translate-x-1/2"
        style="display: none"
    >
        <div class="flex items-center gap-3 rounded-2xl bg-zinc-900 dark:bg-white px-5 py-3 shadow-2xl shadow-black/30 ring-1 ring-white/10 dark:ring-zinc-900/10">
            {{-- Count badge --}}
            <div class="flex items-center gap-2">
                <span class="size-2 rounded-full bg-amber-400 animate-pulse"></span>
                <span class="text-sm font-semibold text-white dark:text-zinc-900" x-text="`${changeCount} unsaved ${changeCount === 1 ? 'change' : 'changes'}`"></span>
            </div>
            <div class="h-4 w-px bg-white/20 dark:bg-zinc-900/20"></div>
            {{-- Discard --}}
            <button
                @click="discard()"
                class="text-xs font-medium text-zinc-400 dark:text-zinc-500 hover:text-white dark:hover:text-zinc-900 transition-colors"
            >
                {{ __('Discard') }}
            </button>
            {{-- Save --}}
            <button
                @click="save()"
                :disabled="saving"
                class="flex items-center gap-1.5 rounded-xl bg-indigo-500 hover:bg-indigo-400 disabled:opacity-60 disabled:cursor-not-allowed px-4 py-1.5 text-xs font-bold text-white transition-colors shadow-inner shadow-indigo-400"
            >
                <svg x-show="saving" class="size-3.5 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                <svg x-show="!saving" class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                </svg>
                <span x-text="saving ? 'Saving…' : 'Save Changes'"></span>
            </button>
        </div>
    </div>
</div>
