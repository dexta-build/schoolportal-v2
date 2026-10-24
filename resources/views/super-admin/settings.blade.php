<x-layouts::app :title="__('Platform Settings')">
    <div class="space-y-6">
        {{-- ── Header ─────────────────────────────────────────────── --}}
        <div class="dash-welcome dash-welcome-super" role="banner">
            <div class="relative z-10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-xl sm:text-2xl font-bold text-white">{{ __('Platform Settings') }}</h1>
                    <p class="mt-1 text-sm text-white/70">{{ __('Global defaults and platform-wide configuration') }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/15 text-white/80 text-xs backdrop-blur-sm border border-white/10">
                        <flux:icon.shield-check class="w-3.5 h-3.5" />
                        {{ __('Super Admin Only') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- ── Flash Messages ──────────────────────────────────────── --}}
        @if (session('success'))
            <flux:callout variant="success" icon="check-circle">{{ session('success') }}</flux:callout>
        @endif
        @if (session('error'))
            <flux:callout variant="danger" icon="exclamation-triangle">{{ session('error') }}</flux:callout>
        @endif

        @if ($errors->any())
            <flux:callout variant="danger" icon="exclamation-triangle">
                <ul class="list-disc list-inside space-y-1 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </flux:callout>
        @endif

        <div class="max-w-2xl space-y-6">

            {{-- ── Platform Identity ──────────────────────────────── --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
                <div class="px-6 py-4 border-b border-zinc-100 dark:border-zinc-700 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
                        <flux:icon.globe-alt class="w-4 h-4 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Platform Identity') }}</h2>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Displayed in the browser tab and email footers') }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('super-admin.settings.update') }}" class="p-6 space-y-4">
                    @csrf
                    @method('PUT')
                    {{-- hidden fields to carry other section values --}}
                    <input type="hidden" name="default_free_ai_credits" value="{{ old('default_free_ai_credits', $settings['default_free_ai_credits']) }}">
                    <input type="hidden" name="maintenance_mode" value="0">
                    @if ($settings['maintenance_mode'])
                        <input type="hidden" name="maintenance_mode" value="1">
                    @endif
                    <input type="hidden" name="maintenance_message" value="{{ old('maintenance_message', $settings['maintenance_message']) }}">
                    <input type="hidden" name="allowed_file_types" value="{{ old('allowed_file_types', $settings['allowed_file_types']) }}">
                    <input type="hidden" name="max_upload_size_mb" value="{{ old('max_upload_size_mb', $settings['max_upload_size_mb']) }}">
                    <input type="hidden" name="credit_price_per_5" value="{{ old('credit_price_per_5', $settings['credit_price_per_5']) }}">

                    <flux:input
                        name="platform_name"
                        :label="__('Platform Name')"
                        :value="old('platform_name', $settings['platform_name'])"
                        required
                        maxlength="100"
                    />

                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </form>
            </div>

            {{-- ── New School Defaults ─────────────────────────────── --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
                <div class="px-6 py-4 border-b border-zinc-100 dark:border-zinc-700 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                        <flux:icon.sparkles class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('New School Defaults') }}</h2>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Applied when a new school is created via the setup wizard') }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('super-admin.settings.update') }}" class="p-6 space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="platform_name" value="{{ old('platform_name', $settings['platform_name']) }}">
                    <input type="hidden" name="maintenance_mode" value="{{ $settings['maintenance_mode'] ? '1' : '0' }}">
                    <input type="hidden" name="maintenance_message" value="{{ old('maintenance_message', $settings['maintenance_message']) }}">
                    <input type="hidden" name="allowed_file_types" value="{{ old('allowed_file_types', $settings['allowed_file_types']) }}">
                    <input type="hidden" name="max_upload_size_mb" value="{{ old('max_upload_size_mb', $settings['max_upload_size_mb']) }}">
                    <input type="hidden" name="credit_price_per_5" value="{{ old('credit_price_per_5', $settings['credit_price_per_5']) }}">

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <flux:input
                                name="default_free_ai_credits"
                                :label="__('Default Free AI Credits')"
                                type="number"
                                min="0"
                                max="100"
                                :value="old('default_free_ai_credits', $settings['default_free_ai_credits'])"
                                required
                            />
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Credits given to each new school on signup') }}</p>
                        </div>
                        <div>
                            <flux:input
                                name="credit_price_per_5"
                                :label="__('Credit Price per 5 (₦)')"
                                type="number"
                                min="100"
                                max="50000"
                                :value="old('credit_price_per_5', $settings['credit_price_per_5'])"
                                required
                            />
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('NGN charged per 5 AI credits purchased') }}</p>
                        </div>
                    </div>

                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </form>
            </div>

            {{-- ── File Upload Limits ──────────────────────────────── --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
                <div class="px-6 py-4 border-b border-zinc-100 dark:border-zinc-700 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center">
                        <flux:icon.paper-clip class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('File Upload Limits') }}</h2>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Default limits applied across the platform') }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('super-admin.settings.update') }}" class="p-6 space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="platform_name" value="{{ old('platform_name', $settings['platform_name']) }}">
                    <input type="hidden" name="default_free_ai_credits" value="{{ old('default_free_ai_credits', $settings['default_free_ai_credits']) }}">
                    <input type="hidden" name="maintenance_mode" value="{{ $settings['maintenance_mode'] ? '1' : '0' }}">
                    <input type="hidden" name="maintenance_message" value="{{ old('maintenance_message', $settings['maintenance_message']) }}">
                    <input type="hidden" name="credit_price_per_5" value="{{ old('credit_price_per_5', $settings['credit_price_per_5']) }}">

                    <div>
                        <flux:input
                            name="allowed_file_types"
                            :label="__('Allowed File Types')"
                            :value="old('allowed_file_types', $settings['allowed_file_types'])"
                            required
                        />
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Comma-separated extensions, e.g. pdf,doc,docx,png,jpg') }}</p>
                    </div>

                    <div>
                        <flux:input
                            name="max_upload_size_mb"
                            :label="__('Max Upload Size (MB)')"
                            type="number"
                            min="1"
                            max="100"
                            :value="old('max_upload_size_mb', $settings['max_upload_size_mb'])"
                            required
                        />
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Maximum file size per upload across the platform') }}</p>
                    </div>

                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </form>
            </div>

            {{-- ── Maintenance Mode ─────────────────────────────────── --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800"
                 x-data="{ on: {{ $settings['maintenance_mode'] ? 'true' : 'false' }} }">
                <div class="px-6 py-4 border-b border-zinc-100 dark:border-zinc-700 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-lg bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
                            <flux:icon.wrench-screwdriver class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                        </div>
                        <div>
                            <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Maintenance Mode') }}</h2>
                            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('When enabled, all non-super-admin users will see a maintenance page') }}</p>
                        </div>
                    </div>
                    <div x-show="on" class="px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                        {{ __('Active') }}
                    </div>
                </div>
                <form method="POST" action="{{ route('super-admin.settings.update') }}" class="p-6 space-y-4">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="platform_name" value="{{ old('platform_name', $settings['platform_name']) }}">
                    <input type="hidden" name="default_free_ai_credits" value="{{ old('default_free_ai_credits', $settings['default_free_ai_credits']) }}">
                    <input type="hidden" name="allowed_file_types" value="{{ old('allowed_file_types', $settings['allowed_file_types']) }}">
                    <input type="hidden" name="max_upload_size_mb" value="{{ old('max_upload_size_mb', $settings['max_upload_size_mb']) }}">
                    <input type="hidden" name="credit_price_per_5" value="{{ old('credit_price_per_5', $settings['credit_price_per_5']) }}">

                    <div class="flex items-center gap-3">
                        <input
                            type="hidden"
                            name="maintenance_mode"
                            value="0"
                        >
                        <input
                            id="maintenance_mode"
                            type="checkbox"
                            name="maintenance_mode"
                            value="1"
                            x-model="on"
                            class="w-4 h-4 text-amber-600 border-zinc-300 rounded focus:ring-amber-500"
                            @checked($settings['maintenance_mode'])
                        >
                        <label for="maintenance_mode" class="text-sm font-medium text-zinc-900 dark:text-white">
                            {{ __('Enable maintenance mode') }}
                        </label>
                    </div>

                    <div x-show="on" x-cloak>
                        <flux:textarea
                            name="maintenance_message"
                            :label="__('Maintenance Message')"
                            rows="3"
                        >{{ old('maintenance_message', $settings['maintenance_message']) }}</flux:textarea>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Shown to users on the maintenance page. Leave blank for the default message.') }}</p>
                    </div>

                    <div x-show="on" x-cloak>
                        <div class="flex items-start gap-2 p-3 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800">
                            <flux:icon.exclamation-triangle class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                            <p class="text-xs text-amber-700 dark:text-amber-300">
                                {{ __('Maintenance mode will block access for all students, teachers, parents, and school admins. Only you (super admin) will have access.') }}
                            </p>
                        </div>
                    </div>

                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </form>
            </div>

            {{-- ── Feature Flag Defaults ─────────────────────────────── --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
                <div class="px-6 py-4 border-b border-zinc-100 dark:border-zinc-700 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center">
                        <flux:icon.cpu-chip class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Feature Flag Defaults') }}</h2>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Platform-wide defaults for new and unoveridden schools. Schools can override unless locked.') }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('super-admin.settings.update') }}" class="p-6 space-y-4">
                    @csrf
                    @method('PUT')
                    {{-- Pass through all other settings unchanged --}}
                    {{-- Marks this as the form that carries the feature-flag checkboxes --}}
                    <input type="hidden" name="feature_flags_form" value="1">
                    <input type="hidden" name="platform_name" value="{{ old('platform_name', $settings['platform_name']) }}">
                    <input type="hidden" name="default_free_ai_credits" value="{{ old('default_free_ai_credits', $settings['default_free_ai_credits']) }}">
                    <input type="hidden" name="allowed_file_types" value="{{ old('allowed_file_types', $settings['allowed_file_types']) }}">
                    <input type="hidden" name="max_upload_size_mb" value="{{ old('max_upload_size_mb', $settings['max_upload_size_mb']) }}">
                    <input type="hidden" name="credit_price_per_5" value="{{ old('credit_price_per_5', $settings['credit_price_per_5']) }}">
                    <input type="hidden" name="maintenance_mode" value="{{ $settings['maintenance_mode'] ? '1' : '0' }}">
                    <input type="hidden" name="maintenance_message" value="{{ old('maintenance_message', $settings['maintenance_message']) }}">

                    <div class="divide-y divide-zinc-100 dark:divide-zinc-700 -mx-6 px-6">
                        @foreach (\App\Models\PlatformSetting::FEATURE_FLAGS as $flagKey => $flagLabel)
                            @php
                                $settingKey = "feature_default_{$flagKey}";
                                $isOn = (bool) old($settingKey, $settings[$settingKey] ?? true);
                            @endphp
                            <div class="py-3 flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __($flagLabel) }}</p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Default state for :flag', ['flag' => $flagKey]) }}</p>
                                </div>
                                <div x-data="{ on: {{ $isOn ? 'true' : 'false' }} }">
                                    {{-- Hidden 0 so unchecked checkbox = false --}}
                                    <input type="hidden" name="{{ $settingKey }}" value="0">
                                    <input
                                        id="{{ $settingKey }}"
                                        type="checkbox"
                                        name="{{ $settingKey }}"
                                        value="1"
                                        x-model="on"
                                        class="sr-only"
                                        @checked($isOn)
                                    >
                                    <button
                                        type="button"
                                        role="switch"
                                        :aria-checked="on.toString()"
                                        @click="on = !on; $el.previousElementSibling.previousElementSibling.checked = on"
                                        :class="on
                                            ? 'bg-indigo-600'
                                            : 'bg-zinc-200 dark:bg-zinc-700'"
                                        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    >
                                        <span
                                            :class="on ? 'translate-x-5' : 'translate-x-0'"
                                            class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200"
                                        ></span>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </form>
            </div>

            {{-- ── Help Videos ─────────────────────────────────────── --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800">
                <div class="px-6 py-4 border-b border-zinc-100 dark:border-zinc-700 flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-rose-100 dark:bg-rose-900/30 flex items-center justify-center">
                        <flux:icon.play-circle class="w-4 h-4 text-rose-600 dark:text-rose-400" />
                    </div>
                    <div>
                        <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Help Videos') }}</h2>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Shown as "Watch video" in the "How this page works" guides, for every school. Leave empty to show the written steps only.') }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('super-admin.settings.help-videos') }}" class="p-6 space-y-4">
                    @csrf
                    @method('PUT')

                    @foreach (\App\Support\PageHelp::VIDEO_SETTINGS as $topic => $settingKey)
                        <flux:input
                            :name="$settingKey"
                            type="url"
                            :label="__(\App\Support\PageHelp::TOPIC_NAMES[$topic])"
                            :value="old($settingKey, $settings[$settingKey] ?? '')"
                            placeholder="https://www.youtube.com/watch?v=..."
                        />
                    @endforeach

                    <flux:text class="text-xs">{{ __('YouTube or Vimeo links. On YouTube, upload as "Unlisted" so only people with the link can find it.') }}</flux:text>

                    <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
                </form>
            </div>

        </div>
    </div>
</x-layouts::app>
