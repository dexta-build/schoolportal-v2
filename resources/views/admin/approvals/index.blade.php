<x-layouts::app :title="__('Approvals')">
    <div class="space-y-6" x-data="{ selected: [], showBulkReject: false, bulkReason: '' }">
        <x-admin-header :title="__('Teacher Approvals')" :description="__(':count pending', ['count' => $pendingCount])" />

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif

        <div class="flex flex-wrap gap-2" role="tablist" aria-label="{{ __('Status filter') }}">
            <flux:button variant="{{ $status === 'pending' ? 'filled' : 'subtle' }}" size="sm" href="{{ route('admin.approvals.index', ['status' => 'pending']) }}" wire:navigate :aria-selected="$status === 'pending' ? 'true' : 'false'">
                {{ __('Pending') }}
            </flux:button>
            <flux:button variant="{{ $status === 'approved' ? 'filled' : 'subtle' }}" size="sm" href="{{ route('admin.approvals.index', ['status' => 'approved']) }}" wire:navigate :aria-selected="$status === 'approved' ? 'true' : 'false'">
                {{ __('Approved') }}
            </flux:button>
            <flux:button variant="{{ $status === 'rejected' ? 'filled' : 'subtle' }}" size="sm" href="{{ route('admin.approvals.index', ['status' => 'rejected']) }}" wire:navigate :aria-selected="$status === 'rejected' ? 'true' : 'false'">
                {{ __('Rejected') }}
            </flux:button>
            <flux:button variant="{{ $status === 'all' ? 'filled' : 'subtle' }}" size="sm" href="{{ route('admin.approvals.index', ['status' => 'all']) }}" wire:navigate :aria-selected="$status === 'all' ? 'true' : 'false'" :aria-selected="$status === 'all' ? 'true' : 'false'">
                {{ __('All') }}
            </flux:button>
        </div>

        {{-- Type filter --}}
        <div class="flex flex-wrap items-end gap-3">
            <flux:select name="type" onchange="window.location.href = this.value ? '{{ route('admin.approvals.index') }}?type=' + this.value + '&status={{ $status }}' : '{{ route('admin.approvals.index') }}?status={{ $status }}'">
                <option value="">{{ __('All Types') }}</option>
                <option value="result" @selected(request('type') === 'result')>{{ __('Results') }}</option>
                <option value="assignment" @selected(request('type') === 'assignment')>{{ __('Assignments') }}</option>
                <option value="notice" @selected(request('type') === 'notice')>{{ __('Notices') }}</option>
                <option value="quiz" @selected(request('type') === 'quiz')>{{ __('Quizzes') }}</option>
                <option value="game" @selected(request('type') === 'game')>{{ __('Games') }}</option>
                <option value="exam" @selected(request('type') === 'exam')>{{ __('Exams') }}</option>
                <option value="report_card" @selected(request('type') === 'report_card')>{{ __('Report Cards') }}</option>
                <option value="score_amendment" @selected(request('type') === 'score_amendment')>{{ __('Score Corrections') }}</option>
            </flux:select>
        </div>

        {{-- Bulk selection controls (pending/all tabs only) --}}
        @php $pendingIds = $actions->getCollection()->where('status', 'pending')->pluck('id')->values(); @endphp
        @if (in_array($status, ['pending', 'all']) && $pendingIds->isNotEmpty())
            <div class="flex items-center gap-3 flex-wrap">
                <flux:button variant="outline" size="sm" @click="selected.length === {{ $pendingIds->count() }} ? selected = [] : selected = {{ $pendingIds->toJson() }}">
                    <span x-text="selected.length === {{ $pendingIds->count() }} ? '{{ __('Deselect All') }}' : '{{ __('Select All Pending') }}'">{{ __('Select All Pending') }}</span>
                </flux:button>
                <span x-show="selected.length > 0" x-cloak class="text-sm text-zinc-500" x-text="selected.length + ' {{ __('selected') }}'"></span>
            </div>
        @endif

        {{-- Bulk actions floating bar (fixed bottom) --}}
        <div
            x-show="selected.length > 0"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-3"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-3"
            class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 flex items-center gap-2 px-3 py-2 rounded-2xl bg-zinc-900 dark:bg-zinc-800 shadow-2xl border border-zinc-700/60 text-white whitespace-nowrap"
        >
            <span class="text-xs text-zinc-400 pl-1 pr-2 border-r border-zinc-700" x-text="selected.length + ' {{ __('selected') }}'"></span>

            <form method="POST" action="{{ route('admin.approvals.bulk-approve') }}" class="inline-flex">
                @csrf
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="action_ids[]" :value="id">
                </template>
                <button
                    type="submit"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white text-xs font-medium transition-colors"
                >
                    <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    {{ __('Approve') }}
                    <span class="tabular-nums font-semibold" x-text="'(' + selected.length + ')'"></span>
                </button>
            </form>

            <button
                type="button"
                @click="showBulkReject = true"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-red-600 hover:bg-red-700 active:bg-red-800 text-white text-xs font-medium transition-colors"
            >
                <svg class="size-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                {{ __('Reject') }}
                <span class="tabular-nums font-semibold" x-text="'(' + selected.length + ')'"></span>
            </button>

            <button
                type="button"
                @click="selected = []"
                class="p-1.5 rounded-lg text-zinc-500 hover:text-zinc-200 hover:bg-zinc-700/70 transition-colors ml-0.5"
                title="{{ __('Clear selection') }}"
                aria-label="{{ __('Clear selection') }}"
            >
                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <div class="space-y-4">
            @forelse ($actions as $action)
                <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4" :class="selected.includes({{ $action->id }}) ? 'ring-2 ring-blue-500' : ''">
                    <div class="flex items-start gap-4">
                        @if ($action->status === 'pending')
                            <input
                                type="checkbox"
                                :value="{{ $action->id }}"
                                x-model="selected"
                                class="mt-1 size-4 rounded border-zinc-300 text-blue-600 focus:ring-blue-500 shrink-0 cursor-pointer"
                                aria-label="{{ __('Select this submission') }}"
                            >
                        @else
                            <div class="mt-1 size-4 shrink-0"></div>
                        @endif
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                @if ($action->entity_type === 'result')
                                    <flux:badge color="blue" size="sm">{{ __('Result') }}</flux:badge>
                                @elseif ($action->entity_type === 'assignment')
                                    <flux:badge color="purple" size="sm">{{ __('Assignment') }}</flux:badge>
                                @elseif ($action->entity_type === 'notice')
                                    <flux:badge color="amber" size="sm">{{ __('Notice') }}</flux:badge>
                                @elseif ($action->entity_type === 'quiz')
                                    <flux:badge color="indigo" size="sm">{{ __('Quiz') }}</flux:badge>
                                @elseif ($action->entity_type === 'game')
                                    <flux:badge color="cyan" size="sm">{{ __('Game') }}</flux:badge>
                                @elseif ($action->entity_type === 'exam')
                                    @php $examEntity = \App\Models\Exam::find($action->entity_id); @endphp
                                    @if ($examEntity?->category === 'assessment')
                                        <flux:badge color="violet" size="sm">{{ __('Assessment') }}</flux:badge>
                                    @elseif ($examEntity?->category === 'assignment')
                                        <flux:badge color="orange" size="sm">{{ __('Assignment') }}</flux:badge>
                                    @else
                                        <flux:badge color="rose" size="sm">{{ __('Exam') }}</flux:badge>
                                    @endif
                                @elseif ($action->entity_type === 'report_card')
                                    <flux:badge color="teal" size="sm">{{ __('Report Card') }}</flux:badge>
                                @elseif ($action->entity_type === 'score_amendment')
                                    <flux:badge color="orange" size="sm">{{ __('Score Correction') }}</flux:badge>
                                @else
                                    <flux:badge size="sm">{{ ucfirst(str_replace('_', ' ', $action->entity_type)) }}</flux:badge>
                                @endif

                                @if ($action->status === 'pending')
                                    <flux:badge color="yellow" size="sm">{{ __('Pending') }}</flux:badge>
                                @elseif ($action->status === 'approved')
                                    <flux:badge color="green" size="sm">{{ __('Approved') }}</flux:badge>
                                @else
                                    <flux:badge color="red" size="sm">{{ __('Rejected') }}</flux:badge>
                                @endif
                            </div>
                            <p class="text-sm font-medium">
                                {{ ucfirst(str_replace('_', ' ', $action->action_type)) }}
                                {{ __('by') }} {{ $action->teacher?->name ?? __('Unknown') }}
                            </p>
                            <p class="text-xs text-zinc-500 mt-1">{{ $action->created_at->diffForHumans() }}</p>
                            @if ($action->entity_type === 'report_card')
                                @php $reportCard = $reportCards[$action->entity_id] ?? null; @endphp
                                @if ($reportCard)
                                    <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                                        {{ $reportCard->student?->name ?? '' }} &middot; {{ $reportCard->class?->name ?? '' }} &middot; {{ $reportCard->term?->name ?? '' }}, {{ $reportCard->session?->name ?? '' }}
                                    </p>
                                @endif
                            @endif
                            @if ($action->entity_type === 'score_amendment')
                                @php $amendment = $scoreAmendments[$action->entity_id] ?? null; @endphp
                                @if ($amendment)
                                    <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">
                                        {{ $amendment->student?->name }} &middot; {{ $amendment->class?->name }} &middot; {{ $amendment->subject?->name }} ({{ $amendment->scoreComponent?->name }}) &middot; {{ $amendment->term?->name }}
                                    </p>
                                    <p class="text-sm mt-1">
                                        <span class="font-mono text-zinc-500 line-through">{{ $amendment->old_score + 0 }}</span>
                                        <span class="text-zinc-400" aria-hidden="true">→</span>
                                        <span class="sr-only">{{ __('changed to') }}</span>
                                        <span class="font-mono font-semibold text-zinc-900 dark:text-white">{{ $amendment->new_score + 0 }}</span>
                                        <span class="text-xs text-zinc-500">/ {{ $amendment->scoreComponent?->max_score }}</span>
                                    </p>
                                    <p class="text-xs text-zinc-600 dark:text-zinc-400 mt-1">{{ __('Teacher\'s reason:') }} {{ $amendment->reason }}</p>
                                @endif
                            @endif
                            @if ($action->rejection_reason)
                                <p class="text-xs text-red-600 mt-1">{{ __('Reason:') }} {{ $action->rejection_reason }}</p>
                            @endif
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            {{-- Preview link for quizzes and games --}}
                            @if ($action->entity_type === 'quiz')
                                <flux:button variant="subtle" size="xs" href="{{ route('admin.quizzes.show', $action->entity_id) }}" wire:navigate>
                                    <flux:icon name="eye" class="size-3.5 mr-1" /> {{ __('Preview') }}
                                </flux:button>
                            @elseif ($action->entity_type === 'game')
                                <flux:button variant="subtle" size="xs" href="{{ route('admin.games.show', $action->entity_id) }}" wire:navigate>
                                    <flux:icon name="eye" class="size-3.5 mr-1" /> {{ __('Preview') }}
                                </flux:button>
                            @elseif ($action->entity_type === 'exam')
                                @php
                                    $examPreview = \App\Models\Exam::find($action->entity_id);
                                    $examPreviewRoute = 'admin.exams.show';
                                @endphp
                                <flux:button variant="subtle" size="xs" href="{{ route($examPreviewRoute, $action->entity_id) }}" wire:navigate>
                                    <flux:icon name="eye" class="size-3.5 mr-1" /> {{ __('Preview') }}
                                </flux:button>
                            @elseif ($action->entity_type === 'notice')
                                @php $notice = $notices[$action->entity_id] ?? null; @endphp
                                @if ($notice)
                                    <div x-data="{ showPreview: false }">
                                        <flux:button variant="subtle" size="xs" @click="showPreview = true">
                                            <flux:icon name="eye" class="size-3.5 mr-1" /> {{ __('Preview') }}
                                        </flux:button>

                                        {{-- Notice Preview Modal --}}
                                        <div x-show="showPreview" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showPreview = false" @keydown.escape.window="showPreview = false">
                                            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-xl max-w-3xl w-full mx-auto max-h-[85vh] flex flex-col" @click.stop>
                                                {{-- Modal Header --}}
                                                <div class="flex items-start justify-between gap-4 p-5 border-b border-zinc-200 dark:border-zinc-700">
                                                    <div>
                                                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $notice->title }}</h3>
                                                        <p class="text-xs text-zinc-500 mt-1">
                                                            {{ __('By') }} {{ $notice->creator?->name ?? __('Unknown') }} &middot; {{ $notice->created_at->format('M j, Y g:i A') }}
                                                        </p>
                                                    </div>
                                                    <button @click="showPreview = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 p-1">
                                                        <flux:icon name="x-mark" class="size-5" />
                                                    </button>
                                                </div>

                                                {{-- Modal Body --}}
                                                <div class="p-5 overflow-y-auto flex-1 space-y-4">
                                                    {{-- Notice Content --}}
                                                    <div class="prose prose-sm dark:prose-invert max-w-none text-zinc-700 dark:text-zinc-300">
                                                        {!! nl2br(e($notice->content)) !!}
                                                    </div>

                                                    {{-- Attachment --}}
                                                    @if ($notice->file_url)
                                                        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                                                            @if ($notice->fileIsImage())
                                                                <img src="{{ $notice->file_url }}" alt="{{ $notice->file_name ?? __('Notice attachment') }}" class="w-full max-h-80 object-contain bg-zinc-50 dark:bg-zinc-900" />
                                                            @else
                                                                <div class="flex items-center gap-3 p-3 bg-zinc-50 dark:bg-zinc-700/50">
                                                                    <flux:icon.paper-clip class="size-5 text-zinc-500 shrink-0" />
                                                                    <span class="text-sm text-zinc-700 dark:text-zinc-300 truncate flex-1">{{ $notice->file_name ?? __('Attached file') }}</span>
                                                                    <a href="{{ $notice->file_url }}" target="_blank" rel="noopener noreferrer" class="text-xs font-medium text-blue-600 hover:underline whitespace-nowrap">{{ __('Open file') }}</a>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endif

                                                    {{-- Targeting Info --}}
                                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                                                        <div>
                                                            <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">{{ __('Levels') }}</span>
                                                            <p class="mt-0.5 text-zinc-700 dark:text-zinc-300">
                                                                @if (!empty($notice->target_levels))
                                                                    {{ \App\Models\SchoolLevel::whereIn('id', $notice->target_levels)->pluck('name')->join(', ') }}
                                                                @else
                                                                    {{ __('All levels') }}
                                                                @endif
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">{{ __('Classes') }}</span>
                                                            <p class="mt-0.5 text-zinc-700 dark:text-zinc-300">
                                                                @if (!empty($notice->target_classes))
                                                                    {{ \App\Models\SchoolClass::whereIn('id', $notice->target_classes)->pluck('name')->join(', ') }}
                                                                @else
                                                                    {{ __('All classes') }}
                                                                @endif
                                                            </p>
                                                        </div>
                                                        <div>
                                                            <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">{{ __('Audience') }}</span>
                                                            <p class="mt-0.5 text-zinc-700 dark:text-zinc-300">
                                                                @if (!empty($notice->target_roles))
                                                                    {{ collect($notice->target_roles)->map(fn ($r) => ucfirst($r) . 's')->join(', ') }}
                                                                @else
                                                                    {{ __('Everyone') }}
                                                                @endif
                                                            </p>
                                                        </div>
                                                    </div>

                                                    @if ($notice->expires_at)
                                                        <p class="text-xs text-zinc-500">{{ __('Expires:') }} {{ $notice->expires_at->format('M j, Y') }}</p>
                                                    @endif
                                                </div>

                                                {{-- Modal Footer --}}
                                                <div class="flex justify-between items-center gap-3 p-5 border-t border-zinc-200 dark:border-zinc-700">
                                                    <flux:button variant="subtle" size="sm" href="{{ route('admin.notices.edit', $notice) }}" wire:navigate>
                                                        <flux:icon name="pencil-square" class="size-3.5 mr-1" /> {{ __('Edit') }}
                                                    </flux:button>

                                                    <div class="flex items-center gap-2">
                                                        @if ($action->status === 'pending')
                                                            <form method="POST" action="{{ route('admin.approvals.approve', $action) }}">
                                                                @csrf
                                                                <flux:button type="submit" variant="primary" size="sm">{{ __('Approve') }}</flux:button>
                                                            </form>

                                                            <div x-data="{ showReject: false, submitting: false }">
                                                                <flux:button @click="showReject = true" variant="danger" size="sm">{{ __('Reject') }}</flux:button>

                                                                {{-- Inline reject reason --}}
                                                                <div x-show="showReject" x-cloak x-transition class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50" @click.self="showReject = false" @keydown.escape.window="showReject = false">
                                                                    <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-xl p-6 max-w-md w-full mx-4" @click.stop>
                                                                        <div class="flex items-center gap-3 mb-4">
                                                                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                                                                                <flux:icon name="x-circle" class="size-5 text-red-600" />
                                                                            </div>
                                                                            <div>
                                                                                <h3 class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Reject Notice') }}</h3>
                                                                                <p class="text-xs text-zinc-500">{{ $notice->title }}</p>
                                                                            </div>
                                                                        </div>
                                                                        <form method="POST" action="{{ route('admin.approvals.reject', $action) }}" @submit="submitting = true">
                                                                            @csrf
                                                                            <div class="mb-4">
                                                                                <label for="modal_rejection_reason_{{ $action->id }}" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Reason for rejection') }}</label>
                                                                                <textarea
                                                                                    id="modal_rejection_reason_{{ $action->id }}"
                                                                                    name="rejection_reason"
                                                                                    rows="3"
                                                                                    required
                                                                                    maxlength="500"
                                                                                    placeholder="{{ __('Explain why this notice is being rejected...') }}"
                                                                                    class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 px-3 py-2 text-sm text-zinc-900 dark:text-white placeholder-zinc-400 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                                                                                ></textarea>
                                                                            </div>
                                                                            <div class="flex justify-end gap-2">
                                                                                <flux:button type="button" variant="subtle" size="sm" @click="showReject = false">{{ __('Cancel') }}</flux:button>
                                                                                <flux:button type="submit" variant="danger" size="sm" x-bind:disabled="submitting">
                                                                                    <span x-show="!submitting">{{ __('Reject') }}</span>
                                                                                    <span x-show="submitting" x-cloak class="inline-flex items-center gap-1">
                                                                                        <flux:icon name="arrow-path" class="size-3 animate-spin" /> {{ __('Rejecting...') }}
                                                                                    </span>
                                                                                </flux:button>
                                                                            </div>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endif

                                                        <flux:button variant="subtle" size="sm" @click="showPreview = false">{{ __('Close') }}</flux:button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @elseif ($action->entity_type === 'result')
                                @php $result = $results[$action->entity_id] ?? null; @endphp
                                @if ($result)
                                    <div x-data="{ showPreview: false }">
                                        <flux:button variant="subtle" size="xs" @click="showPreview = true">
                                            <flux:icon name="eye" class="size-3.5 mr-1" /> {{ __('Preview') }}
                                        </flux:button>

                                        {{-- Result Preview Modal --}}
                                        <div x-show="showPreview" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showPreview = false" @keydown.escape.window="showPreview = false">
                                            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-xl max-w-2xl w-full mx-auto max-h-[85vh] flex flex-col" @click.stop>
                                                {{-- Modal Header --}}
                                                <div class="flex items-start justify-between gap-4 p-5 border-b border-zinc-200 dark:border-zinc-700">
                                                    <div>
                                                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Result Preview') }}</h3>
                                                        <p class="text-xs text-zinc-500 mt-1">
                                                            {{ __('Uploaded by') }} {{ $action->teacher?->name ?? __('Unknown') }} &middot; {{ $action->created_at->format('M j, Y g:i A') }}
                                                        </p>
                                                    </div>
                                                    <button @click="showPreview = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 p-1">
                                                        <flux:icon name="x-mark" class="size-5" />
                                                    </button>
                                                </div>

                                                {{-- Modal Body --}}
                                                <div class="p-5 overflow-y-auto flex-1 space-y-4">
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-700/50 p-3">
                                                            <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">{{ __('Student') }}</span>
                                                            <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">{{ $result->student?->name ?? '—' }}</p>
                                                            <p class="text-xs text-zinc-500">{{ $result->student?->username ?? '' }}</p>
                                                        </div>
                                                        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-700/50 p-3">
                                                            <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">{{ __('Class') }}</span>
                                                            <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">{{ $result->class?->name ?? '—' }}</p>
                                                        </div>
                                                        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-700/50 p-3">
                                                            <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">{{ __('Session') }}</span>
                                                            <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">{{ $result->session?->name ?? '—' }}</p>
                                                        </div>
                                                        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-700/50 p-3">
                                                            <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">{{ __('Term') }}</span>
                                                            <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">{{ $result->term?->name ?? '—' }}</p>
                                                        </div>
                                                    </div>

                                                    @if ($result->notes)
                                                        <div>
                                                            <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">{{ __('Notes') }}</span>
                                                            <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ $result->notes }}</p>
                                                        </div>
                                                    @endif

                                                    {{-- Result PDF --}}
                                                    @if ($result->file_url)
                                                        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                                                            <div class="flex items-center gap-3 p-3 bg-zinc-50 dark:bg-zinc-700/50">
                                                                <flux:icon name="document" class="size-5 text-red-500 shrink-0" />
                                                                <span class="text-sm text-zinc-700 dark:text-zinc-300 truncate flex-1">{{ __('Result PDF') }}</span>
                                                                <a href="{{ $result->file_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:underline whitespace-nowrap">
                                                                    <flux:icon name="arrow-top-right-on-square" class="size-3" /> {{ __('Open PDF') }}
                                                                </a>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>

                                                {{-- Modal Footer --}}
                                                <div class="flex justify-between items-center gap-3 p-5 border-t border-zinc-200 dark:border-zinc-700">
                                                    <flux:button variant="subtle" size="sm" href="{{ route('admin.results.show', $result) }}" wire:navigate>
                                                        <flux:icon name="arrow-top-right-on-square" class="size-3.5 mr-1" /> {{ __('Full Details') }}
                                                    </flux:button>

                                                    <div class="flex items-center gap-2">
                                                        @if ($action->status === 'pending')
                                                            <form method="POST" action="{{ route('admin.approvals.approve', $action) }}">
                                                                @csrf
                                                                <flux:button type="submit" variant="primary" size="sm">{{ __('Approve') }}</flux:button>
                                                            </form>

                                                            @include('admin.approvals._reject-modal', ['action' => $action, 'entityLabel' => __('Result')])
                                                        @endif

                                                        <flux:button variant="subtle" size="sm" @click="showPreview = false">{{ __('Close') }}</flux:button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @elseif ($action->entity_type === 'assignment')
                                @php $assignment = $assignments[$action->entity_id] ?? null; @endphp
                                @if ($assignment)
                                    <div x-data="{ showPreview: false }">
                                        <flux:button variant="subtle" size="xs" @click="showPreview = true">
                                            <flux:icon name="eye" class="size-3.5 mr-1" /> {{ __('Preview') }}
                                        </flux:button>

                                        {{-- Assignment Preview Modal --}}
                                        <div x-show="showPreview" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showPreview = false" @keydown.escape.window="showPreview = false">
                                            <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-xl max-w-2xl w-full mx-auto max-h-[85vh] flex flex-col" @click.stop>
                                                {{-- Modal Header --}}
                                                <div class="flex items-start justify-between gap-4 p-5 border-b border-zinc-200 dark:border-zinc-700">
                                                    <div>
                                                        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">
                                                            {{ $assignment->title ?: __('Assignment Preview') }}
                                                        </h3>
                                                        <p class="text-xs text-zinc-500 mt-1">
                                                            {{ __('Uploaded by') }} {{ $action->teacher?->name ?? __('Unknown') }} &middot; {{ $action->created_at->format('M j, Y g:i A') }}
                                                        </p>
                                                    </div>
                                                    <button @click="showPreview = false" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 p-1">
                                                        <flux:icon name="x-mark" class="size-5" />
                                                    </button>
                                                </div>

                                                {{-- Modal Body --}}
                                                <div class="p-5 overflow-y-auto flex-1 space-y-4">
                                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-700/50 p-3">
                                                            <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">{{ __('Class') }}</span>
                                                            <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">{{ $assignment->class?->name ?? '—' }}</p>
                                                        </div>
                                                        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-700/50 p-3">
                                                            <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">{{ __('Week') }}</span>
                                                            <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">{{ __('Week :num', ['num' => $assignment->week_number]) }}</p>
                                                        </div>
                                                        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-700/50 p-3">
                                                            <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">{{ __('Session') }}</span>
                                                            <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">{{ $assignment->session?->name ?? '—' }}</p>
                                                        </div>
                                                        <div class="rounded-lg bg-zinc-50 dark:bg-zinc-700/50 p-3">
                                                            <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">{{ __('Term') }}</span>
                                                            <p class="mt-1 text-sm font-medium text-zinc-900 dark:text-white">{{ $assignment->term?->name ?? '—' }}</p>
                                                        </div>
                                                    </div>

                                                    @if ($assignment->due_date)
                                                        <div>
                                                            <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">{{ __('Due Date') }}</span>
                                                            <p class="mt-1 text-sm text-zinc-700 dark:text-zinc-300">{{ \Carbon\Carbon::parse($assignment->due_date)->format('M j, Y') }}</p>
                                                        </div>
                                                    @endif

                                                    @if ($assignment->description)
                                                        <div>
                                                            <span class="text-xs font-medium text-zinc-500 uppercase tracking-wide">{{ __('Description') }}</span>
                                                            <div class="mt-1 prose prose-sm dark:prose-invert max-w-none text-zinc-700 dark:text-zinc-300">
                                                                {!! nl2br(e($assignment->description)) !!}
                                                            </div>
                                                        </div>
                                                    @endif

                                                    {{-- Assignment File --}}
                                                    @if ($assignment->file_url)
                                                        <div class="border border-zinc-200 dark:border-zinc-700 rounded-lg overflow-hidden">
                                                            <div class="flex items-center gap-3 p-3 bg-zinc-50 dark:bg-zinc-700/50">
                                                                <flux:icon.paper-clip class="size-5 text-zinc-500 shrink-0" />
                                                                <span class="text-sm text-zinc-700 dark:text-zinc-300 truncate flex-1">{{ __('Assignment File') }}</span>
                                                                <a href="{{ $assignment->file_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-xs font-medium text-blue-600 hover:underline whitespace-nowrap">
                                                                    <flux:icon name="arrow-top-right-on-square" class="size-3" /> {{ __('Open File') }}
                                                                </a>
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>

                                                {{-- Modal Footer --}}
                                                <div class="flex justify-between items-center gap-3 p-5 border-t border-zinc-200 dark:border-zinc-700">
                                                    <flux:button variant="subtle" size="sm" href="{{ route('admin.assignments.edit', $assignment) }}" wire:navigate>
                                                        <flux:icon name="pencil-square" class="size-3.5 mr-1" /> {{ __('Edit') }}
                                                    </flux:button>

                                                    <div class="flex items-center gap-2">
                                                        @if ($action->status === 'pending')
                                                            <form method="POST" action="{{ route('admin.approvals.approve', $action) }}">
                                                                @csrf
                                                                <flux:button type="submit" variant="primary" size="sm">{{ __('Approve') }}</flux:button>
                                                            </form>

                                                            @include('admin.approvals._reject-modal', ['action' => $action, 'entityLabel' => __('Assignment')])
                                                        @endif

                                                        <flux:button variant="subtle" size="sm" @click="showPreview = false">{{ __('Close') }}</flux:button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            @endif

                            {{-- Generic approve/reject for types without preview modals --}}
                            @if ($action->status === 'pending' && ! in_array($action->entity_type, ['notice', 'result', 'assignment']))
                                <form method="POST" action="{{ route('admin.approvals.approve', $action) }}">
                                    @csrf
                                    <flux:button type="submit" variant="primary" size="xs">{{ __('Approve') }}</flux:button>
                                </form>

                                @include('admin.approvals._reject-modal', ['action' => $action, 'entityLabel' => ucfirst(str_replace('_', ' ', $action->entity_type))])
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-8 text-zinc-500">
                    {{ __('No :status approvals found.', ['status' => $status === 'all' ? '' : $status]) }}
                </div>
            @endforelse
        </div>

        {{ $actions->links() }}
    </div>

    {{-- Bulk reject modal --}}
    <div x-show="showBulkReject" x-cloak x-transition class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showBulkReject = false" @keydown.escape.window="showBulkReject = false">
        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-xl p-6 max-w-md w-full mx-4" @click.stop>
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30">
                    <flux:icon name="x-circle" class="size-5 text-red-600" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-zinc-900 dark:text-white">{{ __('Reject Selected Submissions') }}</h3>
                    <p class="text-xs text-zinc-500" x-text="selected.length + ' {{ __('submission(s) will be rejected') }}'"></p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.approvals.bulk-reject') }}">
                @csrf
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="action_ids[]" :value="id">
                </template>
                <div class="mb-4">
                    <label for="bulk_rejection_reason" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">{{ __('Reason for rejection') }}</label>
                    <textarea
                        id="bulk_rejection_reason"
                        name="rejection_reason"
                        rows="3"
                        required
                        maxlength="500"
                        x-model="bulkReason"
                        placeholder="{{ __('Explain why these submissions are being rejected...') }}"
                        class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-700 px-3 py-2 text-sm text-zinc-900 dark:text-white placeholder-zinc-400 focus:border-red-500 focus:ring-1 focus:ring-red-500"
                    ></textarea>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:button type="button" variant="subtle" size="sm" @click="showBulkReject = false">{{ __('Cancel') }}</flux:button>
                    <flux:button type="submit" variant="danger" size="sm">{{ __('Reject All') }}</flux:button>
                </div>
            </form>
        </div>
    </div>
</x-layouts::app>
