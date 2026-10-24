<x-layouts::app :title="__('Grading & Score Setup')">
    <div class="space-y-6">
        <x-score-workflow-steps current="grading" />

        <x-admin-header
            :title="__('Grading & Score Setup')"
            :description="__('Configure grading scales, score components, and report card options.')"
        />

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif
        @if (session('error'))
            <flux:callout variant="danger" icon="x-circle">{{ session('error') }}</flux:callout>
        @endif

        {{-- Tabs --}}
        <div x-data="{ tab: '{{ request('tab', 'scales') }}' }" class="space-y-4">
            <div class="flex gap-2 border-b border-zinc-200 dark:border-zinc-700">
                <button @click="tab = 'scales'" :class="tab === 'scales' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700'" class="border-b-2 px-4 py-2 text-sm font-medium transition-colors -mb-px">
                    {{ __('Grading Scales') }}
                </button>
                <button @click="tab = 'components'" :class="tab === 'components' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700'" class="border-b-2 px-4 py-2 text-sm font-medium transition-colors -mb-px">
                    {{ __('Score Components') }}
                </button>
                <button @click="tab = 'report'" :class="tab === 'report' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-zinc-500 hover:text-zinc-700'" class="border-b-2 px-4 py-2 text-sm font-medium transition-colors -mb-px">
                    {{ __('Report Card') }}
                </button>
            </div>

            {{-- Grading Scales Tab --}}
            <div x-show="tab === 'scales'" x-transition>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
                    <div>
                        <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Grading Scales') }}</p>
                        <p class="text-sm text-zinc-500">{{ __('Define grade bands and optionally assign a scale to one or more levels. Levels without a scale use the school default.') }}</p>
                    </div>
                    <flux:button variant="primary" size="sm" icon="plus" href="{{ route('admin.grading.scales.create') }}" wire:navigate>
                        {{ __('Add Scale') }}
                    </flux:button>
                </div>

                @if ($gradingScales->isEmpty())
                    <div class="rounded-lg border border-dashed border-zinc-300 dark:border-zinc-600 p-8 text-center">
                        <flux:icon name="chart-bar" class="mx-auto size-10 text-zinc-400 mb-3" />
                        <p class="text-sm text-zinc-500">{{ __('No grading scales defined yet.') }}</p>
                    </div>
                @else
                    <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                        @foreach ($gradingScales as $scale)
                            @php
                                $assignedLevels = $scale->levels->sortBy('sort_order')->values();
                                $canDelete = ! $scale->is_default && $assignedLevels->isEmpty();
                            @endphp

                            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 overflow-hidden">
                                <div class="px-4 py-4 border-b border-zinc-100 dark:border-zinc-700">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $scale->name }}</h3>
                                                @if ($scale->is_default)
                                                    <flux:badge color="indigo" size="sm">{{ __('Default') }}</flux:badge>
                                                @endif
                                                <flux:badge :color="$scale->is_active ? 'green' : 'zinc'" size="sm">
                                                    {{ $scale->is_active ? __('Active') : __('Inactive') }}
                                                </flux:badge>
                                            </div>
                                            <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                <span>{{ trans_choice(':count grade band|:count grade bands', $scale->items->count(), ['count' => $scale->items->count()]) }}</span>
                                                <span>{{ trans_choice(':count assigned level|:count assigned levels', $assignedLevels->count(), ['count' => $assignedLevels->count()]) }}</span>
                                            </div>
                                        </div>

                                        <div class="flex shrink-0 items-center gap-1">
                                            <flux:modal.trigger :name="'assign-scale-levels-' . $scale->id">
                                                <flux:button variant="subtle" size="xs" icon="squares-2x2">
                                                    {{ __('Assign') }}
                                                </flux:button>
                                            </flux:modal.trigger>
                                            <flux:button variant="subtle" size="xs" icon="pencil-square" href="{{ route('admin.grading.scales.edit', $scale) }}" wire:navigate>{{ __('Edit') }}</flux:button>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-400">{{ __('Assigned Levels') }}</p>
                                        @if ($assignedLevels->isNotEmpty())
                                            <div class="mt-2 flex flex-wrap gap-1.5">
                                                @foreach ($assignedLevels as $level)
                                                    <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                                                        {{ $level->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                                {{ $scale->is_default
                                                    ? __('No levels assigned. This default scale is used anywhere no level-specific scale is set.')
                                                    : __('No levels assigned yet.') }}
                                            </p>
                                        @endif
                                    </div>
                                </div>

                                <div class="px-4 py-3">
                                    @if ($scale->items->isNotEmpty())
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            @foreach ($scale->items as $item)
                                                <div class="flex items-center justify-between rounded-md bg-zinc-50 px-3 py-2 dark:bg-zinc-900/50">
                                                    <div class="min-w-0">
                                                        <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $item->grade }}</span>
                                                        <span class="ml-1 text-xs text-zinc-500">{{ $item->label }}</span>
                                                    </div>
                                                    <span class="text-xs font-medium text-zinc-500">{{ $item->min_score }}-{{ $item->max_score }}%</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="text-sm text-zinc-500">{{ __('No grade bands configured.') }}</p>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-zinc-100 px-4 py-3 dark:border-zinc-700">
                                    <div class="text-xs text-zinc-500 dark:text-zinc-400">
                                        @if ($scale->is_default)
                                            {{ __('School fallback scale') }}
                                        @elseif ($assignedLevels->isNotEmpty())
                                            {{ __('Remove assigned levels before deleting') }}
                                        @else
                                            {{ __('Can be deleted safely') }}
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @unless ($scale->is_default)
                                            <form method="POST" action="{{ route('admin.grading.scales.make-default', $scale) }}">
                                                @csrf
                                                <flux:button variant="subtle" size="xs" type="submit" icon="star">{{ __('Make Default') }}</flux:button>
                                            </form>
                                        @endunless

                                        @if ($canDelete)
                                            <x-confirm-delete
                                                :action="route('admin.grading.scales.destroy', $scale)"
                                                :title="__('Delete :name?', ['name' => $scale->name])"
                                                :message="__('This grading scale is not used by any level. Deleting it cannot be undone.')"
                                                :buttonLabel="__('Delete')"
                                                :ariaLabel="__('Delete :name', ['name' => $scale->name])"
                                            />
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <flux:modal :name="'assign-scale-levels-' . $scale->id" class="max-w-lg">
                                <form method="POST" action="{{ route('admin.grading.scales.levels', $scale) }}" class="space-y-5">
                                    @csrf
                                    <div>
                                        <flux:heading size="lg">{{ __('Assign Levels') }}</flux:heading>
                                        <flux:text class="mt-1">{{ __('Choose the levels that should use ":name".', ['name' => $scale->name]) }}</flux:text>
                                    </div>

                                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-xs text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-zinc-400">
                                        {{ __('If no level is selected, this scale will only be used when set as the school default. Levels without their own scale use the school default scale.') }}
                                    </div>

                                    @if ($levels->isEmpty())
                                        <p class="text-sm text-zinc-500">{{ __('No active levels found. Create levels first.') }}</p>
                                    @else
                                        <div x-data="{ search: '', selected: @js($assignedLevels->pluck('id')->values()->all()).map(Number) }" class="space-y-3">
                                            <div class="relative">
                                                <flux:icon name="magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-zinc-400" />
                                                <input type="search" x-model.debounce.100ms="search" placeholder="{{ __('Search levels...') }}"
                                                    class="w-full rounded-lg border border-zinc-200 bg-white py-2.5 pl-9 pr-3 text-sm text-zinc-900 shadow-xs outline-none transition placeholder:text-zinc-400 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20 dark:border-zinc-700 dark:bg-zinc-900 dark:text-white dark:placeholder:text-zinc-500">
                                            </div>

                                            <div class="max-h-72 space-y-2 overflow-y-auto rounded-lg border border-zinc-200 bg-white p-2 dark:border-zinc-700 dark:bg-zinc-900">
                                                @foreach ($levels as $level)
                                                    @php
                                                        $assignment = $levelAssignments->get($level->id);
                                                        $assignedToOther = $assignment && (int) $assignment->grading_scale_id !== (int) $scale->id;
                                                    @endphp
                                                    <label
                                                        x-show="{{ Js::from(Str::lower($level->name)) }}.includes(search.trim().toLowerCase())"
                                                        class="block rounded-md border border-transparent px-3 py-2 transition hover:border-indigo-200 hover:bg-indigo-50/60 dark:hover:border-indigo-900 dark:hover:bg-indigo-950/20">
                                                        <div class="flex items-start gap-3">
                                                            <input type="checkbox" name="level_ids[]" value="{{ $level->id }}" x-model.number="selected"
                                                                class="mt-1 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-800">
                                                            <div class="min-w-0 flex-1">
                                                                <div class="text-sm font-medium text-zinc-900 dark:text-white">{{ $level->name }}</div>
                                                                @if ($assignedToOther)
                                                                    <p class="mt-0.5 text-xs text-amber-600 dark:text-amber-400">
                                                                        {{ __('Currently using ":scale". Saving will move it to this scale.', ['scale' => $assignment->scale_name]) }}
                                                                    </p>
                                                                @elseif ($assignment)
                                                                    <p class="mt-0.5 text-xs text-green-600 dark:text-green-400">{{ __('Already assigned to this scale.') }}</p>
                                                                @else
                                                                    <p class="mt-0.5 text-xs text-zinc-500">{{ __('Uses school default unless assigned.') }}</p>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </label>
                                                @endforeach
                                            </div>

                                            <p class="text-xs text-zinc-500">
                                                <span x-text="selected.length"></span> {{ __('selected') }}
                                            </p>
                                        </div>
                                    @endif

                                    <div class="flex justify-end gap-2">
                                        <flux:modal.close>
                                            <flux:button variant="ghost" type="button">{{ __('Cancel') }}</flux:button>
                                        </flux:modal.close>
                                        <flux:button variant="primary" type="submit">{{ __('Save Assignment') }}</flux:button>
                                    </div>
                                </form>
                            </flux:modal>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Score Components Tab --}}
            <div x-show="tab === 'components'" x-transition>
                <p class="text-sm text-zinc-500 mb-4">{{ __('Define how scores are broken down (e.g., CA Test, Exam). Weights must total 100%.') }}</p>

                @php
                    $componentsData = $scoreComponents->count() > 0
                        ? $scoreComponents->map(fn ($c) => ['name' => $c->name, 'short_name' => $c->short_name, 'max_score' => $c->max_score, 'weight' => $c->weight, 'sort_order' => $c->sort_order, 'include_in_midterm' => (bool) $c->include_in_midterm])->values()
                        : [
                            ['name' => 'CA Test 1', 'short_name' => 'CA1', 'max_score' => 20, 'weight' => 20, 'sort_order' => 1, 'include_in_midterm' => true],
                            ['name' => 'CA Test 2', 'short_name' => 'CA2', 'max_score' => 20, 'weight' => 20, 'sort_order' => 2, 'include_in_midterm' => true],
                            ['name' => 'Examination', 'short_name' => 'EXAM', 'max_score' => 60, 'weight' => 60, 'sort_order' => 3, 'include_in_midterm' => false],
                        ];
                @endphp

                <form method="POST" action="{{ route('admin.grading.components.store') }}" x-data="{
                    components: @js($componentsData),
                    get totalWeight() { return this.components.reduce((sum, c) => sum + Number(c.weight || 0), 0); },
                    addComponent() { this.components.push({ name: '', short_name: '', max_score: 0, weight: 0, sort_order: this.components.length + 1, include_in_midterm: false }); },
                    removeComponent(i) { this.components.splice(i, 1); }
                }">
                    @csrf

                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6 space-y-4">
                        <template x-for="(comp, i) in components" :key="i">
                            <div class="grid grid-cols-12 gap-3 items-end">
                                <div class="col-span-3">
                                    <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400" x-text="i === 0 ? 'Name' : ''"></label>
                                    <input type="text" x-model="comp.name" :name="'components['+i+'][name]'" class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 text-sm" placeholder="e.g. CA Test 1" required>
                                </div>
                                <div class="col-span-2">
                                    <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400" x-text="i === 0 ? 'Short' : ''"></label>
                                    <input type="text" x-model="comp.short_name" :name="'components['+i+'][short_name]'" class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 text-sm" placeholder="CA1">
                                </div>
                                <div class="col-span-2">
                                    <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400" x-text="i === 0 ? 'Max Score' : ''"></label>
                                    <input type="number" x-model="comp.max_score" :name="'components['+i+'][max_score]'" class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 text-sm" min="0" required>
                                </div>
                                <div class="col-span-2">
                                    <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400" x-text="i === 0 ? 'Weight %' : ''"></label>
                                    <input type="number" x-model="comp.weight" :name="'components['+i+'][weight]'" class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 text-sm" min="0" max="100" required>
                                </div>
                                <div class="col-span-2 flex items-center gap-2">
                                    <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400" x-text="i === 0 ? 'Mid-Term?' : ''"></label>
                                    <div class="mt-1 flex items-center">
                                        <input type="hidden" :name="'components['+i+'][include_in_midterm]'" value="0">
                                        <input type="checkbox" x-model="comp.include_in_midterm" :name="'components['+i+'][include_in_midterm]'" value="1" class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-700">
                                    </div>
                                </div>
                                <div class="col-span-1">
                                    <button type="button" @click="removeComponent(i)" x-show="components.length > 1" class="p-2 text-red-500 hover:text-red-700 transition-colors">
                                        <flux:icon name="x-mark" class="size-4" />
                                    </button>
                                </div>
                            </div>
                        </template>

                        <div class="flex items-center justify-between pt-2 border-t border-zinc-100 dark:border-zinc-700">
                            <button type="button" @click="addComponent" class="text-sm text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1">
                                <flux:icon name="plus" class="size-4" /> {{ __('Add Component') }}
                            </button>
                            <div class="text-sm" x-bind:class="totalWeight === 100 ? 'text-green-600' : 'text-red-500 font-semibold'">
                                {{ __('Total Weight:') }} <span x-text="totalWeight + '%'"></span>
                                <span x-show="totalWeight !== 100" class="ml-1">{{ __('(must be 100%)') }}</span>
                            </div>
                        </div>

                        <div class="pt-2">
                            <button type="submit" x-bind:disabled="totalWeight !== 100" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">{{ __('Save Score Components') }}</button>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Report Card Config Tab --}}
            <div x-show="tab === 'report'" x-transition>
                <p class="text-sm text-zinc-500 mb-4">{{ __('Configure report card display options, psychomotor/affective traits, and comment presets.') }}</p>

                <form method="POST" action="{{ route('admin.grading.report-card.update') }}" enctype="multipart/form-data" class="space-y-6"
                    x-data="{
                        enabledTypes: @js($reportCardConfig->enabled_report_types ?? ['full_term']),
                        sessionMethod: @js($reportCardConfig->session_calculation_method ?? 'average_of_terms'),
                        midtermWeight: @js($reportCardConfig->midterm_weight ?? ''),
                        fulltermWeight: @js($reportCardConfig->fullterm_weight ?? ''),
                        showTermBreakdown: @js($reportCardConfig->show_term_breakdown_in_session ?? true),
                        isTypeEnabled(type) { return this.enabledTypes.includes(type); },
                        toggleType(type) {
                            const idx = this.enabledTypes.indexOf(type);
                            if (idx > -1) { this.enabledTypes.splice(idx, 1); }
                            else { this.enabledTypes.push(type); }
                        },
                    }">
                    @csrf @method('PUT')

                    {{-- Report Types --}}
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6 space-y-4">
                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Report Types') }}</h4>
                        <p class="text-xs text-zinc-500">{{ __('Choose which report types are available for generation. At least one must be enabled.') }}</p>

                        @error('enabled_report_types') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                        <div class="space-y-3">
                            {{-- Midterm --}}
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" value="midterm" @click="toggleType('midterm')" :checked="isTypeEnabled('midterm')" class="mt-0.5 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-700">
                                <input type="hidden" x-effect="$el.disabled = true" disabled>
                                <div>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Mid-Term Report') }}</span>
                                    <p class="text-xs text-zinc-500">{{ __('Uses only mid-term components (e.g. CA1, CA2, Mid-Term Test). Components must have "Include in Mid-Term" checked.') }}</p>
                                </div>
                            </label>

                            {{-- Fullterm --}}
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" value="full_term" @click="toggleType('full_term')" :checked="isTypeEnabled('full_term')" class="mt-0.5 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-700">
                                <div>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Full-Term Report') }}</span>
                                    <p class="text-xs text-zinc-500">{{ __('Uses all score components. This is the current default behavior.') }}</p>
                                </div>
                            </label>

                            {{-- Session --}}
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" value="session" @click="toggleType('session')" :checked="isTypeEnabled('session')" class="mt-0.5 rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-700">
                                <div>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Session Report') }}</span>
                                    <p class="text-xs text-zinc-500">{{ __('Aggregates all terms in the session into a single report.') }}</p>
                                </div>
                            </label>
                        </div>

                        {{-- Hidden inputs to send enabled_report_types[] --}}
                        <template x-for="type in enabledTypes" :key="type">
                            <input type="hidden" name="enabled_report_types[]" :value="type">
                        </template>

                        {{-- Session Calculation Method (shown only if session is enabled) --}}
                        <div x-show="isTypeEnabled('session')" x-transition class="mt-4 space-y-4 pl-4 border-l-2 border-indigo-200 dark:border-indigo-800">
                            <h5 class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ __('Session Calculation Method') }}</h5>

                            @error('session_calculation_method') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                            <div class="space-y-2">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="session_calculation_method" value="average_of_terms" x-model="sessionMethod" class="border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600">
                                    <div>
                                        <span class="text-sm text-zinc-900 dark:text-white">{{ __('Average of Terms') }}</span>
                                        <span class="text-xs text-zinc-500 ml-1">{{ __('— (Term1 + Term2 + Term3) ÷ 3') }}</span>
                                    </div>
                                </label>

                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="session_calculation_method" value="weighted_average" x-model="sessionMethod" class="border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600">
                                    <div>
                                        <span class="text-sm text-zinc-900 dark:text-white">{{ __('Weighted Average') }}</span>
                                        <span class="text-xs text-zinc-500 ml-1">{{ __('— Custom weights per term') }}</span>
                                    </div>
                                </label>

                                {{-- Weighted inputs --}}
                                <div x-show="sessionMethod === 'weighted_average'" x-transition class="ml-6 mt-2 grid grid-cols-2 sm:grid-cols-3 gap-3">
                                    @error('midterm_weight') <p class="text-xs text-red-600 col-span-full">{{ $message }}</p> @enderror
                                    <div>
                                        <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ __('Term 1 & 2 Weight %') }}</label>
                                        <input type="number" name="midterm_weight" x-model="midtermWeight" min="0" max="100" step="0.01" class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 text-sm" placeholder="30">
                                    </div>
                                    <div>
                                        <label class="text-xs font-medium text-zinc-600 dark:text-zinc-400">{{ __('Term 3 Weight %') }}</label>
                                        <input type="number" name="fullterm_weight" x-model="fulltermWeight" min="0" max="100" step="0.01" class="mt-1 block w-full rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 text-sm" placeholder="40">
                                    </div>
                                    <div class="flex items-end">
                                        <span class="text-xs mb-2" :class="(Number(midtermWeight || 0) + Number(fulltermWeight || 0)) === 100 ? 'text-green-600' : 'text-red-500 font-semibold'">
                                            {{ __('Total:') }} <span x-text="(Number(midtermWeight || 0) + Number(fulltermWeight || 0)) + '%'"></span>
                                        </span>
                                    </div>
                                </div>

                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="session_calculation_method" value="best_two_of_three" x-model="sessionMethod" class="border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600">
                                    <div>
                                        <span class="text-sm text-zinc-900 dark:text-white">{{ __('Best Two of Three') }}</span>
                                        <span class="text-xs text-zinc-500 ml-1">{{ __('— Highest 2 term averages') }}</span>
                                    </div>
                                </label>
                            </div>

                            {{-- Show term breakdown toggle --}}
                            <label class="flex items-center gap-2 mt-3 cursor-pointer">
                                <input type="hidden" name="show_term_breakdown_in_session" value="0">
                                <input type="checkbox" name="show_term_breakdown_in_session" value="1" x-model="showTermBreakdown" class="rounded border-zinc-300 text-indigo-600 focus:ring-indigo-500 dark:border-zinc-600 dark:bg-zinc-700">
                                <span class="text-sm text-zinc-900 dark:text-white">{{ __('Show per-term breakdown in Session Report') }}</span>
                            </label>
                        </div>
                    </div>

                    {{-- Display Options --}}
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6 space-y-4">
                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Display Options') }}</h4>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <flux:switch name="show_position" :label="__('Show Position in Class')" :checked="$reportCardConfig->show_position" value="1" />
                            <flux:switch name="show_class_average" :label="__('Show Class Average')" :checked="$reportCardConfig->show_class_average" value="1" />
                            <flux:switch name="show_grade_summary" :label="__('Show Grade Summary')" :checked="$reportCardConfig->show_grade_summary" value="1" />
                            <flux:switch name="show_subject_teacher" :label="__('Show Subject Teacher')" :checked="$reportCardConfig->show_subject_teacher" value="1" />
                            <flux:switch name="require_class_teacher_comment" :label="__('Require Class Teacher Comment')" :checked="$reportCardConfig->require_class_teacher_comment" value="1" />
                            <flux:switch name="require_principal_comment" :label="__('Require Principal Comment')" :checked="$reportCardConfig->require_principal_comment" value="1" />
                        </div>
                        <flux:input name="principal_title" :label="__('Principal Title')" :value="old('principal_title', $reportCardConfig->principal_title)" placeholder="e.g. Head Teacher, Principal" />
                    </div>

                    {{-- Principal Signature & School Stamp --}}
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6 space-y-4">
                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Principal Signature & School Stamp') }}</h4>
                        <p class="text-xs text-zinc-500">{{ __('Upload a signature image (PNG with transparent background recommended) and optional school stamp. These appear on published report cards.') }}</p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            {{-- Signature Upload --}}
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Principal Signature') }}</label>
                                @if ($reportCardConfig->principal_signature_url)
                                    <div class="flex items-center gap-3 mb-2">
                                        <img src="{{ $reportCardConfig->principal_signature_url }}" alt="{{ __('Current Signature') }}" class="h-16 border rounded bg-white p-1">
                                        <label class="text-xs text-zinc-500">
                                            <input type="checkbox" name="remove_signature" value="1" class="mr-1">
                                            {{ __('Remove') }}
                                        </label>
                                    </div>
                                @endif
                                <input type="file" name="principal_signature" accept="image/png,image/jpeg,image/webp"
                                    class="block w-full text-sm text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900/30 dark:file:text-indigo-300">
                                @error('principal_signature') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>

                            {{-- Stamp Upload --}}
                            <div class="space-y-2">
                                <label class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('School Stamp') }}</label>
                                @if ($reportCardConfig->school_stamp_url)
                                    <div class="flex items-center gap-3 mb-2">
                                        <img src="{{ $reportCardConfig->school_stamp_url }}" alt="{{ __('Current Stamp') }}" class="h-16 border rounded bg-white p-1">
                                        <label class="text-xs text-zinc-500">
                                            <input type="checkbox" name="remove_stamp" value="1" class="mr-1">
                                            {{ __('Remove') }}
                                        </label>
                                    </div>
                                @endif
                                <input type="file" name="school_stamp" accept="image/png,image/jpeg,image/webp"
                                    class="block w-full text-sm text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900/30 dark:file:text-indigo-300">
                                @error('school_stamp') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    {{-- Psychomotor Traits --}}
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6" x-data="{
                        traits: @js($reportCardConfig->psychomotor_traits ?? []),
                        newTrait: '',
                        add() { if (this.newTrait.trim()) { this.traits.push(this.newTrait.trim()); this.newTrait = ''; } },
                        remove(i) { this.traits.splice(i, 1); }
                    }">
                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-white mb-3">{{ __('Psychomotor Traits') }}</h4>
                        <div class="flex flex-wrap gap-2 mb-3">
                            <template x-for="(trait, i) in traits" :key="i">
                                <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50 dark:bg-indigo-900/30 px-3 py-1 text-xs font-medium text-indigo-700 dark:text-indigo-300">
                                    <span x-text="trait"></span>
                                    <input type="hidden" :name="'psychomotor_traits['+i+']'" :value="trait">
                                    <button type="button" @click="remove(i)" class="ml-1 text-indigo-400 hover:text-red-500">&times;</button>
                                </span>
                            </template>
                        </div>
                        <div class="flex gap-2">
                            <input type="text" x-model="newTrait" @keydown.enter.prevent="add" class="block w-full max-w-xs rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 text-sm" placeholder="{{ __('Add a trait...') }}">
                            <flux:button type="button" size="sm" @click="add">{{ __('Add') }}</flux:button>
                        </div>
                    </div>

                    {{-- Affective Traits --}}
                    <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6" x-data="{
                        traits: @js($reportCardConfig->affective_traits ?? []),
                        newTrait: '',
                        add() { if (this.newTrait.trim()) { this.traits.push(this.newTrait.trim()); this.newTrait = ''; } },
                        remove(i) { this.traits.splice(i, 1); }
                    }">
                        <h4 class="text-sm font-semibold text-zinc-900 dark:text-white mb-3">{{ __('Affective Traits') }}</h4>
                        <div class="flex flex-wrap gap-2 mb-3">
                            <template x-for="(trait, i) in traits" :key="i">
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 dark:bg-emerald-900/30 px-3 py-1 text-xs font-medium text-emerald-700 dark:text-emerald-300">
                                    <span x-text="trait"></span>
                                    <input type="hidden" :name="'affective_traits['+i+']'" :value="trait">
                                    <button type="button" @click="remove(i)" class="ml-1 text-emerald-400 hover:text-red-500">&times;</button>
                                </span>
                            </template>
                        </div>
                        <div class="flex gap-2">
                            <input type="text" x-model="newTrait" @keydown.enter.prevent="add" class="block w-full max-w-xs rounded-md border-zinc-300 dark:border-zinc-600 dark:bg-zinc-700 text-sm" placeholder="{{ __('Add a trait...') }}">
                            <flux:button type="button" size="sm" @click="add">{{ __('Add') }}</flux:button>
                        </div>
                    </div>

                    <flux:button variant="primary" type="submit">{{ __('Save Report Card Settings') }}</flux:button>
                </form>
            </div>
        </div>
    </div>
</x-layouts::app>
