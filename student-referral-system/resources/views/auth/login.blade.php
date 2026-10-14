@php
    // Set by LoginRequest when sign-ins are paused; counted down below.
    $lockedFor = max(0, (int) session('login_locked_until', 0) - now()->getTimestamp());
    $error = $errors->first('email') ?: $errors->first('password');
    $field = 'block w-full rounded-lg border bg-white px-3.5 py-2.5 text-sm text-gray-900 placeholder:text-gray-400
              focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-500 '
           . ($error && ! $lockedFor ? 'border-red-300' : 'border-gray-300');
@endphp

<x-guest-layout>
    <x-slot:title>Sign in</x-slot:title>

    <div x-data="loginForm({{ $lockedFor }})" x-init="start()">
        <h1 class="text-2xl font-semibold text-gray-900">Sign in</h1>
        <p class="mt-1 text-sm text-gray-500">Use your email, Employee ID or Student ID.</p>

        @if (session('status'))
            <p class="mt-5 rounded-lg bg-emerald-50 border border-emerald-200 px-3.5 py-2.5 text-sm text-emerald-800" role="status">
                {{ session('status') }}
            </p>
        @endif

        @if ($error)
            <div class="mt-5 rounded-lg border px-3.5 py-2.5 text-sm {{ $lockedFor ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-red-50 border-red-200 text-red-700' }}"
                 role="alert" aria-live="assertive" data-login-error>
                @if ($lockedFor)
                    <p x-show="locked">{{ Str::before($error, ' Please try again') }} Try again in <strong class="tabular-nums" x-text="clock">{{ gmdate('i:s', $lockedFor) }}</strong>.</p>
                    <p x-show="!locked" x-cloak>You can try signing in again now.</p>
                @else
                    <p>{{ $error }}</p>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4" @submit="submitting = true" @pageshow.window="submitting = false">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email or ID</label>
                <input id="email" name="email" type="text" value="{{ old('email') }}" required
                       autocomplete="username" autocapitalize="none" spellcheck="false" maxlength="255"
                       @if (! old('email')) autofocus @endif
                       class="{{ $field }}">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <div class="relative">
                    <input id="password" name="password" :type="reveal ? 'text' : 'password'" type="password" required
                           autocomplete="current-password" maxlength="255"
                           @if (old('email')) autofocus @endif
                           @keydown="checkCaps($event)" @keyup="checkCaps($event)" @blur="caps = false"
                           class="{{ $field }} pr-11">
                    <button type="button" @click="reveal = !reveal"
                            class="absolute right-1.5 top-1/2 -translate-y-1/2 w-8 h-8 rounded-md flex items-center justify-center text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                            :aria-label="reveal ? 'Hide password' : 'Show password'" aria-label="Show password" data-toggle-password>
                        <i class="ti text-lg" :class="reveal ? 'ti-eye-off' : 'ti-eye'"></i>
                    </button>
                </div>
                <p x-show="caps" x-cloak class="mt-1.5 text-xs text-amber-700" aria-live="polite" data-caps-warning>
                    Caps Lock is on
                </p>
            </div>

            <button type="submit" :disabled="submitting || locked"
                    class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700
                           focus:outline-none focus:ring-2 focus:ring-blue-500/40 focus:ring-offset-2
                           disabled:cursor-not-allowed disabled:bg-blue-400">
                <span x-text="submitting ? 'Signing in…' : (locked ? 'Try again in ' + clock : 'Sign in')">Sign in</span>
            </button>
        </form>

        <p class="mt-5 text-center text-sm">
            <a href="{{ route('password.request') }}" class="text-blue-600 hover:text-blue-700 hover:underline">Forgot your password?</a>
        </p>
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
