<x-layouts::app :title="__('Parent Dashboard')">
    @php
        // The school decides whether parents see CBT results
        $cbtResultsForParents = app('current.school')->featureEnabled('enable_cbt_results_for_parents');
        $hour = (int) now()->format('G');
        $timeGreeting = match (true) {
            $hour >= 5 && $hour < 12  => __('Good morning'),
            $hour >= 12 && $hour < 17 => __('Good afternoon'),
            default                    => __('Good evening'),
        };
    @endphp
    @include('partials.dashboard-styles')

    @include('partials.announcement-banners')

    <div class="space-y-6">
        {{-- ── Welcome Banner ─────────────────────────────────────── --}}
        <div class="dash-welcome dash-welcome-parent dash-animate" role="banner">
            <div class="relative z-10">
                <h1 class="text-xl sm:text-2xl font-bold text-white">
                    {{ $timeGreeting }}, {{ $parent->name }}
                </h1>
                @if ($currentSession && $currentTerm)
                    <p class="mt-1 text-sm text-white/70">
                        {{ $currentSession->name }} &mdash; {{ $currentTerm->name }}
                    </p>
                @endif
            </div>
        </div>

        {{-- ── Family Summary Stats ───────────────────────────────── --}}
        @if ($children->isNotEmpty())
            <section aria-label="{{ __('Summary statistics') }}">
                <div class="grid grid-cols-2 gap-3 sm:gap-4 {{ $cbtResultsForParents ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }}">
                    <div class="stat-card stat-card-blue dash-animate dash-animate-delay-1">
                        <div class="flex items-center gap-3">
                            <div class="stat-icon bg-blue-500/15">
                                <flux:icon.users class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Children') }}</p>
                                <p class="stat-value text-zinc-900 dark:text-white">{{ $children->count() }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card stat-card-emerald dash-animate dash-animate-delay-2">
                        <div class="flex items-center gap-3">
                            <div class="stat-icon bg-emerald-500/15">
                                <flux:icon.document-text class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Result Sheets (PDF)') }}</p>
                                <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($totalResults) }}</p>
                            </div>
                        </div>
                    </div>

                    @if ($cbtResultsForParents)
                    <a href="{{ route('parent.cbt.index') }}" wire:navigate class="stat-card dash-animate dash-animate-delay-3 hover:shadow-md transition-shadow" style="border-left-color: #6366f1;">
                        <div class="flex items-center gap-3">
                            <div class="stat-icon bg-indigo-500/15">
                                <flux:icon.computer-desktop class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('CBT Taken') }}</p>
                                <p class="stat-value text-zinc-900 dark:text-white">{{ number_format($totalCbtExamsTaken) }}</p>
                            </div>
                        </div>
                    </a>
                    @endif

                    <div class="stat-card stat-card-purple dash-animate dash-animate-delay-4">
                        <div class="flex items-center gap-3">
                            <div class="stat-icon bg-purple-500/15">
                                <flux:icon.chart-bar class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Avg Quiz Score') }}</p>
                                <p class="stat-value text-zinc-900 dark:text-white">{{ $overallQuizAvg !== null ? $overallQuizAvg . '%' : '—' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        {{-- ── Children Section ───────────────────────────────────── --}}
        <section aria-label="{{ __('Your children') }}" x-data="{ sel: 0 }">
            @if ($children->isNotEmpty())
                {{-- Child Selector Tabs (multiple children) --}}
                @if ($children->count() > 1)
                    <div class="dash-panel dash-animate dash-animate-delay-3" style="padding: 0;">
                        <div class="flex items-center gap-1 p-1.5 overflow-x-auto" style="scrollbar-width: none;" role="tablist" aria-label="{{ __('Choose a child') }}">
                            @foreach ($children as $child)
                                <button
                                    type="button"
                                    role="tab"
                                    :aria-selected="sel === {{ $loop->index }}"
                                    @click="sel = {{ $loop->index }}"
                                    :class="sel === {{ $loop->index }}
                                        ? 'bg-[#000c99] text-white shadow-sm'
                                        : 'text-zinc-600 dark:text-zinc-400 hover:bg-zinc-100 dark:hover:bg-zinc-700/50'"
                                    class="flex items-center gap-2.5 px-4 py-2.5 rounded-xl text-sm font-medium transition-all whitespace-nowrap shrink-0 cursor-pointer"
                                >
                                    <flux:avatar size="sm" :src="$child->avatarThumbUrl()" :name="$child->name" :initials="$child->initials()" />
                                    <div class="text-left">
                                        <p class="text-sm font-semibold leading-tight">{{ $child->name }}</p>
                                        <p class="text-[11px] leading-tight" :class="sel === {{ $loop->index }} ? 'text-white/70' : 'opacity-50'">
                                            {{ $child->studentProfile?->class?->name ?? __('No class') }}
                                        </p>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Per-Child Full View --}}
                @foreach ($children as $child)
                    @php
                        $profile = $child->studentProfile;
                        $class = $profile?->class;
                        $stats = $childrenStats[$child->id] ?? [];
                        $childAvg = $stats['quiz_avg'] ?? null;
                    @endphp

                    <div x-show="sel === {{ $loop->index }}" x-cloak x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" class="space-y-4 mt-4">
                        {{-- Profile Header --}}
                        <div class="dash-panel dash-animate dash-animate-delay-3">
                            <div class="flex items-center gap-4">
                                <flux:avatar size="xl" :src="$child->avatarProfileUrl()" :name="$child->name" :initials="$child->initials()" />
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-lg font-bold text-zinc-900 dark:text-white truncate">{{ $child->name }}</h3>
                                    <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ $class?->name ?? __('No class assigned') }}
                                        @if ($class?->level)
                                            <span class="text-zinc-300 dark:text-zinc-600 mx-1">&middot;</span>
                                            {{ $class->level->name }}
                                        @endif
                                    </p>
                                    @if ($class?->teacher)
                                        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">
                                            {{ __('Class Teacher: :name', ['name' => $class->teacher->name]) }}
                                        </p>
                                    @endif
                                </div>
                                <a href="{{ route('parent.children.show', $child) }}" wire:navigate
                                   class="hidden sm:flex items-center gap-1.5 shrink-0 text-xs font-medium text-[#000c99] dark:text-blue-400 hover:underline">
                                    <flux:icon.user class="w-3.5 h-3.5" />
                                    {{ __('Full Profile') }}
                                </a>
                            </div>

                            @if (!empty($stats['latest_result']))
                                <div class="mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-700/50">
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400 flex items-center gap-1.5">
                                        <flux:icon.document-check class="w-3.5 h-3.5 text-emerald-500" />
                                        {{ __('Latest result:') }}
                                        <span class="font-semibold text-zinc-700 dark:text-zinc-300">
                                            {{ $stats['latest_result']['session']['name'] ?? '' }} &mdash; {{ $stats['latest_result']['term']['name'] ?? '' }}
                                        </span>
                                    </p>
                                </div>
                            @endif
                        </div>

                        {{-- Child Stats --}}
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div class="stat-card stat-card-emerald">
                                <div class="flex items-center gap-3">
                                    <div class="stat-icon bg-emerald-500/15">
                                        <flux:icon.document-text class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Result Sheets (PDF)') }}</p>
                                        <p class="stat-value text-zinc-900 dark:text-white">{{ $stats['results_count'] ?? 0 }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="stat-card stat-card-amber">
                                <div class="flex items-center gap-3">
                                    <div class="stat-icon bg-amber-500/15">
                                        <flux:icon.academic-cap class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Quizzes') }}</p>
                                        <p class="stat-value text-zinc-900 dark:text-white">{{ $stats['quizzes_taken'] ?? 0 }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="stat-card stat-card-pink">
                                <div class="flex items-center gap-3">
                                    <div class="stat-icon bg-pink-500/15">
                                        <flux:icon.puzzle-piece class="w-5 h-5 text-pink-600 dark:text-pink-400" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400 truncate">{{ __('Games') }}</p>
                                        <p class="stat-value text-zinc-900 dark:text-white">{{ $stats['games_played'] ?? 0 }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- CBT Breakdown --}}
                        @if ($cbtResultsForParents)
                            <div class="dash-panel" style="margin-bottom: 0;">
                                <div class="flex items-center gap-2 mb-3">
                                    <flux:icon.computer-desktop class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                                    <h4 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('CBT Overview') }}</h4>
                                    @if (($stats['cbt_avg'] ?? null) !== null)
                                        <span class="ml-auto text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Avg: :avg%', ['avg' => number_format((float) $stats['cbt_avg'], 0)]) }}</span>
                                    @endif
                                </div>
                                <div class="grid grid-cols-3 gap-3">
                                    <div class="rounded-lg bg-indigo-50 dark:bg-indigo-900/20 px-3 py-2.5 text-center">
                                        <p class="text-lg font-bold text-indigo-700 dark:text-indigo-300">{{ $stats['cbt_exams'] ?? 0 }}</p>
                                        <p class="text-[11px] text-indigo-600/70 dark:text-indigo-400/70 font-medium">{{ __('Exams') }}</p>
                                    </div>
                                    <div class="rounded-lg bg-violet-50 dark:bg-violet-900/20 px-3 py-2.5 text-center">
                                        <p class="text-lg font-bold text-violet-700 dark:text-violet-300">{{ $stats['cbt_assessments'] ?? 0 }}</p>
                                        <p class="text-[11px] text-violet-600/70 dark:text-violet-400/70 font-medium">{{ __('Assessments') }}</p>
                                    </div>
                                    <div class="rounded-lg bg-fuchsia-50 dark:bg-fuchsia-900/20 px-3 py-2.5 text-center">
                                        <p class="text-lg font-bold text-fuchsia-700 dark:text-fuchsia-300">{{ $stats['cbt_assignments'] ?? 0 }}</p>
                                        <p class="text-[11px] text-fuchsia-600/70 dark:text-fuchsia-400/70 font-medium">{{ __('Assignments') }}</p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Navigation Tiles --}}
                        <div class="grid grid-cols-3 sm:grid-cols-6 gap-3">
                            <a href="{{ route('parent.children.results', $child) }}" wire:navigate
                               class="dash-panel group flex flex-col items-center gap-2 py-4 px-2 min-h-[96px] hover:shadow-md hover:border-emerald-200 dark:hover:border-emerald-800/50 transition-all cursor-pointer" style="margin-bottom: 0;">
                                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-900/30 group-hover:scale-110 transition-transform">
                                    <flux:icon.document-text class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                                </div>
                                <span class="text-xs font-semibold leading-tight text-center text-zinc-700 dark:text-zinc-300">{{ __('Result Sheets (PDF)') }}</span>
                            </a>
                            <a href="{{ route('parent.children.report-cards', $child) }}" wire:navigate
                               class="dash-panel group flex flex-col items-center gap-2 py-4 px-2 min-h-[96px] hover:shadow-md hover:border-blue-200 dark:hover:border-blue-800/50 transition-all cursor-pointer" style="margin-bottom: 0;">
                                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-blue-100 dark:bg-blue-900/30 group-hover:scale-110 transition-transform">
                                    <flux:icon.document-chart-bar class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                                </div>
                                <span class="text-xs font-semibold leading-tight text-center text-zinc-700 dark:text-zinc-300">{{ __('Report Cards') }}</span>
                            </a>
                            <a href="{{ route('parent.children.assignments', $child) }}" wire:navigate
                               class="dash-panel group flex flex-col items-center gap-2 py-4 px-2 min-h-[96px] hover:shadow-md hover:border-purple-200 dark:hover:border-purple-800/50 transition-all cursor-pointer" style="margin-bottom: 0;">
                                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-purple-100 dark:bg-purple-900/30 group-hover:scale-110 transition-transform">
                                    <flux:icon.clipboard-document-list class="w-5 h-5 text-purple-600 dark:text-purple-400" />
                                </div>
                                <span class="text-xs font-semibold leading-tight text-center text-zinc-700 dark:text-zinc-300">{{ __('Assignments') }}</span>
                            </a>
                            <a href="{{ route('parent.children.quizzes', $child) }}" wire:navigate
                               class="dash-panel group flex flex-col items-center gap-2 py-4 px-2 min-h-[96px] hover:shadow-md hover:border-amber-200 dark:hover:border-amber-800/50 transition-all cursor-pointer" style="margin-bottom: 0;">
                                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-amber-100 dark:bg-amber-900/30 group-hover:scale-110 transition-transform">
                                    <flux:icon.academic-cap class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                                </div>
                                <span class="text-xs font-semibold leading-tight text-center text-zinc-700 dark:text-zinc-300">{{ __('Quizzes') }}</span>
                            </a>
                            <a href="{{ route('parent.children.games', $child) }}" wire:navigate
                               class="dash-panel group flex flex-col items-center gap-2 py-4 px-2 min-h-[96px] hover:shadow-md hover:border-pink-200 dark:hover:border-pink-800/50 transition-all cursor-pointer" style="margin-bottom: 0;">
                                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-pink-100 dark:bg-pink-900/30 group-hover:scale-110 transition-transform">
                                    <flux:icon.puzzle-piece class="w-5 h-5 text-pink-600 dark:text-pink-400" />
                                </div>
                                <span class="text-xs font-semibold leading-tight text-center text-zinc-700 dark:text-zinc-300">{{ __('Games') }}</span>
                            </a>
                            @if ($cbtResultsForParents)
                            <a href="{{ route('parent.children.cbt-results', $child) }}" wire:navigate
                               class="dash-panel group flex flex-col items-center gap-2 py-4 px-2 min-h-[96px] hover:shadow-md hover:border-indigo-200 dark:hover:border-indigo-800/50 transition-all cursor-pointer" style="margin-bottom: 0;">
                                <div class="flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-100 dark:bg-indigo-900/30 group-hover:scale-110 transition-transform">
                                    <flux:icon.computer-desktop class="w-5 h-5 text-indigo-600 dark:text-indigo-400" />
                                </div>
                                <span class="text-xs font-semibold leading-tight text-center text-zinc-700 dark:text-zinc-300">{{ __('CBT Results') }}</span>
                            </a>
                            @endif
                        </div>

                        {{-- Profile link (mobile only — desktop has it in the header) --}}
                        <a href="{{ route('parent.children.show', $child) }}" wire:navigate
                           class="sm:hidden dash-panel flex items-center justify-between" style="margin-bottom: 0; padding-top: 0.75rem; padding-bottom: 0.75rem;">
                            <div class="flex items-center gap-3">
                                <div class="flex items-center justify-center w-9 h-9 rounded-lg bg-blue-100 dark:bg-blue-900/30">
                                    <flux:icon.user class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                                </div>
                                <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('View Full Profile') }}</span>
                            </div>
                            <flux:icon.chevron-right class="w-4 h-4 text-zinc-400" />
                        </a>
                    </div>
                @endforeach
            @else
                <div class="dash-panel p-8 text-center dash-animate dash-animate-delay-3">
                    <div class="w-16 h-16 mx-auto rounded-full bg-zinc-100 dark:bg-zinc-700 flex items-center justify-center mb-4">
                        <flux:icon.users class="w-8 h-8 text-zinc-400" />
                    </div>
                    <p class="font-semibold text-zinc-900 dark:text-white">{{ __('No children linked') }}</p>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400 max-w-sm mx-auto">
                        {{ __('Your school admin will link your children to your account. Please contact the school if you believe this is an error.') }}
                    </p>
                </div>
            @endif
        </section>

        {{-- ── Latest Notices ─────────────────────────────────────── --}}
        @if ($recentNotices->isNotEmpty())
            <div class="dash-panel dash-animate dash-animate-delay-5" style="padding: 0;">
                <div class="dash-panel-header">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('School Notices') }}</h2>
                    <a href="{{ route('parent.notices.index') }}" wire:navigate class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">{{ __('View all') }}</a>
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700/50">
                    @foreach ($recentNotices as $notice)
                        <a href="{{ route('parent.notices.show', $notice) }}" wire:navigate class="flex items-start gap-3 p-4 hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors">
                            @if ($notice->image_url)
                                <img src="{{ $notice->imageThumbnailUrl() }}" alt="" class="w-12 h-12 rounded-lg object-cover shrink-0" loading="lazy" />
                            @else
                                <div class="flex items-center justify-center w-12 h-12 rounded-lg bg-cyan-100 dark:bg-cyan-900/30 shrink-0">
                                    <flux:icon.megaphone class="w-5 h-5 text-cyan-600 dark:text-cyan-400" />
                                </div>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-sm text-zinc-900 dark:text-white">{{ $notice->title }}</p>
                                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">{{ $notice->published_at->format('M j, Y') }}</p>
                            </div>
                            <flux:icon.chevron-right class="w-4 h-4 text-zinc-400 mt-1 shrink-0" />
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ── School Contact Info ────────────────────────────────── --}}
        <div class="dash-panel dash-animate dash-animate-delay-6">
            <div class="flex items-center gap-3 mb-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-zinc-100 dark:bg-zinc-700">
                    @if ($school->logo_url)
                        <img src="{{ $school->logoSmallUrl() }}" alt="{{ $school->name }}" class="w-8 h-8 rounded object-contain" />
                    @else
                        <flux:icon.building-office-2 class="w-5 h-5 text-zinc-500 dark:text-zinc-400" />
                    @endif
                </div>
                <div>
                    <p class="font-bold text-zinc-900 dark:text-white text-sm">{{ $school->name }}</p>
                    @if ($school->motto)
                        <p class="text-xs text-zinc-500 dark:text-zinc-400 italic">{{ $school->motto }}</p>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap gap-x-6 gap-y-1.5 text-xs text-zinc-500 dark:text-zinc-400">
                @if ($school->phone)
                    <span class="flex items-center gap-1.5">
                        <flux:icon.phone class="w-3.5 h-3.5" />
                        <a href="tel:{{ $school->phone }}" class="hover:underline">{{ $school->phone }}</a>
                    </span>
                @endif
                @if ($school->email)
                    <span class="flex items-center gap-1.5">
                        <flux:icon.envelope class="w-3.5 h-3.5" />
                        <a href="mailto:{{ $school->email }}" class="hover:underline">{{ $school->email }}</a>
                    </span>
                @endif
                @if ($school->address)
                    <span class="flex items-center gap-1.5">
                        <flux:icon.map-pin class="w-3.5 h-3.5" />
                        {{ $school->address }}
                    </span>
                @endif
            </div>
        </div>
    </div>
</x-layouts::app>
