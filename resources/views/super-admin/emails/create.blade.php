<x-layouts::app :title="__('Compose Email')">
    <div class="space-y-6">
        <x-admin-header :title="__('Compose Email to Schools')" />

        @if (session('error'))
            <flux:callout variant="danger" icon="exclamation-triangle">{{ session('error') }}</flux:callout>
        @endif

        <div class="max-w-3xl rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6"
             x-data="{
                 showSendConfirm: false,
                 sending: false,
                 files: [],
                 dragging: false,
                 maxFiles: 5,
                 maxSizeBytes: 5 * 1024 * 1024,
                 skippedFiles: [],
                 addFiles(newFiles) {
                     // Shown inline under the upload box (no browser alert)
                     this.skippedFiles = Array.from(newFiles).filter(f => f.size > this.maxSizeBytes).map(f => f.name);
                     const valid = Array.from(newFiles).filter(f => f.size <= this.maxSizeBytes);
                     const combined = [...this.files, ...valid].slice(0, this.maxFiles);
                     this.files = combined;
                     const dt = new DataTransfer();
                     combined.forEach(f => dt.items.add(f));
                     if (this.$refs.fileInput) this.$refs.fileInput.files = dt.files;
                 },
                 removeFile(index) {
                     this.files.splice(index, 1);
                     const dt = new DataTransfer();
                     this.files.forEach(f => dt.items.add(f));
                     if (this.$refs.fileInput) this.$refs.fileInput.files = dt.files;
                 },
                 formatSize(bytes) {
                     if (bytes < 1024) return bytes + ' B';
                     if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
                     return (bytes / 1048576).toFixed(1) + ' MB';
                 }
             }">

            <form method="POST"
                  action="{{ route('super-admin.emails.store') }}"
                  enctype="multipart/form-data"
                  class="space-y-6"
                  x-ref="emailForm">
                @csrf

                {{-- ── Recipient schools ────────────────────────────────────────── --}}
                <fieldset x-data="{
                    search: '',
                    selected: {{ json_encode(old('school_ids', [])) }},
                    schools: {{ $schools->map(fn($s) => ['id' => $s->id, 'name' => $s->name, 'email' => $s->email, 'domain' => $s->custom_domain])->values()->toJson() }},
                    get filtered() {
                        const q = this.search.toLowerCase().trim();
                        if (!q) return this.schools;
                        return this.schools.filter(s =>
                            s.name.toLowerCase().includes(q) ||
                            (s.email && s.email.toLowerCase().includes(q)) ||
                            (s.domain && s.domain.toLowerCase().includes(q))
                        );
                    },
                    get allFilteredSelected() {
                        return this.filtered.length > 0 && this.filtered.every(s => this.selected.includes(s.id));
                    },
                    get someFilteredSelected() {
                        return this.filtered.some(s => this.selected.includes(s.id)) && !this.allFilteredSelected;
                    },
                    toggleFiltered() {
                        if (this.allFilteredSelected) {
                            const ids = this.filtered.map(s => s.id);
                            this.selected = this.selected.filter(id => !ids.includes(id));
                        } else {
                            const ids = this.filtered.map(s => s.id);
                            this.selected = [...new Set([...this.selected, ...ids])];
                        }
                    },
                    highlight(text) {
                        if (!text) return '';
                        const safe = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
                        if (!this.search.trim()) return safe;
                        const esc = this.search.trim().replace(/[.*+?^${}()|[\]\\]/g, (c) => '\\' + c);
                        return safe.replace(new RegExp('(' + esc + ')', 'gi'), (m) => '<mark class=\'bg-amber-200 dark:bg-amber-800/50 rounded-sm px-px\'>' + m + '</mark>');
                    }
                }" class="space-y-2">
                    <legend class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        {{ __('Recipient Schools') }} <span class="text-red-500" aria-hidden="true">*</span>
                    </legend>

                    {{-- Hidden inputs for form submission, driven by Alpine selected state --}}
                    <template x-for="id in selected" :key="id">
                        <input type="hidden" name="school_ids[]" x-bind:value="id" />
                    </template>

                    {{-- Search --}}
                    <div class="relative">
                        <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 size-4 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                        </svg>
                        <input type="text"
                               x-model.debounce.150ms="search"
                               placeholder="{{ __('Search by name, email or domain…') }}"
                               class="w-full rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-900 py-2 pl-9 pr-8 text-sm text-zinc-900 dark:text-white placeholder-zinc-400 dark:placeholder-zinc-500 outline-none focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/40 transition-shadow"
                               aria-label="{{ __('Search schools') }}" />
                        <button type="button"
                                x-show="search.length > 0"
                                @click="search = ''"
                                class="absolute right-2.5 top-1/2 -translate-y-1/2 rounded p-0.5 text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-300 transition-colors"
                                aria-label="{{ __('Clear search') }}"
                                x-cloak>
                            <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Select-all bar --}}
                    <div class="flex items-center justify-between px-1">
                        <label class="flex cursor-pointer select-none items-center gap-2 text-sm text-zinc-600 dark:text-zinc-400">
                            <input type="checkbox"
                                   x-bind:checked="allFilteredSelected"
                                   x-bind:indeterminate="someFilteredSelected"
                                   @change="toggleFiltered()"
                                   class="rounded border-zinc-300 dark:border-zinc-600 text-indigo-600 focus:ring-indigo-500" />
                            <span x-text="search.trim() ? '{{ __('Select matching') }}' : '{{ __('Select all') }}'"></span>
                        </label>
                        <div class="flex items-center gap-1.5 text-xs text-zinc-400 dark:text-zinc-500">
                            <template x-if="search.trim()">
                                <span>
                                    <span class="font-medium text-zinc-600 dark:text-zinc-300" x-text="filtered.length"></span>
                                    {{ __('match(es)') }} &middot;
                                </span>
                            </template>
                            <span>
                                <span class="font-semibold text-indigo-600 dark:text-indigo-400" x-text="selected.length"></span>
                                / {{ $schools->count() }} {{ __('selected') }}
                            </span>
                        </div>
                    </div>

                    {{-- School list --}}
                    <div class="max-h-64 overflow-y-auto rounded-lg border border-zinc-200 dark:border-zinc-700 divide-y divide-zinc-100 dark:divide-zinc-800">

                        {{-- No results state --}}
                        <div x-show="filtered.length === 0"
                             class="flex flex-col items-center justify-center py-8 text-center"
                             x-cloak>
                            <svg class="mb-2 size-8 text-zinc-300 dark:text-zinc-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
                            </svg>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('No schools match your search') }}</p>
                            <button type="button" @click="search = ''"
                                    class="mt-1.5 text-xs text-indigo-600 dark:text-indigo-400 hover:underline">
                                {{ __('Clear search') }}
                            </button>
                        </div>

                        <template x-for="school in filtered" :key="school.id">
                            <label class="flex cursor-pointer items-center gap-3 px-3 py-2.5 transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700/40"
                                   x-bind:class="selected.includes(school.id) && 'bg-indigo-50/70 dark:bg-indigo-900/20'">
                                <input type="checkbox"
                                       x-bind:checked="selected.includes(school.id)"
                                       @change="selected.includes(school.id) ? selected = selected.filter(id => id !== school.id) : selected = [...selected, school.id]"
                                       class="shrink-0 rounded border-zinc-300 dark:border-zinc-600 text-indigo-600 focus:ring-indigo-500" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-zinc-900 dark:text-white" x-html="highlight(school.name)"></p>
                                    <div class="mt-0.5 flex items-center gap-1.5">
                                        <template x-if="school.email">
                                            <span class="truncate text-xs text-zinc-400 dark:text-zinc-500" x-html="highlight(school.email)"></span>
                                        </template>
                                        <template x-if="!school.email">
                                            <span class="text-xs text-amber-500 dark:text-amber-400">{{ __('No email') }}</span>
                                        </template>
                                        <template x-if="school.domain && school.email">
                                            <span class="text-xs text-zinc-300 dark:text-zinc-600" aria-hidden="true">&middot;</span>
                                        </template>
                                        <template x-if="school.domain">
                                            <span class="truncate font-mono text-xs text-zinc-400 dark:text-zinc-500" x-html="highlight(school.domain)"></span>
                                        </template>
                                    </div>
                                </div>
                                <template x-if="selected.includes(school.id)">
                                    <svg class="size-4 shrink-0 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </template>
                            </label>
                        </template>
                    </div>

                    {{-- Selected chips --}}
                    <template x-if="selected.length > 0">
                        <div class="flex flex-wrap gap-1.5 pt-1" role="list" aria-label="{{ __('Selected schools') }}">
                            <template x-for="id in selected" :key="id">
                                <span class="inline-flex items-center gap-1 rounded-full bg-indigo-100 dark:bg-indigo-900/40 px-2.5 py-1 text-xs font-medium text-indigo-700 dark:text-indigo-300"
                                      role="listitem">
                                    <span x-text="schools.find(s => s.id === id)?.name ?? id"></span>
                                    <button type="button"
                                            @click="selected = selected.filter(i => i !== id)"
                                            class="ml-0.5 rounded-full p-0.5 hover:bg-indigo-200 dark:hover:bg-indigo-800 transition-colors">
                                        <svg class="size-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </span>
                            </template>
                        </div>
                    </template>

                    @error('school_ids')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                    @enderror
                </fieldset>

                {{-- ── Subject ──────────────────────────────────────────────────── --}}
                <flux:input name="subject" :label="__('Subject')" :value="old('subject')" required />

                {{-- ── Rich text editor (body) ──────────────────────────────────── --}}
                <x-rich-editor
                    name="body"
                    :label="__('Email Body')"
                    :value="old('body', '')"
                    :placeholder="__('Write your email content here...')"
                    min-height="min-h-56"
                    required />

                {{-- ── File attachments ─────────────────────────────────────────── --}}
                <div>
                    <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1.5 block">
                        {{ __('Attachments') }}
                        <span class="text-xs font-normal text-zinc-400 dark:text-zinc-500 ml-1.5">
                            {{ __('optional · max 5 files · 5 MB each') }}
                        </span>
                    </label>

                    {{-- Drop zone --}}
                    <label
                        class="flex cursor-pointer items-center gap-3 rounded-lg border-2 border-dashed border-zinc-300 dark:border-zinc-600 px-4 py-3 text-sm text-zinc-500 dark:text-zinc-400 hover:border-indigo-400 dark:hover:border-indigo-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                        x-bind:class="dragging && 'border-indigo-400 dark:border-indigo-500 bg-indigo-50 dark:bg-indigo-900/10 !text-indigo-600'"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="dragging = false; addFiles($event.dataTransfer.files)">
                        <svg class="size-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 01-6.364-6.364l10.94-10.94A3 3 0 1119.5 7.372L8.552 18.32m.009-.01l-.01.01m5.699-9.941l-7.81 7.81a1.5 1.5 0 002.112 2.13"/>
                        </svg>
                        <span x-show="files.length === 0">{{ __('Click to attach files or drag & drop here') }}</span>
                        <span x-show="files.length > 0 && files.length < maxFiles" x-cloak>{{ __('Add more files') }}</span>
                        <span x-show="files.length >= maxFiles" x-cloak class="text-amber-500 dark:text-amber-400">{{ __('Maximum 5 files reached') }}</span>
                        <input type="file"
                               name="attachments[]"
                               multiple
                               x-ref="fileInput"
                               x-bind:disabled="files.length >= maxFiles"
                               @change="addFiles($event.target.files)"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.png,.jpg,.jpeg,.gif,.webp,.txt"
                               class="sr-only" />
                    </label>

                    {{-- Files over the size limit --}}
                    <div x-show="skippedFiles.length" x-cloak role="alert"
                         class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-200">
                        <p class="font-medium">{{ __('These files are over 5 MB and were not attached:') }}</p>
                        <ul class="mt-1 list-disc pl-5">
                            <template x-for="name in skippedFiles" :key="name"><li x-text="name"></li></template>
                        </ul>
                    </div>

                    {{-- Selected file list --}}
                    <template x-if="files.length > 0">
                        <ul class="mt-2 space-y-1.5" role="list" aria-label="{{ __('Selected attachments') }}">
                            <template x-for="(file, index) in files" :key="index">
                                <li class="flex items-center gap-3 rounded-lg border border-zinc-200 dark:border-zinc-700 bg-zinc-50 dark:bg-zinc-800/60 px-3 py-2">
                                    <svg class="size-4 shrink-0 text-zinc-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                                    </svg>
                                    <span class="flex-1 truncate text-sm text-zinc-700 dark:text-zinc-300" x-text="file.name"></span>
                                    <span class="shrink-0 text-xs text-zinc-400 dark:text-zinc-500" x-text="formatSize(file.size)"></span>
                                    <button type="button"
                                            @click="removeFile(index)"
                                            class="ml-1 shrink-0 rounded p-0.5 text-zinc-400 hover:text-red-500 dark:hover:text-red-400 transition-colors"
                                            x-bind:aria-label="'{{ __('Remove') }} ' + file.name">
                                        <svg class="size-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </template>

                    @error('attachments')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                    @enderror
                    @error('attachments.*')
                        <p class="mt-1 text-sm text-red-600 dark:text-red-400" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                {{-- ── Actions ──────────────────────────────────────────────────── --}}
                <div class="flex gap-3">
                    <flux:button variant="primary" type="button" icon="paper-airplane" @click="showSendConfirm = true">
                        {{ __('Send Email') }}
                    </flux:button>
                    <flux:button variant="ghost" href="{{ route('super-admin.emails.index') }}" wire:navigate>
                        {{ __('Cancel') }}
                    </flux:button>
                </div>
            </form>

            {{-- ── Send confirmation dialog ─────────────────────────────────── --}}
            <div x-show="showSendConfirm"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 @keydown.escape.window="showSendConfirm = false"
                 class="fixed inset-0 z-50 flex items-center justify-center p-4"
                 role="dialog" aria-modal="true" aria-labelledby="send-confirm-title"
                 x-cloak>
                <div class="fixed inset-0 bg-black/50 dark:bg-black/70" @click="showSendConfirm = false" aria-hidden="true"></div>
                <div x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-150"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="relative w-full max-w-md rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-6 shadow-xl">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/40 mb-4">
                        <flux:icon name="paper-airplane" class="size-6 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div class="text-center">
                        <h3 id="send-confirm-title" class="text-lg font-semibold text-zinc-900 dark:text-white">{{ __('Send Email?') }}</h3>
                        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('The email will be queued and delivered to all selected schools in the background. You can leave this page immediately.') }}
                        </p>
                        <p class="mt-1.5 text-xs text-zinc-400 dark:text-zinc-500"
                           x-show="files.length > 0"
                           x-text="files.length + ' {{ __('attachment(s) will be included.') }}'"></p>
                    </div>
                    <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-center">
                        <flux:button variant="ghost" @click="showSendConfirm = false" x-bind:disabled="sending">
                            {{ __('Cancel') }}
                        </flux:button>
                        <flux:button variant="primary" icon="paper-airplane"
                                     @click="sending = true; $nextTick(() => { $refs.emailForm.submit(); })"
                                     x-bind:disabled="sending">
                            <span x-show="!sending">{{ __('Yes, Send') }}</span>
                            <span x-show="sending" x-cloak class="inline-flex items-center gap-2">
                                <svg class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ __('Queueing...') }}
                            </span>
                        </flux:button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app>
