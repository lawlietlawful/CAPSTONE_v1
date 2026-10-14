@php
    // Set by LoginRequest when sign-ins are paused; counted down below.
    $lockedFor = max(0, (int) session('login_locked_until', 0) - now()->getTimestamp());
    $error = $errors->first('email') ?: $errors->first('password');
@endphp

<x-guest-layout>
    <x-slot:title>Sign in</x-slot:title>

    <div x-data="loginForm({{ $lockedFor }})" x-init="start()">
        <div>
            <h1 class="text-2xl sm:text-[1.7rem] font-bold tracking-tight text-gray-900">Welcome back</h1>
            <p class="mt-2 text-sm text-gray-500">Sign in with your email, Employee ID or Student ID.</p>
        </div>

        {{-- Status (e.g. after a password change) --}}
        @if (session('status'))
            <div class="mt-6 flex items-start gap-3 rounded-xl bg-emerald-50 ring-1 ring-emerald-100 px-4 py-3 text-sm text-emerald-800" role="status">
                <i class="ti ti-circle-check text-lg mt-px"></i>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        {{-- Sign-in error / pause --}}
        @if ($error)
            <div class="mt-6 flex items-start gap-3 rounded-xl px-4 py-3 text-sm ring-1 {{ $lockedFor ? 'bg-amber-50 ring-amber-100 text-amber-800' : 'bg-red-50 ring-red-100 text-red-700' }}"
                 role="alert" aria-live="assertive" data-login-error>
                <i class="ti {{ $lockedFor ? 'ti-clock-pause' : 'ti-alert-circle' }} text-lg mt-px flex-shrink-0"></i>
                <div>
                    @if ($lockedFor)
                        <p x-show="locked">{{ Str::before($error, ' Please try again') }} Try again in <strong class="tabular-nums" x-text="clock">{{ gmdate('i:s', $lockedFor) }}</strong>.</p>
                        <p x-show="!locked" x-cloak>You can try signing in again now.</p>
                    @else
                        <p>{{ $error }}</p>
                    @endif
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="mt-7 space-y-5" @submit="submitting = true" @pageshow.window="submitting = false">
            @csrf

            {{-- Email or ID --}}
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email or ID</label>
                <div class="relative">
                    <i class="ti ti-user absolute left-3.5 top-1/2 -translate-y-1/2 text-lg text-gray-400 pointer-events-none"></i>
                    <input id="email" name="email" type="text" value="{{ old('email') }}" required
                           autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="255"
                           @if (! old('email')) autofocus @endif
                           placeholder="you@school.edu.ph or 2026-0001"
                           class="block w-full rounded-xl border bg-white pl-11 pr-4 py-3 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm transition
                                  focus:outline-none focus:ring-4 focus:ring-blue-500/15 focus:border-blue-500
                                  {{ $error && ! $lockedFor ? 'border-red-300' : 'border-gray-200' }}">
                </div>
            </div>

            {{-- Password --}}
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                    <a href="{{ route('password.request') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700 rounded focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                        Forgot password?
                    </a>
                </div>
                <div class="relative">
                    <i class="ti ti-lock absolute left-3.5 top-1/2 -translate-y-1/2 text-lg text-gray-400 pointer-events-none"></i>
                    <input id="password" name="password" :type="reveal ? 'text' : 'password'" type="password" required
                           autocomplete="current-password" maxlength="255"
                           @if (old('email')) autofocus @endif
                           placeholder="Enter your password"
                           @keydown="checkCaps($event)" @keyup="checkCaps($event)" @blur="caps = false"
                           class="block w-full rounded-xl border bg-white pl-11 pr-12 py-3 text-sm text-gray-900 placeholder:text-gray-400 shadow-sm transition
                                  focus:outline-none focus:ring-4 focus:ring-blue-500/15 focus:border-blue-500
                                  {{ $error && ! $lockedFor ? 'border-red-300' : 'border-gray-200' }}">
                    <button type="button" @click="reveal = !reveal"
                            class="absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-lg flex items-center justify-center text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition focus:outline-none focus:ring-2 focus:ring-blue-500/40"
                            :aria-label="reveal ? 'Hide password' : 'Show password'" aria-label="Show password" data-toggle-password>
                        <i class="ti text-lg" :class="reveal ? 'ti-eye-off' : 'ti-eye'"></i>
                    </button>
                </div>
                <p x-show="caps" x-cloak x-transition.opacity class="mt-2 flex items-center gap-1.5 text-xs font-medium text-amber-700" aria-live="polite" data-caps-warning>
                    <i class="ti ti-arrow-big-up-line text-sm"></i> Caps Lock is on
                </p>
            </div>

            <button type="submit" :disabled="submitting || locked"
                    class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-600/20 transition
                           hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-500/30
                           disabled:cursor-not-allowed disabled:bg-blue-400 disabled:shadow-none">
                <template x-if="submitting">
                    <svg class="w-4 h-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"></circle>
                        <path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                    </svg>
                </template>
                <i x-show="!submitting && !locked" class="ti ti-login-2 text-base"></i>
                <i x-show="locked && !submitting" x-cloak class="ti ti-clock text-base"></i>
                <span x-text="submitting ? 'Signing in…' : (locked ? 'Try again in ' + clock : 'Sign in')">Sign in</span>
            </button>
        </form>

        <div class="mt-8 flex items-start gap-3 rounded-xl bg-slate-50 ring-1 ring-slate-100 px-4 py-3.5 text-xs leading-relaxed text-slate-500">
            <i class="ti ti-shield-lock text-base text-slate-400 mt-px"></i>
            <p>For your security, sign out when you're done, especially on a shared computer. Teachers and students can also use the mobile app.</p>
        </div>
    </div>

    <script>
        function loginForm(lockedFor) {
            return {
                reveal: false,
                caps: false,
                submitting: false,
                remaining: lockedFor,
                get locked() { return this.remaining > 0; },
                get clock() {
                    const m = Math.floor(this.remaining / 60), s = this.remaining % 60;
                    return m + ':' + String(s).padStart(2, '0');
                },
                start() {
                    if (!this.locked) return;
                    const timer = setInterval(() => {
                        if (--this.remaining <= 0) { this.remaining = 0; clearInterval(timer); }
                    }, 1000);
                },
                checkCaps(e) {
                    if (e.getModifierState) this.caps = e.getModifierState('CapsLock');
                },
            };
        }
    </script>
</x-guest-layout>
