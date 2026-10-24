<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800 overflow-x-hidden">
        {{-- Skip to main content link (accessibility) --}}
        <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:text-sm focus:font-medium focus:shadow-lg focus:ring-2 focus:ring-indigo-500 dark:focus:bg-zinc-800 dark:focus:text-white">
            {{ __('Skip to main content') }}
        </a>

        <flux:sidebar sticky collapsible class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header class="!gap-1">
                <div class="min-w-0 flex-1">
                    <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                </div>

                <div class="flex items-center shrink-0 in-data-flux-sidebar-collapsed-desktop:hidden">
                    <livewire:notification-bell />
                </div>

                <flux:sidebar.collapse class="!w-auto shrink-0" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @if (auth()->user()?->role === 'school_admin')
                    <livewire:global-search />
                @endif

                @include('partials.sidebar-nav')
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden !gap-2">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <livewire:notification-bell />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :src="auth()->user()->avatarThumbUrl()"
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email ?? auth()->user()->username }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @if (session('impersonating_original_id'))
            {{-- A change was refused because viewing a school is read-only (form posts flash it;
                 Livewire actions raise the impersonation-read-only event from resources/js/app.js) --}}
            <div
                x-data="{ show: @js((bool) session('impersonation_blocked')), timer: null }"
                x-init="if (show) timer = setTimeout(() => show = false, 6000)"
                x-on:impersonation-read-only.window="show = true; clearTimeout(timer); timer = setTimeout(() => show = false, 6000)"
                x-show="show" x-transition x-cloak
                class="fixed bottom-12 inset-x-0 z-50 mx-auto w-fit max-w-[calc(100%-2rem)] rounded-lg bg-zinc-900 px-4 py-2.5 text-sm text-white shadow-lg dark:bg-white dark:text-zinc-900"
                role="alert"
            >
                {{ __('You are viewing this school in read-only mode. Changes can only be made by the school\'s own staff.') }}
            </div>

            {{-- Impersonation banner — fixed at bottom so it doesn't push content --}}
            <div class="fixed bottom-0 inset-x-0 z-50 flex items-center justify-between gap-4 bg-amber-500 px-4 py-2.5 text-sm font-medium text-white shadow-lg dark:bg-amber-600" role="alert" aria-live="polite">
                <div class="flex min-w-0 items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.964-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <span class="truncate">
                        {{ __('Viewing as') }}
                        <strong>{{ auth()->user()->name }}</strong>
                        @if (app()->bound('current.school'))
                            &mdash; {{ app('current.school')->name }}
                        @endif
                        &middot; <strong>{{ __('Read-only') }}</strong>
                        <span class="hidden sm:inline">&mdash; {{ __('changes can only be made by the school\'s own staff') }}</span>
                    </span>
                </div>
                <form method="POST" action="{{ route('impersonate.stop') }}" class="shrink-0">
                    @csrf
                    <button
                        type="submit"
                        class="rounded border border-white/50 px-3 py-1 text-xs font-semibold transition hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-white/70 dark:hover:bg-amber-700"
                    >
                        {{ __('Stop viewing') }}
                    </button>
                </form>
            </div>
        @endif

        @persist('toast')
            <flux:toast.group>
                <flux:toast duration="8000" />
            </flux:toast.group>
        @endpersist

        <x-page-expired-dialog />
        <x-app-confirm-dialog />
        <x-undo-toast />

        {{-- ── Offline Banner (student only) ─────────────────────────────────── --}}
        @auth
            @if (auth()->user()?->role === 'student')
                <div
                    x-data="{
                        online: navigator.onLine,
                        showBanner: !navigator.onLine,
                        justCameOnline: false,
                        init() {
                            window.addEventListener('offline', () => {
                                this.online = false;
                                this.showBanner = true;
                                this.justCameOnline = false;
                            });
                            window.addEventListener('online', () => {
                                this.online = true;
                                this.justCameOnline = true;
                                setTimeout(() => {
                                    this.showBanner = false;
                                    this.justCameOnline = false;
                                }, 3000);
                            });
                        }
                    }"
                    x-show="showBanner"
                    x-cloak
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="translate-y-full opacity-0"
                    x-transition:enter-end="translate-y-0 opacity-100"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="translate-y-0 opacity-100"
                    x-transition:leave-end="translate-y-full opacity-0"
                    class="fixed bottom-0 inset-x-0 z-[60] flex items-center justify-between gap-3 px-4 py-3 text-sm font-medium text-white shadow-lg"
                    :class="online ? 'bg-emerald-600' : 'bg-zinc-800'"
                    role="status"
                    aria-live="polite"
                >
                    <div class="flex items-center gap-2.5">
                        <template x-if="!online">
                            {{-- No-wifi icon --}}
                            <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18M10.584 10.587a2 2 0 0 0 2.828 2.83m5.145 5.145A9.955 9.955 0 0 1 12 22C6.477 22 2 17.523 2 12c0-2.106.654-4.062 1.77-5.672m3.144-2.65A9.956 9.956 0 0 1 12 2c5.523 0 10 4.477 10 10 0 2.107-.655 4.063-1.77 5.673"/>
                            </svg>
                        </template>
                        <template x-if="online">
                            {{-- Check-circle icon --}}
                            <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                            </svg>
                        </template>
                        <span x-show="!online">{{ __("You're offline — showing cached content.") }}</span>
                        <span x-show="online">{{ __('Back online — content updated.') }}</span>
                    </div>
                    <button
                        type="button"
                        @click="showBanner = false"
                        class="shrink-0 rounded p-1 hover:bg-white/20 transition-colors"
                        aria-label="{{ __('Dismiss') }}"
                    >
                        <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            @endif
        @endauth

        @fluxScripts
        @stack('scripts')

        {{-- ── Back to Top ──────────────────────────────────────────────────── --}}
        <div
            x-data="{
                show: false,
                init() {
                    window.addEventListener('scroll', () => {
                        this.show = window.scrollY > 400;
                    }, { passive: true });
                }
            }"
            x-show="show"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-75"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-75"
            class="fixed bottom-6 right-6 z-[60] sm:bottom-8 sm:right-8"
        >
            <button
                type="button"
                @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
                class="flex items-center justify-center w-10 h-10 rounded-full bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 shadow-md hover:bg-zinc-50 dark:hover:bg-zinc-700 hover:text-zinc-900 dark:hover:text-white transition-colors focus:outline-none focus:ring-2 focus:ring-zinc-400 focus:ring-offset-2 dark:focus:ring-offset-zinc-900"
                aria-label="{{ __('Back to top') }}"
            >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5 12 3m0 0 7.5 7.5M12 3v18" />
                </svg>
            </button>
        </div>

        {{-- ── Session Expiry Warning ──────────────────────────────────────── --}}
        @auth
        @php
            $school = app()->bound('current.school') ? app('current.school') : null;
            $sessionMinutes = (int) ($school?->setting('portal.session_timeout_minutes') ?? config('session.lifetime', 30));
            $sessionSeconds = $sessionMinutes * 60;
            $warnBeforeSeconds = min(300, (int) ($sessionSeconds * 0.15)); // warn at 15% remaining (max 5 min)
        @endphp
        <div
            x-data="{
                show: false,
                secondsLeft: {{ $sessionSeconds }},
                totalSeconds: {{ $sessionSeconds }},
                warnAt: {{ $warnBeforeSeconds }},
                intervalId: null,
                pingUrl: '{{ route('session.ping') }}',
                init() {
                    this.intervalId = setInterval(() => {
                        this.secondsLeft--;
                        if (this.secondsLeft <= this.warnAt && this.secondsLeft > 0) {
                            this.show = true;
                        }
                        if (this.secondsLeft <= 0) {
                            clearInterval(this.intervalId);
                            window.location.href = '{{ route('login') }}?expired=1';
                        }
                    }, 1000);
                },
                async keepAlive() {
                    try {
                        const resp = await fetch(this.pingUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
                                'Accept': 'application/json',
                            },
                        });
                        if (resp.ok) {
                            this.secondsLeft = this.totalSeconds;
                            this.show = false;
                        }
                    } catch {
                        // Network error — do nothing, timer continues
                    }
                },
                get mins() { return String(Math.floor(Math.max(0, this.secondsLeft) / 60)).padStart(2, '0'); },
                get secs() { return String(Math.max(0, this.secondsLeft) % 60).padStart(2, '0'); },
            }"
            x-show="show"
            x-cloak
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="translate-y-full opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="translate-y-0 opacity-100"
            x-transition:leave-end="translate-y-full opacity-0"
            class="fixed bottom-0 inset-x-0 z-[70] flex items-center justify-between gap-3 bg-orange-600 px-4 py-3 text-sm font-medium text-white shadow-lg"
            role="alert"
            aria-live="assertive"
        >
            <div class="flex items-center gap-2.5">
                <svg class="size-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
                <span>{{ __('Your session expires in') }} <strong x-text="`${mins}:${secs}`"></strong></span>
            </div>
            <button
                type="button"
                @click="keepAlive()"
                class="shrink-0 rounded border border-white/40 px-3 py-1 text-xs font-semibold transition hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-white/70"
            >
                {{ __('Stay logged in') }}
            </button>
        </div>
        @endauth
    </body>
</html>
