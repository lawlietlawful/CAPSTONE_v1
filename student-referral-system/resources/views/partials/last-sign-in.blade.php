{{-- Shown once after a staff sign-in (AuthenticatedSessionController):
     when this account last signed in, and any wrong passwords tried on it
     since — so a counselor can notice use of their account that wasn't them. --}}
@if($lastSignIn = session('last_sign_in'))
    @php
        $at = $lastSignIn['at'] ? \Illuminate\Support\Carbon::parse($lastSignIn['at'])->timezone(config('app.timezone')) : null;
        $failed = (int) $lastSignIn['failed'];
    @endphp
    <div x-data="{ show: true }" x-show="show" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-4"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         @if(! $failed) x-init="setTimeout(() => show = false, 8000)" @endif
         class="fixed bottom-6 right-6 z-[100] max-w-sm w-full bg-white rounded-xl shadow-2xl shadow-gray-200/50 ring-1 print:hidden
                {{ $failed ? 'ring-amber-200' : 'ring-black/5' }}"
         role="status" data-last-sign-in>
        <div class="flex items-start gap-4 px-5 py-4">
            <div class="flex items-center justify-center w-10 h-10 rounded-full flex-shrink-0
                        {{ $failed ? 'bg-amber-50 text-amber-600' : 'bg-blue-50 text-blue-600' }}">
                <i class="ti {{ $failed ? 'ti-shield-exclamation' : 'ti-shield-check' }} text-xl"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="font-semibold text-gray-900 text-sm">
                    {{ $failed ? 'Check your account activity' : 'Signed in securely' }}
                </p>
                <p class="text-gray-500 text-xs mt-1 leading-relaxed">
                    @if($at)
                        Last sign-in: <span class="text-gray-700 font-medium">{{ $at->format('M j, Y \a\t g:i A') }}</span>
                        @if($lastSignIn['ip']) from {{ $lastSignIn['ip'] }}@endif
                    @else
                        This is the first recorded sign-in to your account.
                    @endif
                </p>
                @if($failed)
                    <p class="text-amber-700 text-xs mt-2 leading-relaxed" data-failed-attempts="{{ $failed }}">
                        <strong>{{ $failed }} failed sign-in {{ Str::plural('attempt', $failed) }}</strong>
                        on your account {{ $at ? 'since then' : 'before this' }}. If that wasn't you,
                        <a href="{{ route('account.password.edit') }}" class="underline font-semibold hover:text-amber-900">change your password</a>.
                    </p>
                @endif
            </div>
            <button type="button" @click="show = false" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0" aria-label="Dismiss">
                <i class="ti ti-x text-lg"></i>
            </button>
        </div>
    </div>
@endif
