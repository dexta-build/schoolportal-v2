<x-layouts::app :title="$school->name">
    @php
        $branding = $school->settings['branding'] ?? [];
        $primaryColor = $branding['primary_color'] ?? '#4F46E5';
        $secondaryColor = $branding['secondary_color'] ?? '#F59E0B';
        $accentColor = $branding['accent_color'] ?? '#10B981';
        $serverIp = config('app.server_ip', '');
        $platformDomain = parse_url(config('app.url'), PHP_URL_HOST) ?: 'your-platform-domain.com';
    @endphp

    <div class="space-y-6">
        <x-admin-header :title="$school->name" :description="$school->motto">
            <flux:button variant="subtle" size="sm" icon="arrow-left" href="{{ route('super-admin.schools.index') }}" wire:navigate>
                {{ __('Back') }}
            </flux:button>
            <flux:button variant="filled" size="sm" icon="pencil-square" href="{{ route('super-admin.schools.edit', $school) }}" wire:navigate>
                {{ __('Edit') }}
            </flux:button>
        </x-admin-header>

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif
        @if (session('error'))
            <flux:callout variant="danger" icon="exclamation-triangle">{{ session('error') }}</flux:callout>
        @endif

        {{-- Status + quick actions --}}
        <div class="flex flex-wrap items-center gap-3 rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center gap-2">
                <flux:text size="sm" class="text-zinc-500">{{ __('Status:') }}</flux:text>
                @if ($school->is_active)
                    <flux:badge color="green">{{ __('Active') }}</flux:badge>
                @else
                    <flux:badge color="zinc">{{ __('Inactive') }}</flux:badge>
                @endif
            </div>
            <div class="ml-auto flex flex-wrap items-center gap-2">
                {{-- Preview Portal branding --}}
                <button
                    type="button"
                    x-data
                    @click="$dispatch('open-preview-portal')"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-zinc-200 bg-white px-3 py-1.5 text-sm font-medium text-zinc-700 shadow-sm transition hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700"
                >
                    <flux:icon.eye class="h-4 w-4 text-zinc-500" />
                    {{ __('Preview Portal') }}
                </button>

                @if ($school->is_active)
                    {{-- Impersonate: log in as this school's admin for support/debugging --}}
                    <form method="POST" action="{{ route('super-admin.schools.impersonate', $school) }}">
                        @csrf
                        <flux:button type="submit" variant="filled" size="sm" icon="eye">
                            {{ __('Login as Admin') }}
                        </flux:button>
                    </form>

                    <flux:modal.trigger name="deactivate-school">
                        <flux:button variant="subtle" size="sm" icon="pause-circle">{{ __('Deactivate') }}</flux:button>
                    </flux:modal.trigger>
                @else
                    <form method="POST" action="{{ route('super-admin.schools.activate', $school) }}">
                        @csrf
                        <flux:button type="submit" variant="subtle" size="sm" icon="play-circle">{{ __('Activate') }}</flux:button>
                    </form>
                @endif
                {{-- Danger zone: typed-confirmation delete modal --}}
                <div
                    x-data="{
                        open: false,
                        submitting: false,
                        nameInput: '',
                        expectedName: '{{ addslashes($school->name) }}',
                        get canDelete() { return this.nameInput === this.expectedName; },
                    }"
                >
                    @if ($school->canBePermanentlyDeleted() && $dataExport)
                        <flux:button
                            variant="danger"
                            size="sm"
                            icon="trash"
                            x-on:click.prevent="open = true"
                            aria-label="{{ __('Permanently delete :name', ['name' => $school->name]) }}"
                        >
                            {{ __('Delete') }}
                        </flux:button>
                    @else
                        {{-- Deleting a school wipes its whole history: deactivate, wait, export its data, then delete --}}
                        @php
                            $deleteHint = match (true) {
                                ! $school->deletableFrom() => __('Deactivate the school first. It can be deleted :days days later.', ['days' => \App\Models\School::DELETION_WAITING_DAYS]),
                                ! $school->canBePermanentlyDeleted() => __('Can be deleted from :date (:days days after deactivation).', ['date' => $school->deletableFrom()->format('j M Y'), 'days' => \App\Models\School::DELETION_WAITING_DAYS]),
                                default => __('Download the data export first, so the school keeps a copy of its records.'),
                            };
                        @endphp
                        <flux:button variant="danger" size="sm" icon="trash" disabled title="{{ $deleteHint }}" aria-describedby="delete-school-hint">
                            {{ __('Delete') }}
                        </flux:button>
                        <p id="delete-school-hint" class="mt-1 max-w-xs text-xs text-zinc-500 dark:text-zinc-400">{{ $deleteHint }}</p>
                    @endif

                    <template x-teleport="body">
                        <div
                            x-show="open"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0"
                            x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            x-on:keydown.escape.window="open = false"
                            class="fixed inset-0 z-50 flex items-center justify-center p-4"
                            role="dialog"
                            aria-modal="true"
                            aria-labelledby="delete-school-modal-title"
                            x-cloak
                        >
                            <div class="fixed inset-0 bg-black/50 dark:bg-black/70" x-on:click="open = false" aria-hidden="true"></div>

                            <div
                                x-show="open"
                                x-transition:enter="transition ease-out duration-200"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-150"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                x-trap.noscroll="open"
                                class="relative w-full max-w-lg rounded-xl bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 p-6 shadow-xl"
                            >
                                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/30 mb-4">
                                    <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                                    </svg>
                                </div>

                                <div class="text-center mb-6">
                                    <h3 id="delete-school-modal-title" class="text-lg font-semibold text-zinc-900 dark:text-white">
                                        {{ __('Permanently Delete School') }}
                                    </h3>
                                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('This will irreversibly delete :name and ALL its data — students, teachers, results, quizzes, games, and audit logs. There is no undo.', ['name' => $school->name]) }}
                                    </p>
                                    <div class="mt-3 flex flex-wrap justify-center gap-4 text-xs font-medium text-red-600 dark:text-red-400">
                                        <span>{{ $school->students_count }} {{ __('students') }}</span>
                                        <span>{{ $school->teachers_count }} {{ __('teachers') }}</span>
                                        <span>{{ $school->admins_count }} {{ __('admins') }}</span>
                                    </div>
                                </div>

                                <form
                                    method="POST"
                                    action="{{ route('super-admin.schools.destroy', $school) }}"
                                    x-on:submit="submitting = true"
                                >
                                    @csrf
                                    @method('DELETE')

                                    <div class="space-y-4">
                                        <div>
                                            <label for="delete-reason" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                                                {{ __('Reason for deletion') }} <span class="text-red-500" aria-hidden="true">*</span>
                                            </label>
                                            <textarea
                                                id="delete-reason"
                                                name="reason"
                                                rows="3"
                                                required
                                                minlength="10"
                                                maxlength="1000"
                                                placeholder="{{ __('Why is this school being permanently deleted?') }}"
                                                class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-sm text-zinc-900 dark:text-white placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-red-500"
                                            ></textarea>
                                        </div>

                                        <div>
                                            <label for="delete-name-confirm" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300 mb-1">
                                                {{ __('Type') }} <strong class="text-zinc-900 dark:text-white select-all">{{ $school->name }}</strong> {{ __('to confirm') }}
                                            </label>
                                            <input
                                                id="delete-name-confirm"
                                                type="text"
                                                name="name_confirmation"
                                                autocomplete="off"
                                                x-model="nameInput"
                                                placeholder="{{ $school->name }}"
                                                class="w-full rounded-lg border border-zinc-300 dark:border-zinc-600 bg-white dark:bg-zinc-900 px-3 py-2 text-sm text-zinc-900 dark:text-white placeholder-zinc-400 focus:outline-none focus:ring-2 focus:ring-red-500"
                                            />
                                        </div>
                                    </div>

                                    <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                        <flux:button variant="ghost" type="button" x-on:click="open = false; nameInput = ''" x-bind:disabled="submitting">
                                            {{ __('Cancel') }}
                                        </flux:button>
                                        <button
                                            type="submit"
                                            x-bind:disabled="!canDelete || submitting"
                                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:focus:ring-offset-zinc-800"
                                        >
                                            <svg x-show="submitting" style="display:none" class="h-4 w-4 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span x-text="submitting ? '{{ __('Deleting…') }}' : '{{ __('Permanently Delete') }}'">{{ __('Permanently Delete') }}</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        @if (! $school->is_active && $school->deactivation_reason)
            <flux:callout variant="danger" icon="exclamation-triangle">
                <flux:callout.heading>{{ __('School Deactivated') }}</flux:callout.heading>
                <flux:callout.text>{{ $school->deactivation_reason }}</flux:callout.text>
                @if ($school->deactivated_at)
                    <flux:callout.text class="text-xs mt-1">{{ __('Deactivated on :date', ['date' => $school->deactivated_at->format('M j, Y g:i A')]) }}</flux:callout.text>
                @endif
            </flux:callout>
        @endif

        {{-- Before deletion: hand the school a full copy of its records --}}
        @if (! $school->is_active && ! $school->isPlatform())
            <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="max-w-2xl">
                        <flux:heading size="sm">{{ __('Data export') }}</flux:heading>
                        <flux:text class="mt-1">
                            {{ __('A ZIP with every record of this school as spreadsheets (CSV): students, scores, report cards, CBT, notices, audit logs and more, plus links to its uploaded files. Passwords are never included. It is required before the school can be deleted.') }}
                        </flux:text>
                        @if ($dataExport)
                            <p class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-green-700 dark:text-green-400">
                                <flux:icon.check-circle class="size-4" />
                                {{ __('Exported :date by :name', ['date' => $dataExport->created_at->format('j M Y, g:i A'), 'name' => $dataExport->user?->name ?? __('a super admin')]) }}
                            </p>
                        @else
                            <p class="mt-2 text-sm font-medium text-amber-700 dark:text-amber-400">{{ __('Not exported yet.') }}</p>
                        @endif
                    </div>
                    <flux:button variant="{{ $dataExport ? 'subtle' : 'primary' }}" size="sm" icon="arrow-down-tray" href="{{ route('super-admin.schools.export-data', $school) }}">
                        {{ $dataExport ? __('Download again') : __('Download data export') }}
                    </flux:button>
                </div>
            </div>
        @endif

        {{-- Deactivate modal --}}
        @if ($school->is_active)
            <flux:modal name="deactivate-school" class="max-w-md">
                <form method="POST" action="{{ route('super-admin.schools.deactivate', $school) }}" class="space-y-4">
                    @csrf
                    <div>
                        <flux:heading size="lg">{{ __('Deactivate :name', ['name' => $school->name]) }}</flux:heading>
                        <flux:text class="mt-1">{{ __('All users of this school will be unable to log in. This message will be shown to them.') }}</flux:text>
                    </div>
                    <flux:textarea
                        name="deactivation_reason"
                        :label="__('Reason for deactivation')"
                        :placeholder="__('e.g. Subscription expired, pending renewal...')"
                        required
                        rows="3"
                    />
                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                        </flux:modal.close>
                        <flux:button type="submit" variant="danger">{{ __('Deactivate') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        @endif

        {{-- Stats grid --}}
        <div class="grid grid-cols-2 gap-3 sm:gap-4 md:grid-cols-4">
            @foreach ([
                ['label' => __('Students'), 'value' => $school->students_count, 'icon' => 'academic-cap'],
                ['label' => __('Teachers'), 'value' => $school->teachers_count, 'icon' => 'user-group'],
                ['label' => __('Parents'), 'value' => $school->parents_count, 'icon' => 'users'],
                ['label' => __('Classes'), 'value' => $school->classes_count, 'icon' => 'building-library'],
            ] as $stat)
                <div class="rounded-lg border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900">
                    <div class="flex items-center gap-2 text-zinc-500">
                        <flux:icon :name="$stat['icon']" class="size-4" />
                        <flux:text size="xs" class="uppercase tracking-wide">{{ $stat['label'] }}</flux:text>
                    </div>
                    <div class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-white">
                        {{ number_format($stat['value']) }}
                    </div>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            {{-- School info --}}
            <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('School Information') }}</flux:heading>
                </div>
                <dl class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach ([
                        __('Email') => $school->email,
                        __('Phone') => $school->phone,
                        __('Website') => $school->website,
                        __('Address') => $school->address,
                        __('City') => $school->city,
                        __('State') => $school->state,
                        __('Country') => $school->country,
                        __('Levels') => $school->levels_count,
                        __('Created') => $school->created_at->format('M j, Y'),
                    ] as $label => $value)
                        <div class="flex items-start justify-between gap-3 px-4 py-2.5">
                            <dt class="text-sm text-zinc-500">{{ $label }}</dt>
                            <dd class="text-right text-sm font-medium">{{ $value ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- School admins --}}
            <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('School Admins') }} ({{ $schoolAdmins->count() }})</flux:heading>
                    <flux:button variant="primary" size="sm" icon="plus" href="{{ route('super-admin.schools.create-admin', $school) }}" wire:navigate>
                        {{ __('Add Admin') }}
                    </flux:button>
                </div>
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($schoolAdmins as $admin)
                        <div class="flex items-center justify-between gap-3 p-4">
                            <div class="flex items-center gap-3 min-w-0">
                                <flux:avatar :name="$admin->name" size="md" />
                                <div class="min-w-0">
                                    <div class="truncate font-medium">{{ $admin->name }}</div>
                                    <flux:text size="sm" class="text-zinc-500">
                                        {{ $admin->email ?? '—' }} · {{ $admin->username }}
                                    </flux:text>
                                    @if ($admin->last_login_at)
                                        <flux:text size="xs" class="text-zinc-500">
                                            {{ __('Last login: :ago', ['ago' => $admin->last_login_at->diffForHumans()]) }}
                                        </flux:text>
                                    @else
                                        <flux:text size="xs" class="text-zinc-500">{{ __('Never logged in') }}</flux:text>
                                    @endif
                                </div>
                            </div>
                            @if ($schoolAdmins->count() > 1)
                                <x-confirm-delete
                                    :action="route('super-admin.schools.destroy-admin', [$school, $admin])"
                                    :title="__('Remove Admin')"
                                    :message="__('Remove :name as admin? They will no longer be able to manage this school.', ['name' => $admin->name])"
                                    :confirmLabel="__('Remove')"
                                    buttonVariant="danger"
                                    buttonSize="xs"
                                    :buttonLabel="__('Remove')"
                                    :ariaLabel="__('Remove :name', ['name' => $admin->name])"
                                />
                            @endif
                        </div>
                    @empty
                        <div class="p-4">
                            <flux:text class="text-zinc-500">{{ __('No admin accounts found for this school.') }}</flux:text>
                        </div>
                    @endforelse
                </div>

                {{-- Reset admin password (for primary admin) --}}
                @if ($primaryAdmin)
                    <div class="border-t border-zinc-200 p-4 dark:border-zinc-700" x-data="{ showForm: false, selectedAdmin: '' }">
                        <flux:button variant="subtle" size="sm" icon="key" x-on:click="showForm = !showForm" aria-controls="reset-pw-form" x-bind:aria-expanded="showForm">
                            {{ __('Reset Admin Password') }}
                        </flux:button>

                        <form
                            method="POST"
                            action="{{ route('super-admin.schools.reset-admin-password', $school) }}"
                            x-show="showForm"
                            x-cloak
                            x-transition
                            id="reset-pw-form"
                            class="mt-3 space-y-3"
                        >
                            @csrf
                            <flux:field>
                                <flux:label for="admin-select">{{ __('Select Admin') }}</flux:label>
                                <select
                                    id="admin-select"
                                    name="admin_id"
                                    required
                                    class="mt-1 block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-800 dark:text-white"
                                >
                                    @foreach ($schoolAdmins as $admin)
                                        <option value="{{ $admin->id }}">{{ $admin->name }} ({{ $admin->username }})</option>
                                    @endforeach
                                </select>
                            </flux:field>
                            <flux:field>
                                <flux:label for="admin-new-pw">{{ __('New Password') }}</flux:label>
                                <flux:input id="admin-new-pw" name="password" type="password" required viewable />
                                <flux:description>{{ __('The admin will be forced to change this on their next login.') }}</flux:description>
                                @error('password') <flux:error>{{ $message }}</flux:error> @enderror
                            </flux:field>
                            <flux:button type="submit" variant="primary" size="sm">{{ __('Reset Password') }}</flux:button>
                        </form>
                    </div>
                @endif
            </div>

            {{-- Branding --}}
            <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900 lg:col-span-2">
                <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('Branding') }}</flux:heading>
                </div>
                <div class="grid grid-cols-1 gap-6 p-4 sm:grid-cols-2">
                    {{-- Logo --}}
                    <div class="space-y-4">
                        <flux:text size="sm" class="font-semibold">{{ __('School Logo') }}</flux:text>
                        <div class="flex items-start gap-4" x-data="{ preview: null }">
                            <div class="shrink-0">
                                {{-- Client-side preview (shown when file selected) --}}
                                <img
                                    x-show="preview"
                                    x-cloak
                                    :src="preview"
                                    alt="{{ __('Logo preview') }}"
                                    class="size-24 rounded-lg border border-zinc-200 object-contain bg-white p-1 dark:border-zinc-700 dark:bg-zinc-800"
                                />
                                {{-- Server-rendered logo or placeholder --}}
                                <div x-show="!preview">
                                    @if ($school->logo_url)
                                        <img
                                            src="{{ $school->logoMediumUrl() }}"
                                            alt="{{ $school->name }} logo"
                                            class="size-24 rounded-lg border border-zinc-200 object-contain bg-white p-1 dark:border-zinc-700 dark:bg-zinc-800"
                                        />
                                    @else
                                        <div class="flex size-24 items-center justify-center rounded-lg border-2 border-dashed border-zinc-300 bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-800">
                                            <flux:icon.photo class="size-8 text-zinc-400" />
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="space-y-2">
                                <form method="POST" action="{{ route('super-admin.schools.upload-logo', $school) }}" enctype="multipart/form-data" class="space-y-2">
                                    @csrf
                                    <input
                                        type="file"
                                        name="logo"
                                        id="logo-upload-{{ $school->id }}"
                                        accept="image/jpeg,image/png,image/webp,image/svg+xml"
                                        class="block w-full text-sm text-zinc-500 file:mr-2 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:file:bg-zinc-700 dark:file:text-zinc-300"
                                        required
                                        @change="
                                            const file = $event.target.files[0];
                                            if (file) {
                                                const reader = new FileReader();
                                                reader.onload = (e) => { preview = e.target.result; };
                                                reader.readAsDataURL(file);
                                            } else {
                                                preview = null;
                                            }
                                        "
                                    />
                                    @error('logo') <flux:error>{{ $message }}</flux:error> @enderror
                                    <flux:text size="xs" class="text-zinc-500">{{ __('JPG, PNG, WebP or SVG. Max 2 MB.') }}</flux:text>
                                    <flux:button type="submit" variant="primary" size="xs" icon="arrow-up-tray">
                                        {{ $school->logo_url ? __('Replace Logo') : __('Upload Logo') }}
                                    </flux:button>
                                </form>
                                @if ($school->logo_url)
                                    <form method="POST" action="{{ route('super-admin.schools.remove-logo', $school) }}">
                                        @csrf
                                        @method('DELETE')
                                        <flux:button type="submit" variant="danger" size="xs" icon="trash">
                                            {{ __('Remove Logo') }}
                                        </flux:button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Colors --}}
                    <div class="space-y-4">
                        <flux:text size="sm" class="font-semibold">{{ __('Brand Colors') }}</flux:text>
                        <div class="space-y-3">
                            @foreach ([
                                __('Primary') => $primaryColor,
                                __('Secondary') => $secondaryColor,
                                __('Accent') => $accentColor,
                            ] as $label => $hex)
                                <div class="flex items-center gap-3">
                                    <span
                                        class="inline-block size-10 rounded-lg border border-zinc-200 dark:border-zinc-700"
                                        style="background-color: {{ $hex }}"
                                        aria-hidden="true"
                                    ></span>
                                    <div>
                                        <div class="text-sm font-medium">{{ $label }}</div>
                                        <div class="font-mono text-xs text-zinc-500">{{ $hex }}</div>
                                    </div>
                                </div>
                            @endforeach
                            <flux:button variant="subtle" size="xs" icon="pencil-square" href="{{ route('super-admin.schools.edit', $school) }}" wire:navigate>
                                {{ __('Edit Colors') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- AI credits --}}
            <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                    <flux:heading size="lg">{{ __('AI Credits') }}</flux:heading>
                    <flux:button variant="subtle" size="sm" href="{{ route('super-admin.credits.index', ['search' => $school->name]) }}" wire:navigate>
                        {{ __('Adjust') }}
                    </flux:button>
                </div>
                <dl class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    <div class="flex items-center justify-between px-4 py-2.5">
                        <dt class="text-sm text-zinc-500">{{ __('Free credits') }}</dt>
                        <dd class="text-sm font-medium">{{ number_format($school->ai_free_credits) }} / 15</dd>
                    </div>
                    <div class="flex items-center justify-between px-4 py-2.5">
                        <dt class="text-sm text-zinc-500">{{ __('Purchased credits') }}</dt>
                        <dd class="text-sm font-medium">{{ number_format($school->ai_purchased_credits) }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-4 py-2.5">
                        <dt class="text-sm text-zinc-500">{{ __('Lifetime purchased') }}</dt>
                        <dd class="text-sm font-medium">{{ number_format($school->ai_credits_total_purchased) }}</dd>
                    </div>
                    <div class="flex items-center justify-between px-4 py-2.5">
                        <dt class="text-sm text-zinc-500">{{ __('Next free reset') }}</dt>
                        <dd class="text-sm font-medium">
                            {{ $school->ai_free_credits_reset_at ? \Illuminate\Support\Carbon::parse($school->ai_free_credits_reset_at)->format('M j, Y') : '—' }}
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Custom domain / DNS --}}
        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Custom Domain & DNS') }}</flux:heading>
            </div>
            <div class="space-y-4 p-4">
                @if ($school->custom_domain)
                    {{-- Domain + verification status --}}
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2">
                            <flux:icon.globe-alt class="size-5 text-zinc-500" />
                            <span class="font-mono text-sm font-medium">{{ $school->custom_domain }}</span>
                            <a href="https://{{ $school->custom_domain }}" target="_blank" rel="noopener noreferrer" class="ml-1 text-xs text-zinc-500 hover:underline">
                                {{ __('Open ↗') }}
                            </a>
                        </div>

                        @if ($school->domain_verified_at)
                            <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">
                                <flux:icon.check-circle class="size-3.5" />
                                {{ __('Verified') }}
                            </span>
                            <span class="text-xs text-zinc-500">{{ $school->domain_verified_at->diffForHumans() }}</span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-400">
                                <flux:icon.clock class="size-3.5" />
                                {{ __('Unverified') }}
                            </span>
                        @endif
                    </div>

                    {{-- Verify button --}}
                    <form method="POST" action="{{ route('super-admin.schools.verify-domain', $school) }}">
                        @csrf
                        <flux:button type="submit" variant="primary" size="sm" icon="arrow-path">
                            {{ $school->domain_verified_at ? __('Re-check Domain') : __('Verify Domain') }}
                        </flux:button>
                    </form>

                    {{-- Flash messages for verification results --}}
                    @if (session('warning'))
                        <flux:callout variant="warning" icon="exclamation-triangle">
                            <flux:text size="sm">{{ session('warning') }}</flux:text>
                        </flux:callout>
                    @endif

                    @unless ($school->domain_verified_at)
                        {{-- DNS instructions (shown only when not verified) --}}
                        <flux:callout variant="secondary" icon="information-circle">
                            <flux:heading size="sm">{{ __('DNS setup required') }}</flux:heading>
                            <flux:text size="sm" class="mt-1">
                                {{ __('Have the school add ONE of the following DNS record options at their domain registrar. For subdomains like portal.pearschool.com, the school adds records for "portal" (not "@").') }}
                            </flux:text>
                        </flux:callout>

                        @php
                            $isSubdomain = substr_count($school->custom_domain, '.') > 1;
                            $dnsName = $isSubdomain ? explode('.', $school->custom_domain, 2)[0] : '@';
                        @endphp

                        {{-- Option A: CNAME record (recommended) --}}
                        <div class="space-y-2">
                            <flux:text size="sm" class="font-semibold">{{ __('Option A — CNAME Record (Recommended)') }}</flux:text>
                            <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                                <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                                    <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800">
                                        <tr>
                                            <th scope="col" class="px-3 py-2">{{ __('Type') }}</th>
                                            <th scope="col" class="px-3 py-2">{{ __('Name') }}</th>
                                            <th scope="col" class="px-3 py-2">{{ __('Value') }}</th>
                                            <th scope="col" class="px-3 py-2">{{ __('TTL') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-zinc-100 font-mono dark:divide-zinc-800">
                                        <tr>
                                            <td class="px-3 py-2">CNAME</td>
                                            <td class="px-3 py-2">{{ $dnsName }}</td>
                                            <td class="px-3 py-2">{{ $platformDomain }}</td>
                                            <td class="px-3 py-2">3600</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            @unless ($isSubdomain)
                                <flux:text size="xs" class="text-zinc-500">
                                    {{ __('Note: Some registrars do not support CNAME on the root (@). In that case, use Option B, or consider using a subdomain like portal.pearschool.com.') }}
                                </flux:text>
                            @endunless
                        </div>

                        {{-- Option B: A record --}}
                        <div class="space-y-2">
                            <flux:text size="sm" class="font-semibold">{{ __('Option B — A Record (if CNAME is not supported)') }}</flux:text>
                            @if ($serverIp)
                                <div class="overflow-x-auto rounded-lg border border-zinc-200 dark:border-zinc-700">
                                    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                                        <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800">
                                            <tr>
                                                <th scope="col" class="px-3 py-2">{{ __('Type') }}</th>
                                                <th scope="col" class="px-3 py-2">{{ __('Name') }}</th>
                                                <th scope="col" class="px-3 py-2">{{ __('Value') }}</th>
                                                <th scope="col" class="px-3 py-2">{{ __('TTL') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-zinc-100 font-mono dark:divide-zinc-800">
                                            <tr>
                                                <td class="px-3 py-2">A</td>
                                                <td class="px-3 py-2">{{ $dnsName }}</td>
                                                <td class="px-3 py-2">{{ $serverIp }}</td>
                                                <td class="px-3 py-2">3600</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <flux:callout variant="warning" icon="exclamation-triangle">
                                    <flux:text size="sm">{{ __('Server IP not configured. Add SERVER_IP to your .env file to display A record instructions.') }}</flux:text>
                                </flux:callout>
                            @endif
                        </div>

                        <flux:text size="sm" class="text-zinc-500">
                            {{ __('After DNS propagates (5 minutes to 48 hours), SSL is auto-provisioned via cPanel AutoSSL. Then click "Verify Domain" above to confirm everything is working.') }}
                        </flux:text>
                    @endunless

                    @if ($school->domain_verified_at)
                        <flux:callout variant="success" icon="check-circle">
                            <flux:text size="sm">
                                {{ __('This domain is verified and serving the portal at') }}
                                <a href="https://{{ $school->custom_domain }}/portal/login" target="_blank" class="font-mono underline">https://{{ $school->custom_domain }}/portal/login</a>.
                            </flux:text>
                        </flux:callout>
                    @endif
                @else
                    <flux:text class="text-zinc-500">
                        {{ __('No custom domain set. Add one in') }}
                        <a href="{{ route('super-admin.schools.edit', $school) }}" wire:navigate class="underline">{{ __('edit') }}</a>.
                    </flux:text>
                @endif
            </div>
        </div>

        {{-- Portal Settings (S17) --}}
        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Portal Settings') }}</flux:heading>
                <flux:subheading>{{ __('Override feature flags, limits, and notifications for this school') }}</flux:subheading>
            </div>
            @if ($errors->any())
                <flux:callout variant="danger" icon="exclamation-triangle" class="m-4">
                    <flux:callout.text>{{ $errors->first() }}</flux:callout.text>
                </flux:callout>
            @endif
            <form
                method="POST"
                action="{{ route('super-admin.schools.update-settings', $school) }}"
                x-data="{
                    portal: {
                        enable_parent_portal:             {{ $school->featureEnabled('enable_parent_portal') ? 'true' : 'false' }},
                        enable_quiz_generator:            {{ $school->featureEnabled('enable_quiz_generator') ? 'true' : 'false' }},
                        enable_game_generator:            {{ $school->featureEnabled('enable_game_generator') ? 'true' : 'false' }},
                        enable_teacher_approval:          {{ $school->featureEnabled('enable_teacher_approval') ? 'true' : 'false' }},
                        enable_cbt_results_for_parents:   {{ $school->featureEnabled('enable_cbt_results_for_parents') ? 'true' : 'false' }},
                    },
                    notifications: {
                        email_enabled:           {{ $school->setting('notifications.email_enabled', true) ? 'true' : 'false' }},
                        notify_parent_on_result: {{ $school->setting('notifications.notify_parent_on_result', true) ? 'true' : 'false' }},
                        notify_parent_on_notice: {{ $school->setting('notifications.notify_parent_on_notice', true) ? 'true' : 'false' }},
                    }
                }"
            >
                @csrf
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    {{-- Feature toggles --}}
                    <div class="p-4">
                        <flux:text size="sm" class="mb-3 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Feature Toggles') }}</flux:text>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ([
                                ['key' => 'enable_parent_portal',    'label' => __('Parent Portal'),          'desc' => __('Allow parents to log in and view their children\u2019s progress')],
                                ['key' => 'enable_quiz_generator',   'label' => __('AI Quiz Generator'),      'desc' => __('Teachers can generate quizzes using AI')],
                                ['key' => 'enable_game_generator',   'label' => __('AI Game Generator'),           'desc' => __('Teachers can generate educational games using AI')],
                                ['key' => 'enable_teacher_approval', 'label' => __('Teacher Approval Flow'),        'desc' => __('Require admin approval before teacher content goes live')],
                                ['key' => 'enable_cbt_results_for_parents', 'label' => __('CBT Results for Parents'), 'desc' => __('Show CBT exam results in the parent dashboard')],
                            ] as $toggle)
                                <div class="flex items-start justify-between gap-3 rounded-lg border border-zinc-100 bg-zinc-50/50 p-3 dark:border-zinc-700/50 dark:bg-zinc-800/30">
                                    <input type="hidden"
                                        name="portal[{{ $toggle['key'] }}]"
                                        :value="portal.{{ $toggle['key'] }} ? '1' : '0'">
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $toggle['label'] }}</div>
                                        <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $toggle['desc'] }}</div>
                                    </div>
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="portal.{{ $toggle['key'] }}.toString()"
                                        @click="portal.{{ $toggle['key'] }} = !portal.{{ $toggle['key'] }}"
                                        :class="portal.{{ $toggle['key'] }} ? 'bg-indigo-600 dark:bg-indigo-500' : 'bg-zinc-300 dark:bg-zinc-600'"
                                        class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900"
                                        aria-label="{{ $toggle['label'] }}"
                                    >
                                        <span
                                            :class="portal.{{ $toggle['key'] }} ? 'translate-x-5' : 'translate-x-0'"
                                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        ></span>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Limits --}}
                    <div class="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label for="settings-session-timeout">{{ __('Session Timeout') }}</flux:label>
                            <div class="flex items-center gap-2">
                                <flux:input
                                    id="settings-session-timeout"
                                    name="portal[session_timeout_minutes]"
                                    type="number"
                                    min="5"
                                    max="1440"
                                    :value="$school->setting('portal.session_timeout_minutes', 30)"
                                    class="w-28"
                                />
                                <flux:text size="sm" class="text-zinc-500">{{ __('minutes') }}</flux:text>
                            </div>
                            <flux:description>{{ __('How long before an inactive session expires (5–1440 min)') }}</flux:description>
                            @error('portal.session_timeout_minutes') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>
                        <flux:field>
                            <flux:label for="settings-max-upload">{{ __('Max File Upload') }}</flux:label>
                            <div class="flex items-center gap-2">
                                <flux:input
                                    id="settings-max-upload"
                                    name="portal[max_file_upload_mb]"
                                    type="number"
                                    min="1"
                                    max="100"
                                    :value="$school->setting('portal.max_file_upload_mb', 10)"
                                    class="w-28"
                                />
                                <flux:text size="sm" class="text-zinc-500">{{ __('MB') }}</flux:text>
                            </div>
                            <flux:description>{{ __('Maximum size for uploaded files (results, assignments, etc.)') }}</flux:description>
                            @error('portal.max_file_upload_mb') <flux:error>{{ $message }}</flux:error> @enderror
                        </flux:field>
                    </div>

                    {{-- Email notifications --}}
                    <div class="p-4">
                        <flux:text size="sm" class="mb-3 font-semibold text-zinc-700 dark:text-zinc-300">{{ __('Email Notifications') }}</flux:text>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ([
                                ['key' => 'email_enabled',            'label' => __('Email Notifications'),  'desc' => __('Master switch: send any emails to users of this school')],
                                ['key' => 'notify_parent_on_result',  'label' => __('Notify on Result'),     'desc' => __('Email parents when a student result is published')],
                                ['key' => 'notify_parent_on_notice',  'label' => __('Notify on Notice'),     'desc' => __('Email parents and students when a new notice is posted')],
                            ] as $toggle)
                                <div class="flex items-start justify-between gap-3 rounded-lg border border-zinc-100 bg-zinc-50/50 p-3 dark:border-zinc-700/50 dark:bg-zinc-800/30">
                                    <input type="hidden"
                                        name="notifications[{{ $toggle['key'] }}]"
                                        :value="notifications.{{ $toggle['key'] }} ? '1' : '0'">
                                    <div class="min-w-0">
                                        <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $toggle['label'] }}</div>
                                        <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $toggle['desc'] }}</div>
                                    </div>
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="notifications.{{ $toggle['key'] }}.toString()"
                                        @click="notifications.{{ $toggle['key'] }} = !notifications.{{ $toggle['key'] }}"
                                        :class="notifications.{{ $toggle['key'] }} ? 'bg-indigo-600 dark:bg-indigo-500' : 'bg-zinc-300 dark:bg-zinc-600'"
                                        class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-zinc-900"
                                        aria-label="{{ $toggle['label'] }}"
                                    >
                                        <span
                                            :class="notifications.{{ $toggle['key'] }} ? 'translate-x-5' : 'translate-x-0'"
                                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                        ></span>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" size="sm" icon="check">
                        {{ __('Save Settings') }}
                    </flux:button>
                </div>
            </form>
        </div>

        {{-- Feature Locks (§2.4) --}}
        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Feature Locks') }}</flux:heading>
                <flux:subheading>{{ __('Lock features ON or OFF for this school — overrides the school admin\'s own settings. Leave unlocked to let the school admin decide.') }}</flux:subheading>
            </div>
            <form method="POST" action="{{ route('super-admin.schools.lock-features', $school) }}">
                @csrf
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @foreach (\App\Models\PlatformSetting::FEATURE_FLAGS as $flagKey => $flagLabel)
                        @php $lock = $school->featureLock($flagKey); @endphp
                        <div
                            class="grid grid-cols-1 items-center gap-3 p-4 sm:grid-cols-3"
                            x-data="{ mode: '{{ $lock === null ? 'unlocked' : ($lock ? 'on' : 'off') }}' }"
                        >
                            {{-- Description --}}
                            <div class="sm:col-span-1">
                                <div class="text-sm font-medium text-zinc-900 dark:text-zinc-100">{{ $flagLabel }}</div>
                                <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                    @if ($lock === null)
                                        <span class="text-zinc-400">{{ __('School admin controls this') }}</span>
                                    @elseif ($lock)
                                        <span class="font-medium text-emerald-600 dark:text-emerald-400">{{ __('Locked ON by super admin') }}</span>
                                    @else
                                        <span class="font-medium text-red-500 dark:text-red-400">{{ __('Locked OFF by super admin') }}</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Lock controls --}}
                            <div class="flex items-center gap-2 sm:col-span-2">
                                <input type="hidden" name="locks[{{ $flagKey }}]" x-bind:value="mode === 'on' ? '1' : (mode === 'off' ? '0' : '')">
                                <div class="inline-flex rounded-lg border border-zinc-200 bg-zinc-50 p-0.5 text-xs dark:border-zinc-700 dark:bg-zinc-800">
                                    <button
                                        type="button"
                                        @click="mode = 'off'"
                                        :class="mode === 'off' ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300 font-semibold' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                                        class="rounded-md px-3 py-1.5 transition-colors"
                                    >{{ __('Lock OFF') }}</button>
                                    <button
                                        type="button"
                                        @click="mode = 'unlocked'"
                                        :class="mode === 'unlocked' ? 'bg-white text-zinc-800 dark:bg-zinc-700 dark:text-zinc-100 font-semibold shadow-sm' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                                        class="rounded-md px-3 py-1.5 transition-colors"
                                    >{{ __('Unlocked') }}</button>
                                    <button
                                        type="button"
                                        @click="mode = 'on'"
                                        :class="mode === 'on' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300 font-semibold' : 'text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300'"
                                        class="rounded-md px-3 py-1.5 transition-colors"
                                    >{{ __('Lock ON') }}</button>
                                </div>
                                <div class="text-xs text-zinc-400" x-show="mode !== 'unlocked'" x-cloak>
                                    <flux:icon.lock-closed class="inline-block h-3.5 w-3.5 text-zinc-400" />
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-zinc-100 px-4 py-3 dark:border-zinc-800">
                    <flux:button type="submit" variant="primary" size="sm" icon="lock-closed">
                        {{ __('Save Locks') }}
                    </flux:button>
                </div>
            </form>
        </div>

        {{-- School Levels & Classes --}}
        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('School Levels & Classes') }}</flux:heading>
            </div>
            <div class="p-4">
                @if ($levels->isEmpty())
                    <flux:text class="text-zinc-500">{{ __('No levels configured. Set them up via the School Setup Wizard or by editing the school.') }}</flux:text>
                @else
                    <div class="space-y-4">
                        @foreach ($levels as $level)
                            <div class="rounded-md border border-zinc-200 dark:border-zinc-700">
                                <div class="flex items-center justify-between border-b border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-700 dark:bg-zinc-800">
                                    <div class="flex items-center gap-2">
                                        <flux:icon.academic-cap class="size-4 text-zinc-500" />
                                        <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $level->name }}</span>
                                        <span class="text-xs text-zinc-500">({{ $level->classes->count() }} {{ Str::plural('class', $level->classes->count()) }})</span>
                                    </div>
                                    @if ($level->is_active)
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">{{ __('Active') }}</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-zinc-100 px-2 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-400">{{ __('Inactive') }}</span>
                                    @endif
                                </div>
                                @if ($level->classes->isEmpty())
                                    <p class="px-3 py-2 text-sm text-zinc-400">{{ __('No classes in this level.') }}</p>
                                @else
                                    <div class="flex flex-wrap gap-2 px-3 py-2">
                                        @foreach ($level->classes as $class)
                                            <span class="inline-flex items-center gap-1 rounded border border-zinc-200 bg-white px-2 py-0.5 text-xs text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                                {{ $class->name }}
                                                @unless ($class->is_active)
                                                    <span class="text-zinc-400">(off)</span>
                                                @endunless
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Academic Session --}}
        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Academic Session') }}</flux:heading>
            </div>
            <div class="p-4">
                @if ($currentSession)
                    <div class="flex flex-wrap items-center gap-3">
                        <flux:icon.calendar class="size-5 text-zinc-500" />
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $currentSession->name }}</span>
                        <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800 dark:bg-green-900/30 dark:text-green-400">{{ __('Active') }}</span>
                        <span class="text-sm text-zinc-500">{{ $currentSession->start_date->format('M j, Y') }} – {{ $currentSession->end_date->format('M j, Y') }}</span>
                    </div>

                    @if ($currentTerm)
                        <div class="mt-3 flex flex-wrap items-center gap-2 pl-7">
                            <flux:icon.arrow-right class="size-4 text-zinc-400" />
                            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ $currentTerm->name }}</span>
                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">{{ __('Current Term') }}</span>
                            @if ($currentTerm->start_date && $currentTerm->end_date)
                                <span class="text-xs text-zinc-500">{{ $currentTerm->start_date->format('M j') }} – {{ $currentTerm->end_date->format('M j, Y') }}</span>
                            @endif
                            <span class="text-xs text-zinc-500 capitalize">• {{ $currentTerm->status }}</span>
                        </div>
                    @else
                        <p class="mt-2 pl-7 text-sm text-zinc-400">{{ __('No active term set for this session.') }}</p>
                    @endif
                @else
                    <flux:text class="text-zinc-500">{{ __('No active academic session. The school admin can create one in Session Management.') }}</flux:text>
                @endif
            </div>
        </div>

        {{-- Portal Settings --}}
        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Portal Settings') }}</flux:heading>
            </div>
            @php
                $portalItems = [
                    ['label' => __('Session Timeout'),       'value' => $school->setting('portal.session_timeout_minutes', 30) . ' min'],
                    ['label' => __('Max File Upload'),        'value' => $school->setting('portal.max_file_upload_mb', 10) . ' MB'],
                    ['label' => __('Parent Portal'),          'value' => $school->featureEnabled('enable_parent_portal') ? __('Enabled') : __('Disabled'), 'bool' => $school->featureEnabled('enable_parent_portal')],
                    ['label' => __('Teacher Approval'),       'value' => $school->featureEnabled('enable_teacher_approval') ? __('Required') : __('Disabled'), 'bool' => $school->featureEnabled('enable_teacher_approval')],
                    ['label' => __('AI Quiz Generator'),      'value' => $school->featureEnabled('enable_quiz_generator') ? __('Enabled') : __('Disabled'), 'bool' => $school->featureEnabled('enable_quiz_generator')],
                    ['label' => __('AI Game Generator'),      'value' => $school->featureEnabled('enable_game_generator') ? __('Enabled') : __('Disabled'), 'bool' => $school->featureEnabled('enable_game_generator')],
                    ['label' => __('CBT Results (Parents)'),  'value' => $school->featureEnabled('enable_cbt_results_for_parents') ? __('Visible') : __('Hidden'), 'bool' => $school->featureEnabled('enable_cbt_results_for_parents')],
                ];
            @endphp
            <div class="grid grid-cols-2 divide-x divide-y divide-zinc-100 sm:grid-cols-3 lg:grid-cols-4 dark:divide-zinc-800">
                @foreach ($portalItems as $item)
                    <div class="px-4 py-3">
                        <dt class="text-xs text-zinc-500">{{ $item['label'] }}</dt>
                        <dd class="mt-1 text-sm font-medium
                            @if (isset($item['bool']))
                                {{ $item['bool'] ? 'text-green-700 dark:text-green-400' : 'text-zinc-400' }}
                            @else
                                text-zinc-900 dark:text-zinc-100
                            @endif">
                            {{ $item['value'] }}
                        </dd>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Content Library (S16) --}}
        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="flex items-center justify-between border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <div>
                    <flux:heading size="lg">{{ __('Content Library') }}</flux:heading>
                    <flux:subheading>{{ __('All educational content created for this school') }}</flux:subheading>
                </div>
                <flux:button
                    variant="subtle"
                    size="sm"
                    icon="arrow-top-right-on-square"
                    href="{{ route('super-admin.content.index', ['search' => $school->name]) }}"
                    wire:navigate
                >
                    {{ __('Platform View') }}
                </flux:button>
            </div>
            <div class="grid grid-cols-2 gap-px bg-zinc-100 dark:bg-zinc-800 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ([
                    ['key' => 'quizzes',     'label' => __('Quizzes'),     'icon' => 'document-text'],
                    ['key' => 'games',       'label' => __('Games'),       'icon' => 'puzzle-piece'],
                    ['key' => 'exams',       'label' => __('Exams'),       'icon' => 'clipboard-document-list'],
                    ['key' => 'results',     'label' => __('Results'),     'icon' => 'chart-bar'],
                    ['key' => 'assignments', 'label' => __('Assignments'), 'icon' => 'paper-clip'],
                ] as $ct)
                    @php $counts = $contentCounts[$ct['key']]; @endphp
                    <div class="bg-white p-4 dark:bg-zinc-900">
                        <div class="flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                            <flux:icon :name="$ct['icon']" class="size-4 shrink-0" />
                            <span class="text-xs font-medium uppercase tracking-wide">{{ $ct['label'] }}</span>
                        </div>
                        <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">
                            {{ number_format($counts['total']) }}
                        </div>
                        <div class="mt-1 space-y-0.5">
                            @if (isset($counts['published']) && $counts['published'] > 0)
                                <div class="flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                                    <span class="size-1.5 rounded-full bg-green-500"></span>
                                    {{ $counts['published'] }} {{ __('published') }}
                                </div>
                            @endif
                            @if (isset($counts['approved']) && $counts['approved'] > 0)
                                <div class="flex items-center gap-1 text-xs text-green-600 dark:text-green-400">
                                    <span class="size-1.5 rounded-full bg-green-500"></span>
                                    {{ $counts['approved'] }} {{ __('approved') }}
                                </div>
                            @endif
                            @if (($counts['pending'] ?? 0) > 0)
                                <div class="flex items-center gap-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                                    <span class="size-1.5 rounded-full bg-amber-500"></span>
                                    {{ $counts['pending'] }} {{ __('pending') }}
                                </div>
                            @endif
                            @if ($counts['total'] === 0)
                                <div class="text-xs text-zinc-400">{{ __('None yet') }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Audit Log --}}
        <div class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-700">
                <flux:heading size="lg">{{ __('Recent Activity') }}</flux:heading>
                <flux:subheading>{{ __('Last 20 events for this school') }}</flux:subheading>
            </div>
            <div class="p-4">
                @if ($auditLogs->isEmpty())
                    <flux:text class="text-zinc-500">{{ __('No activity recorded yet.') }}</flux:text>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-700">
                            <thead class="bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800">
                                <tr>
                                    <th scope="col" class="px-3 py-2">{{ __('When') }}</th>
                                    <th scope="col" class="px-3 py-2">{{ __('Who') }}</th>
                                    <th scope="col" class="px-3 py-2">{{ __('Action') }}</th>
                                    <th scope="col" class="px-3 py-2">{{ __('Entity') }}</th>
                                    <th scope="col" class="px-3 py-2">{{ __('IP') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                                @foreach ($auditLogs as $log)
                                    <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                                        <td class="whitespace-nowrap px-3 py-2 text-zinc-500" title="{{ $log->created_at->format('Y-m-d H:i:s') }}">
                                            {{ $log->created_at->diffForHumans() }}
                                        </td>
                                        <td class="px-3 py-2">
                                            @if ($log->user)
                                                <span class="font-medium text-zinc-900 dark:text-zinc-100">{{ $log->user->name }}</span>
                                                <span class="ml-1 text-xs text-zinc-400">({{ $log->user->role }})</span>
                                            @else
                                                <span class="text-zinc-400">{{ __('System') }}</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2">
                                            <code class="rounded bg-zinc-100 px-1.5 py-0.5 text-xs font-mono text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">{{ $log->action }}</code>
                                        </td>
                                        <td class="px-3 py-2 text-xs text-zinc-500">
                                            @if ($log->entity_type)
                                                {{ $log->entity_type }}
                                                @if ($log->entity_id) <span class="text-zinc-400">#{{ $log->entity_id }}</span>@endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-zinc-500">
                                            {{ $log->ip_address ?? '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════════
         S5 — Portal Branding Preview Modal
         Shows a faithful mockup of what the school's login page looks like
         using their actual colors, logo, name, motto, and role buttons.
    ══════════════════════════════════════════════════════════════════════════ --}}
    @php
        $previewPrimary   = $primaryColor;
        $previewSecondary = $secondaryColor;
        $previewAccent    = $accentColor;
        $previewTextOnPrimary = '#ffffff'; // white works on almost all dark brand colors
        // Derive a subtle tinted background for the login page body
        // We'll just use a CSS inline style so no Tailwind scan needed
    @endphp

    <div
        x-data="{ open: false }"
        @open-preview-portal.window="open = true"
        @keydown.escape.window="open = false"
    >
        {{-- Backdrop + modal shell --}}
        <template x-teleport="body">
            <div
                x-show="open"
                x-transition:enter="transition ease-out duration-250"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4"
                role="dialog" aria-modal="true" aria-labelledby="preview-portal-title"
                x-cloak
            >
                <div @click="open = false" class="absolute inset-0 bg-black/60 backdrop-blur-sm" aria-hidden="true"></div>

                {{-- Modal panel --}}
                <div
                    x-show="open"
                    x-transition:enter="transition ease-out duration-250"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100"
                    x-transition:leave-end="opacity-0 scale-95"
                    class="relative z-10 flex w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-zinc-50 shadow-2xl dark:border-zinc-700 dark:bg-zinc-900"
                >
                    {{-- Modal header --}}
                    <div class="flex items-center justify-between border-b border-zinc-200 bg-white px-5 py-3 dark:border-zinc-700 dark:bg-zinc-800">
                        <div class="flex items-center gap-2.5">
                            <div class="flex gap-1.5">
                                <div class="h-3 w-3 rounded-full bg-red-400"></div>
                                <div class="h-3 w-3 rounded-full bg-amber-400"></div>
                                <div class="h-3 w-3 rounded-full bg-emerald-400"></div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <flux:icon.globe-alt class="h-3.5 w-3.5 text-zinc-400" />
                                <span class="font-mono text-xs text-zinc-500">
                                    {{ $school->custom_domain ? 'https://' . $school->custom_domain . '/portal/login' : url('/portal/login') }}
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <p id="preview-portal-title" class="text-xs font-medium text-zinc-500">
                                {{ __('Portal Branding Preview — :name', ['name' => $school->name]) }}
                            </p>
                            <button
                                type="button"
                                @click="open = false"
                                class="rounded-md p-1 text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200"
                                aria-label="{{ __('Close preview') }}"
                            >
                                <flux:icon.x-mark class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    {{-- ── Simulated Browser Viewport ────────────────────────────────── --}}
                    {{-- Mirrors the actual two-column auth/modern.blade.php layout --}}
                    <div class="flex min-h-[480px] overflow-hidden">

                        {{-- LEFT: Brand panel (mirrors .login-brand-panel) --}}
                        <div
                            class="relative hidden w-5/12 flex-col justify-between overflow-hidden p-8 sm:flex"
                            style="background: {{ $previewPrimary }};"
                        >
                            {{-- Grid overlay --}}
                            <div class="pointer-events-none absolute inset-0" style="background-image: repeating-linear-gradient(0deg,rgba(255,255,255,.03) 0px,rgba(255,255,255,.03) 1px,transparent 1px,transparent 40px),repeating-linear-gradient(90deg,rgba(255,255,255,.03) 0px,rgba(255,255,255,.03) 1px,transparent 1px,transparent 40px);"></div>
                            {{-- Orbs --}}
                            <div class="pointer-events-none absolute -right-8 -top-8 h-40 w-40 rounded-full opacity-30" style="background: {{ $previewSecondary }}; filter: blur(50px);"></div>
                            <div class="pointer-events-none absolute -bottom-6 -left-6 h-28 w-28 rounded-full opacity-25" style="background: {{ $previewPrimary }}; filter: blur(40px);"></div>

                            {{-- Top: school logo + name --}}
                            <div class="relative z-10 flex items-center gap-2.5">
                                @if ($school->logo_url)
                                    <img src="{{ $school->logoSmallUrl() }}" alt="" class="h-8 w-8 rounded-lg object-contain bg-white/10 p-0.5" />
                                @else
                                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 text-sm font-bold text-white">
                                        {{ mb_strtoupper(mb_substr($school->name, 0, 1)) }}
                                    </div>
                                @endif
                                <span class="text-sm font-bold text-white">{{ $school->name }}</span>
                            </div>

                            {{-- Middle: welcome heading + motto --}}
                            <div class="relative z-10">
                                <h2 class="text-xl font-extrabold leading-tight text-white">
                                    {{ __('Welcome to') }}
                                    <span style="color: {{ $previewSecondary }};">{{ $school->name }}</span>
                                </h2>
                                @if ($school->motto)
                                    <p class="mt-1.5 text-xs leading-relaxed text-white/60">{{ $school->motto }}</p>
                                @endif

                                {{-- Mini feature cards --}}
                                <div class="mt-5 space-y-2">
                                    @foreach ([
                                        [__('AI-Powered Quizzes'),          __('Generate from documents in seconds')],
                                        [__('Results & Analytics'),          __('Track student performance')],
                                        [__('Parent–Teacher Collaboration'), __('Keep parents in the loop')],
                                    ] as $card)
                                        <div class="flex items-center gap-2.5 rounded-lg border border-white/10 bg-white/10 px-3 py-2 backdrop-blur-sm">
                                            <div class="h-1.5 w-1.5 rounded-full flex-shrink-0" style="background: {{ $previewSecondary }};"></div>
                                            <div>
                                                <p class="text-[11px] font-semibold text-white">{{ $card[0] }}</p>
                                                <p class="text-[10px] text-white/50">{{ $card[1] }}</p>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Bottom: copyright --}}
                            <div class="relative z-10">
                                <p class="text-[10px] text-white/30">&copy; {{ date('Y') }} {{ $school->name }}.</p>
                            </div>
                        </div>

                        {{-- RIGHT: Login form panel --}}
                        <div class="flex flex-1 flex-col items-center justify-center bg-white px-8 py-10 dark:bg-zinc-900">
                            <div class="w-full max-w-xs space-y-5">

                                {{-- School logo + heading --}}
                                <div class="flex flex-col items-center gap-2 text-center">
                                    @if ($school->logo_url)
                                        <img src="{{ $school->logoSmallUrl() }}" alt="" class="h-12 w-12 rounded-xl object-contain shadow-md" />
                                    @endif
                                    <h3 class="text-base font-bold text-zinc-900">
                                        {{ __('Welcome to') }}
                                        <span style="color: {{ $previewPrimary }};">{{ $school->name }}</span>
                                    </h3>
                                    @if ($school->motto)
                                        <p class="text-[11px] italic text-zinc-500">{{ $school->motto }}</p>
                                    @endif
                                    <div class="mt-0.5 h-0.5 w-10 rounded-full" style="background: {{ $previewPrimary }};"></div>
                                </div>

                                {{-- Fake form fields --}}
                                <div class="space-y-3">
                                    <div>
                                        <p class="mb-1 text-[11px] font-medium text-zinc-600">{{ __('Username or Email') }}</p>
                                        <div class="flex h-9 w-full items-center rounded-lg border border-zinc-200 bg-zinc-50 px-3 text-[11px] text-zinc-400">
                                            {{ __('Enter your username or email') }}&hellip;
                                        </div>
                                    </div>
                                    <div>
                                        <div class="mb-1 flex items-center justify-between">
                                            <p class="text-[11px] font-medium text-zinc-600">{{ __('Password') }}</p>
                                            <span class="text-[10px]" style="color: {{ $previewPrimary }};">{{ __('Forgot your password?') }}</span>
                                        </div>
                                        <div class="flex h-9 w-full items-center rounded-lg border border-zinc-200 bg-zinc-50 px-3 text-[11px] text-zinc-400">
                                            ••••••••
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-1.5">
                                        <div class="h-3.5 w-3.5 rounded border border-zinc-300 bg-white"></div>
                                        <span class="text-[11px] text-zinc-500">{{ __('Remember me') }}</span>
                                    </div>
                                    <div
                                        class="flex h-10 w-full items-center justify-center gap-1.5 rounded-lg text-xs font-semibold text-white shadow-sm"
                                        style="background: {{ $previewPrimary }};"
                                    >
                                        {{ __('Log in') }}
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
                                    </div>
                                </div>

                                <p class="text-center text-[10px] text-zinc-400">&copy; {{ date('Y') }} DX-SchoolPortal</p>
                            </div>
                        </div>
                    </div>

                    {{-- Color swatches footer --}}
                    <div class="flex items-center gap-4 border-t border-zinc-200 bg-white px-5 py-3 dark:border-zinc-700 dark:bg-zinc-800">
                        <p class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Brand Colors:') }}</p>
                        @foreach ([
                            ['label' => __('Primary'),   'color' => $previewPrimary],
                            ['label' => __('Secondary'), 'color' => $previewSecondary],
                            ['label' => __('Accent'),    'color' => $previewAccent],
                        ] as $swatch)
                            <div class="flex items-center gap-1.5">
                                <div
                                    class="h-4 w-4 rounded-full border border-white shadow-sm"
                                    style="background: {{ $swatch['color'] }};"
                                    title="{{ $swatch['label'] }}: {{ $swatch['color'] }}"
                                ></div>
                                <span class="font-mono text-[11px] text-zinc-500 dark:text-zinc-400">{{ $swatch['color'] }}</span>
                                <span class="text-[10px] text-zinc-400">{{ $swatch['label'] }}</span>
                            </div>
                        @endforeach
                        <div class="ml-auto">
                            <flux:button
                                size="sm" variant="subtle"
                                icon="pencil-square"
                                href="{{ route('super-admin.schools.edit', $school) }}"
                                wire:navigate
                            >
                                {{ __('Edit Branding') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-layouts::app>
