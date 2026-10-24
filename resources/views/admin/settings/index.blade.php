<x-layouts::app :title="__('School Settings')">
    <div class="space-y-6">
        <x-admin-header :title="__('School Settings')" />

        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif
        @if (session('error'))
            <flux:callout variant="danger" icon="exclamation-triangle">{{ session('error') }}</flux:callout>
        @endif

        <div class="max-w-2xl space-y-8">
            {{-- School Information --}}
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                <flux:heading size="sm" class="mb-4">{{ __('School Information') }}</flux:heading>
                <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    <flux:input name="name" :label="__('School Name')" :value="old('name', $school->name)" required />

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:input name="email" :label="__('Email')" :value="old('email', $school->email)" type="email" required />
                        <flux:input name="phone" :label="__('Phone')" :value="old('phone', $school->phone)" />
                    </div>

                    <flux:textarea name="address" :label="__('Address')" rows="2">{{ old('address', $school->address) }}</flux:textarea>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:input name="city" :label="__('City')" :value="old('city', $school->city)" />
                        <flux:input name="state" :label="__('State')" :value="old('state', $school->state)" />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:input name="website" :label="__('Website')" :value="old('website', $school->website)" type="url" />
                        <flux:input name="motto" :label="__('Motto')" :value="old('motto', $school->motto)" />
                    </div>

                    <div>
                        <flux:select name="timezone" :label="__('Timezone')" searchable>
                            @foreach (timezone_identifiers_list() as $tz)
                                <flux:select.option :value="$tz" :selected="old('timezone', $school->timezone ?? 'Africa/Lagos') === $tz">
                                    {{ $tz }} ({{ now()->setTimezone($tz)->format('P') }})
                                </flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:description class="mt-1">{{ __('All dates and times in the portal will use this timezone.') }}</flux:description>
                    </div>

                    <flux:button variant="primary" type="submit">{{ __('Save Information') }}</flux:button>
                </form>
            </div>

            {{-- Branding --}}
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                <flux:heading size="sm" class="mb-2">{{ __('Branding') }}</flux:heading>
                <flux:text size="sm" class="mb-4 text-zinc-500">{{ __('Logo and colors that appear throughout your school portal.') }}</flux:text>

                {{-- Logo upload --}}
                <div class="mb-6 pb-6 border-b border-zinc-200 dark:border-zinc-700"
                     x-data="{ preview: null }"
                >
                    <flux:text size="sm" class="font-semibold mb-3">{{ __('School Logo') }}</flux:text>
                    <div class="flex items-start gap-4">
                        <div class="shrink-0">
                            {{-- Client-side preview (shown when file selected) --}}
                            <img
                                x-show="preview"
                                x-cloak
                                :src="preview"
                                alt="{{ __('Logo preview') }}"
                                class="size-20 rounded-lg border border-zinc-200 object-contain bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900"
                            />
                            {{-- Server-rendered logo or placeholder --}}
                            <div x-show="!preview">
                                @if ($school->logo_url)
                                    <img
                                        src="{{ $school->logoSmallUrl() }}"
                                        alt="{{ $school->name }} logo"
                                        class="size-20 rounded-lg border border-zinc-200 object-contain bg-white p-1 dark:border-zinc-700 dark:bg-zinc-900"
                                    />
                                @else
                                    <div class="flex size-20 items-center justify-center rounded-lg border-2 border-dashed border-zinc-300 bg-zinc-50 dark:border-zinc-600 dark:bg-zinc-900">
                                        <flux:icon.photo class="size-8 text-zinc-400" />
                                    </div>
                                @endif
                            </div>
                        </div>
                        <div class="space-y-3">
                            <form method="POST" action="{{ route('admin.settings.upload-logo') }}" enctype="multipart/form-data" class="space-y-2">
                                @csrf
                                <input
                                    type="file"
                                    name="logo"
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
                                <flux:button type="submit" variant="primary" size="sm" icon="arrow-up-tray">
                                    {{ $school->logo_url ? __('Replace Logo') : __('Upload Logo') }}
                                </flux:button>
                            </form>
                            @if ($school->logo_url)
                                <form method="POST" action="{{ route('admin.settings.remove-logo') }}">
                                    @csrf
                                    @method('DELETE')
                                    <flux:button type="submit" variant="danger" size="sm" icon="trash">
                                        {{ __('Remove Logo') }}
                                    </flux:button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Colors --}}
                @php
                    $brandPrimary = $school->settings['branding']['primary_color'] ?? '#4F46E5';
                    $brandSecondary = $school->settings['branding']['secondary_color'] ?? '#F59E0B';
                    $brandAccent = $school->settings['branding']['accent_color'] ?? '#10B981';
                @endphp

                <form
                    method="POST"
                    action="{{ route('admin.settings.branding') }}"
                    class="space-y-4"
                    x-data="{
                        primary: '{{ old('primary_color', $brandPrimary) }}',
                        secondary: '{{ old('secondary_color', $brandSecondary) }}',
                        accent: '{{ old('accent_color', $brandAccent) }}',
                    }"
                >
                    @csrf
                    @method('PUT')

                    <flux:text size="sm" class="font-semibold">{{ __('Brand Colors') }}</flux:text>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        @foreach ([
                            ['label' => __('Primary'), 'name' => 'primary_color', 'model' => 'primary'],
                            ['label' => __('Secondary'), 'name' => 'secondary_color', 'model' => 'secondary'],
                            ['label' => __('Accent'), 'name' => 'accent_color', 'model' => 'accent'],
                        ] as $color)
                            <div class="space-y-2">
                                <label for="{{ $color['name'] }}" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
                                    {{ $color['label'] }}
                                </label>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="color"
                                        id="{{ $color['name'] }}"
                                        name="{{ $color['name'] }}"
                                        x-model="{{ $color['model'] }}"
                                        class="h-10 w-12 shrink-0 cursor-pointer rounded border border-zinc-300 bg-transparent p-1 dark:border-zinc-600"
                                    />
                                    <input
                                        type="text"
                                        x-model="{{ $color['model'] }}"
                                        class="block w-full rounded-md border border-zinc-300 bg-white px-3 py-2 font-mono text-sm text-zinc-900 shadow-sm focus:border-[var(--color-primary)] focus:ring-[var(--color-primary)] dark:border-zinc-600 dark:bg-zinc-800 dark:text-white"
                                        pattern="^#[0-9A-Fa-f]{6}$"
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>

                    {{-- Live preview --}}
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <flux:text size="xs" class="mb-2 uppercase tracking-wide text-zinc-500">{{ __('Preview') }}</flux:text>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex h-9 items-center rounded-md px-3 text-sm font-medium text-white" :style="`background-color: ${primary}`">{{ __('Primary') }}</span>
                            <span class="inline-flex h-9 items-center rounded-md px-3 text-sm font-medium text-white" :style="`background-color: ${secondary}`">{{ __('Secondary') }}</span>
                            <span class="inline-flex h-9 items-center rounded-md px-3 text-sm font-medium text-white" :style="`background-color: ${accent}`">{{ __('Accent') }}</span>
                        </div>
                    </div>

                    <flux:button variant="primary" type="submit">{{ __('Save Branding') }}</flux:button>
                </form>
            </div>

            {{-- Portal Settings --}}
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                <flux:heading size="sm" class="mb-4">{{ __('Portal Settings') }}</flux:heading>
                <form method="POST" action="{{ route('admin.settings.portal') }}" class="space-y-4">
                    @csrf
                    @method('PUT')

                    @php
                        $portalSettings = $school->settings['portal'] ?? [];
                    @endphp

                    <div class="space-y-3">
                        <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wide">{{ __('General Features') }}</p>
                        @foreach ([
                            'enable_parent_portal'             => __('Enable Parent Portal'),
                            'enable_quiz_generator'            => __('Enable AI Quiz Generator'),
                            'enable_game_generator'            => __('Enable AI Game Generator'),
                            'enable_teacher_approval'          => __('Require Teacher Approval'),
                            'enable_cbt_results_for_parents'   => __('CBT Results for Parents'),
                        ] as $flagKey => $flagLabel)
                            @php
                                $lock = $school->featureLock($flagKey);
                                $checked = old($flagKey, $school->featureEnabled($flagKey));
                            @endphp
                            @if ($lock !== null)
                                <input type="hidden" name="{{ $flagKey }}" value="{{ $lock ? '1' : '0' }}">
                                <div class="flex items-center justify-between gap-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/40 px-3 py-2">
                                    <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $flagLabel }}</span>
                                    <div class="flex items-center gap-1.5 text-xs font-medium {{ $lock ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}">
                                        <flux:icon.lock-closed class="h-3.5 w-3.5" />
                                        {{ $lock ? __('Enabled by platform') : __('Disabled by platform') }}
                                    </div>
                                </div>
                            @else
                                <flux:switch name="{{ $flagKey }}" :label="$flagLabel" :checked="$checked" value="1" />
                            @endif
                        @endforeach

                        <p class="text-xs font-semibold text-zinc-400 uppercase tracking-wide pt-2">{{ __('CBT & Assessments') }}</p>
                        @foreach ([
                            'enable_cbt_exam'        => __('Enable CBT Exams'),
                            'enable_assessment'      => __('Enable Assessments'),
                            'enable_cbt_assignment'  => __('Enable CBT Assignments'),
                        ] as $flagKey => $flagLabel)
                            @php
                                $lock = $school->featureLock($flagKey);
                                $checked = old($flagKey, $school->featureEnabled($flagKey));
                            @endphp
                            @if ($lock !== null)
                                <input type="hidden" name="{{ $flagKey }}" value="{{ $lock ? '1' : '0' }}">
                                <div class="flex items-center justify-between gap-3 rounded-lg bg-zinc-50 dark:bg-zinc-700/40 px-3 py-2">
                                    <span class="text-sm text-zinc-700 dark:text-zinc-300">{{ $flagLabel }}</span>
                                    <div class="flex items-center gap-1.5 text-xs font-medium {{ $lock ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}">
                                        <flux:icon.lock-closed class="h-3.5 w-3.5" />
                                        {{ $lock ? __('Enabled by platform') : __('Disabled by platform') }}
                                    </div>
                                </div>
                            @else
                                <flux:switch name="{{ $flagKey }}" :label="$flagLabel" :checked="$checked" value="1" />
                            @endif
                        @endforeach
                    </div>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <flux:input name="session_timeout_minutes" :label="__('Session Timeout (minutes)')" :value="old('session_timeout_minutes', $portalSettings['session_timeout_minutes'] ?? 30)" type="number" min="5" max="120" required />
                        <flux:input name="max_file_upload_mb" :label="__('Max File Upload (MB)')" :value="old('max_file_upload_mb', $portalSettings['max_file_upload_mb'] ?? 10)" type="number" min="1" max="50" required />
                    </div>

                    <flux:button variant="primary" type="submit">{{ __('Save Portal Settings') }}</flux:button>
                </form>
            </div>

            {{-- School Domain Info (read-only) --}}
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                <flux:heading size="sm" class="mb-4">{{ __('Domain') }}</flux:heading>
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('Custom Domain') }}</dt>
                        <dd class="font-medium">{{ $school->custom_domain ?? __('Not configured') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-zinc-500">{{ __('School Slug') }}</dt>
                        <dd class="font-medium">{{ $school->slug }}</dd>
                    </div>
                </dl>
                <p class="text-xs text-zinc-500 mt-3">{{ __('Contact platform administrator to change domain settings.') }}</p>
            </div>

            {{-- Full data export: the school's own backup copy --}}
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                <flux:heading size="sm" class="mb-2">{{ __('Download all school data') }}</flux:heading>
                <flux:text class="text-sm">
                    {{ __('A ZIP file with every record of your school as spreadsheets (CSV, opens in Excel): students, parents, teachers, classes, scores, report cards, CBT, notices, audit logs and more, plus links to your uploaded files. Passwords are never included.') }}
                </flux:text>
                <p class="mt-2 text-xs text-zinc-500">{{ __('Keep it somewhere safe: it contains personal information about your students and staff. Each download is recorded in the audit log.') }}</p>
                <flux:button class="mt-4" icon="arrow-down-tray" href="{{ route('admin.settings.export-data') }}">
                    {{ __('Download data (ZIP)') }}
                </flux:button>
            </div>
        </div>
    </div>
</x-layouts::app>
