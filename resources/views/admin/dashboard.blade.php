<x-layouts::app :title="__('Admin Dashboard')">
    @php
        $hour = (int) now()->format('G');
        $timeGreeting = match (true) {
            $hour >= 5 && $hour < 12  => __('Good morning'),
            $hour >= 12 && $hour < 17 => __('Good afternoon'),
            default                    => __('Good evening'),
        };
    @endphp
    @include('partials.dashboard-styles')

    @include('partials.announcement-banners')

    <div class="flex flex-col gap-6"
        x-data="{
            widgetMap: @js($widgetOrder),
            updateWidgets(widgets) {
                const map = {};
                widgets.forEach((w, i) => { map[w.id] = { order: i, visible: w.visible }; });
                this.widgetMap = map;
            }
        }"
        x-on:dashboard-updated.window="updateWidgets($event.detail.widgets)"
    >
        {{-- ── Welcome Banner ─────────────────────────────────────── --}}
        <div class="dash-welcome dash-welcome-admin dash-animate" role="banner">
            <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-white">
                        {{ $timeGreeting }}, {{ auth()->user()->name }}
                    </h1>
                    @if ($currentSession && $currentTerm)
                        <p class="mt-1 text-sm text-white/70">
                            {{ $currentSession->name }} &mdash; {{ $currentTerm->name }}
                        </p>
                    @else
                        <p class="mt-1 text-sm text-amber-300">
                            {{ __('No active session/term configured — set one up in Academic Sessions to get started.') }}
                        </p>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('admin.students.create') }}" wire:navigate class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-white/15 hover:bg-white/25 text-white text-sm font-medium transition-colors backdrop-blur-sm border border-white/10">
                        <flux:icon.plus class="w-4 h-4" />
                        {{ __('Add Student') }}
                    </a>
                    <a href="{{ route('admin.results.create') }}" wire:navigate class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-white/15 hover:bg-white/25 text-white text-sm font-medium transition-colors backdrop-blur-sm border border-white/10">
                        <flux:icon.arrow-up-tray class="w-4 h-4" />
                        {{ __('Upload Result') }}
                    </a>
                    <span class="hidden sm:inline-block w-px h-5 bg-white/20 mx-0.5"></span>
                    <livewire:admin.dashboard-customizer />
                </div>
            </div>
        </div>

        {{-- ── Getting started (until the school is fully set up) ───── --}}
        @if ($showSetupChecklist)
            @include('admin.partials.setup-checklist')
        @endif

        {{-- ── Session/Term Filter ─────────────────────────────────── --}}
        @include('partials.session-term-filter', [
            'route' => 'admin.dashboard',
            'allSessions' => $allSessions,
            'sessionTerms' => $sessionTerms,
            'currentSession' => $currentSession,
            'currentTerm' => $currentTerm,
        ])

        {{-- ── Alerts ─────────────────────────────────────────────── --}}
        @if ($unassignedTeachers->isNotEmpty())
            <div
                :style="'order: ' + (widgetMap.alerts?.order ?? 0)"
                :class="{ 'hidden': !(widgetMap.alerts?.visible ?? true) }"
            >
            <div class="dash-alert dash-alert-amber dash-animate dash-animate-delay-1" role="alert" aria-label="{{ __('Unassigned teachers alert') }}">
                <flux:icon.exclamation-triangle class="w-5 h-5 mt-0.5 shrink-0 text-amber-600 dark:text-amber-400" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">
                        {{ trans_choice(':count teacher has no class assigned|:count teachers have no class assigned', $unassignedTeachers->count(), ['count' => $unassignedTeachers->count()]) }}
                    </p>
                    <p class="mt-0.5 text-xs text-amber-700 dark:text-amber-300">
                        {{ $unassignedTeachers->pluck('name')->join(', ', ' & ') }}
                    </p>
                    <a href="{{ route('admin.teachers.index') }}" wire:navigate class="mt-1.5 inline-flex items-center gap-1 text-xs font-semibold text-amber-800 dark:text-amber-200 hover:underline">
                        {{ __('Assign classes') }}
                        <flux:icon.arrow-right class="w-3 h-3" />
                    </a>
                </div>
            </div>
            </div>
        @endif

        {{-- ── Primary Stats ──────────────────────────────────────── --}}
        <div
            :style="'order: ' + (widgetMap.primary_stats?.order ?? 1)"
            :class="{ 'hidden': !(widgetMap.primary_stats?.visible ?? true) }"
        >
        <section aria-label="{{ __('School statistics') }}">
            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <div class="stat-card stat-card-blue dash-animate dash-animate-delay-1" data-test="stat-students" data-stat-loading
                     x-data x-init="setTimeout(() => $el.removeAttribute('data-stat-loading'), 700)">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon bg-blue-500/15">
                            <flux:icon.academic-cap class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Students') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($totalStudents) }}</p>
                        </div>
                    </div>
                    <div class="mt-2 flex gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ $maleStudents }} {{ __('M') }}</span>
                        <span class="text-zinc-300 dark:text-zinc-600">&bull;</span>
                        <span>{{ $femaleStudents }} {{ __('F') }}</span>
                    </div>
                </div>

                <div class="stat-card stat-card-emerald dash-animate dash-animate-delay-2" data-test="stat-teachers" data-stat-loading
                     x-data x-init="setTimeout(() => $el.removeAttribute('data-stat-loading'), 850)">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon bg-emerald-500/15">
                            <flux:icon.user-group class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Teachers') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($totalTeachers) }}</p>
                        </div>
                    </div>
                    @if ($unassignedTeachers->isNotEmpty())
                        <p class="mt-2 text-xs font-medium text-amber-600 dark:text-amber-400">
                            {{ $unassignedTeachers->count() }} {{ __('unassigned') }}
                        </p>
                    @endif
                </div>

                <div class="stat-card stat-card-purple dash-animate dash-animate-delay-3" data-test="stat-parents" data-stat-loading
                     x-data x-init="setTimeout(() => $el.removeAttribute('data-stat-loading'), 1000)">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon bg-purple-500/15">
                            <flux:icon.users class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Parents') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($totalParents) }}</p>
                        </div>
                    </div>
                </div>

                <div class="stat-card stat-card-amber dash-animate dash-animate-delay-4" data-test="stat-classes" data-stat-loading
                     x-data x-init="setTimeout(() => $el.removeAttribute('data-stat-loading'), 1150)">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon bg-amber-500/15">
                            <flux:icon.building-library class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Classes') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($totalClasses) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        </div>

        {{-- ── Term Stats Row ─────────────────────────────────────── --}}
        <div
            :style="'order: ' + (widgetMap.term_stats?.order ?? 2)"
            :class="{ 'hidden': !(widgetMap.term_stats?.visible ?? true) }"
        >
        <section aria-label="{{ __('Term statistics') }}">
            <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
                <div class="stat-card stat-card-indigo dash-animate dash-animate-delay-2">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon bg-indigo-500/15">
                            <flux:icon.sparkles class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('AI Credits') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($aiTotalCredits) }}</p>
                        </div>
                    </div>
                    <div class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ $aiFreeCredits }} {{ __('free') }} &bull; {{ $aiPurchasedCredits }} {{ __('purchased') }}
                    </div>
                </div>

                <div class="stat-card stat-card-cyan dash-animate dash-animate-delay-3">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon bg-cyan-500/15">
                            <flux:icon.document-check class="w-5 h-5 text-cyan-600 dark:text-cyan-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Result Sheets (PDF)') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($termResultsCount) }}</p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('This term') }}</p>
                </div>

                <div class="stat-card stat-card-teal dash-animate dash-animate-delay-4">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon bg-teal-500/15">
                            <flux:icon.clipboard-document-list class="w-5 h-5 text-teal-600 dark:text-teal-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Assignments') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($termAssignmentsCount) }}</p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('This term') }}</p>
                </div>

                <div class="stat-card stat-card-pink dash-animate dash-animate-delay-5">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon bg-pink-500/15">
                            <flux:icon.megaphone class="w-5 h-5 text-pink-600 dark:text-pink-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Notice Board') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($termNoticesCount) }}</p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('This term') }}</p>
                </div>
            </div>
        </section>
        </div>

        {{-- ── CBT Stats Row ──────────────────────────────────────── --}}
        <div
            :style="'order: ' + (widgetMap.term_stats?.order ?? 2)"
            :class="{ 'hidden': !(widgetMap.term_stats?.visible ?? true) }"
        >
        <section aria-label="{{ __('CBT statistics') }}">
            <div class="grid grid-cols-1 gap-3 sm:gap-4">
                <a href="{{ route('admin.exams.index') }}" wire:navigate class="stat-card dash-animate dash-animate-delay-3 block" style="border-left-color: #6366f1;">
                    <div class="flex items-center gap-3">
                        <div class="stat-icon bg-indigo-500/15">
                            <flux:icon.computer-desktop class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('CBT') }}</p>
                            <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($cbtExamsCount + $cbtAssessmentsCount + $cbtAssignmentsCount) }}</p>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Published') }}</p>
                </a>
            </div>
        </section>
        </div>

        {{-- ── Quick Actions ──────────────────────────────────────── --}}
        <div
            :style="'order: ' + (widgetMap.quick_actions?.order ?? 3)"
            :class="{ 'hidden': !(widgetMap.quick_actions?.visible ?? true) }"
        >
        <section aria-labelledby="quick-actions-heading" class="dash-animate dash-animate-delay-3">
            <h2 id="quick-actions-heading" class="text-sm font-semibold text-zinc-900 dark:text-white mb-3">{{ __('Quick Actions') }}</h2>
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-8">
                <a href="{{ route('admin.students.create') }}" wire:navigate class="quick-action">
                    <div class="quick-action-icon bg-blue-100 dark:bg-blue-900/30">
                        <flux:icon.plus class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                    </div>
                    <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300 text-center">{{ __('Student') }}</span>
                </a>
                <a href="{{ route('admin.teachers.create') }}" wire:navigate class="quick-action">
                    <div class="quick-action-icon bg-emerald-100 dark:bg-emerald-900/30">
                        <flux:icon.plus class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300 text-center">{{ __('Teacher') }}</span>
                </a>
                <a href="{{ route('admin.parents.create') }}" wire:navigate class="quick-action">
                    <div class="quick-action-icon bg-purple-100 dark:bg-purple-900/30">
                        <flux:icon.plus class="w-4 h-4 text-purple-600 dark:text-purple-400" />
                    </div>
                    <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300 text-center">{{ __('Parent') }}</span>
                </a>
                <a href="{{ route('admin.results.create') }}" wire:navigate class="quick-action">
                    <div class="quick-action-icon bg-cyan-100 dark:bg-cyan-900/30">
                        <flux:icon.arrow-up-tray class="w-4 h-4 text-cyan-600 dark:text-cyan-400" />
                    </div>
                    <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300 text-center">{{ __('Result') }}</span>
                </a>
                <a href="{{ route('admin.assignments.create') }}" wire:navigate class="quick-action">
                    <div class="quick-action-icon bg-teal-100 dark:bg-teal-900/30">
                        <flux:icon.clipboard-document-list class="w-4 h-4 text-teal-600 dark:text-teal-400" />
                    </div>
                    <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300 text-center">{{ __('Assignment') }}</span>
                </a>
                <a href="{{ route('admin.notices.create') }}" wire:navigate class="quick-action">
                    <div class="quick-action-icon bg-pink-100 dark:bg-pink-900/30">
                        <flux:icon.megaphone class="w-4 h-4 text-pink-600 dark:text-pink-400" />
                    </div>
                    <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300 text-center">{{ __('Notice') }}</span>
                </a>
                <a href="{{ route('admin.students.import') }}" wire:navigate class="quick-action">
                    <div class="quick-action-icon bg-amber-100 dark:bg-amber-900/30">
                        <flux:icon.arrow-up-on-square-stack class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                    </div>
                    <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300 text-center">{{ __('Import') }}</span>
                </a>
                <a href="{{ route('admin.settings.index') }}" wire:navigate class="quick-action">
                    <div class="quick-action-icon bg-zinc-100 dark:bg-zinc-700">
                        <flux:icon.cog-6-tooth class="w-4 h-4 text-zinc-600 dark:text-zinc-400" />
                    </div>
                    <span class="text-xs font-medium text-zinc-700 dark:text-zinc-300 text-center">{{ __('Settings') }}</span>
                </a>
            </div>
        </section>
        </div>

        {{-- ── Pending Approvals + Recent Activity ────────────────── --}}
        <div
            :style="'order: ' + (widgetMap.approvals_activity?.order ?? 4)"
            :class="{ 'hidden': !(widgetMap.approvals_activity?.visible ?? true) }"
        >
        <div x-data="{ open: window.innerWidth >= 768 }" x-init="window.addEventListener('resize', () => { if (window.innerWidth >= 768) open = true })" class="dash-animate dash-animate-delay-5">
            <button @click="open = !open" class="flex items-center justify-between w-full text-left md:hidden mb-3">
                <div class="flex items-center gap-2">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Approvals & Activity') }}</h2>
                    @if ($pendingCount > 0)
                        <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-amber-500 rounded-full">{{ $pendingCount }}</span>
                    @endif
                </div>
                <flux:icon.chevron-down class="w-4 h-4 text-zinc-500 transition-transform" ::class="open ? 'rotate-180' : ''" />
            </button>
            <div x-show="open" x-collapse>
        <div class="grid gap-4 sm:gap-6 lg:grid-cols-2">
            <div class="dash-panel dash-animate dash-animate-delay-5">
                <div class="dash-panel-header">
                    <div class="flex items-center gap-2">
                        <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Pending Approvals') }}</h2>
                        @if ($pendingCount > 0)
                            <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-amber-500 rounded-full badge-pulse">{{ $pendingCount }}</span>
                        @endif
                    </div>
                    @if ($pendingCount > 0)
                        <a href="{{ route('admin.approvals.index') }}" wire:navigate class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">{{ __('View all') }}</a>
                    @endif
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @forelse ($pendingApprovals as $action)
                        <div class="activity-item">
                            @if ($action->teacher?->avatar_url)
                                <img src="{{ $action->teacher->avatarTableUrl() }}" alt="" class="w-9 h-9 rounded-full object-cover shrink-0">
                            @else
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white text-xs font-bold shrink-0">
                                    {{ $action->teacher ? strtoupper(substr($action->teacher->name, 0, 1)) : '?' }}
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-zinc-700 dark:text-zinc-300">
                                    <span class="font-semibold text-zinc-900 dark:text-white">{{ $action->teacher->name }}</span>
                                    {{ __('submitted a :type', ['type' => $action->entity_type]) }}
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $action->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center">
                            <div class="w-12 h-12 mx-auto rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mb-3">
                                <flux:icon.check-circle class="w-6 h-6 text-emerald-500" />
                            </div>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('All caught up — no pending teacher submissions to review.') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="dash-panel dash-animate dash-animate-delay-6">
                <div class="dash-panel-header">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Recent Activity') }}</h2>
                    <a href="{{ route('admin.audit-logs.index') }}" wire:navigate class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">{{ __('View all') }}</a>
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @forelse ($recentActivity as $log)
                        <div class="activity-item">
                            @if ($log->user?->avatar_url)
                                <img src="{{ $log->user->avatarTableUrl() }}" alt="" class="w-9 h-9 rounded-full object-cover shrink-0">
                            @elseif ($log->user)
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-zinc-500 to-zinc-600 ring-1 ring-zinc-300 dark:ring-zinc-500 flex items-center justify-center text-white text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($log->user->name, 0, 1)) }}
                                </div>
                            @else
                                <div class="activity-dot bg-zinc-100 dark:bg-zinc-700">
                                    <flux:icon.cog-6-tooth class="w-4 h-4 text-zinc-500 dark:text-zinc-400" />
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="text-sm text-zinc-700 dark:text-zinc-300">
                                    @if ($log->user)
                                        <span class="font-semibold text-zinc-900 dark:text-white">{{ $log->user->name }}</span>
                                    @else
                                        <span class="font-medium text-zinc-500">{{ __('System') }}</span>
                                    @endif
                                    {{ str_replace('.', ' ', $log->action) }}
                                </p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $log->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center">
                            <div class="w-12 h-12 mx-auto rounded-full bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center mb-3">
                                <flux:icon.clock class="w-6 h-6 text-zinc-400" />
                            </div>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No activity yet — actions by your team will show up here.') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
            </div>
        </div>
        </div>

        {{-- ── Analytics Link ─────────────────────────────────────── --}}
        <div
            :style="'order: ' + (widgetMap.analytics_link?.order ?? 5)"
            :class="{ 'hidden': !(widgetMap.analytics_link?.visible ?? true) }"
        >
        <div class="text-center dash-animate dash-animate-delay-5">
            <a href="{{ route('admin.analytics') }}" wire:navigate class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-sm font-medium text-zinc-700 dark:text-zinc-300 hover:border-blue-300 dark:hover:border-blue-600 hover:text-blue-600 dark:hover:text-blue-400 transition-all hover:shadow-sm">
                <flux:icon.chart-bar-square class="w-4 h-4" />
                {{ __('View Analytics') }}
                <flux:icon.arrow-right class="w-3.5 h-3.5" />
            </a>
        </div>
        </div>
    </div>
</x-layouts::app>
