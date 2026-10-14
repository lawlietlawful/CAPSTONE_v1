<x-guest-layout>
    <x-slot:title>Forgot password</x-slot:title>

    <div>
        <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center ring-1 ring-blue-100">
            <i class="ti ti-key text-2xl"></i>
        </div>
        <h1 class="mt-5 text-2xl sm:text-[1.7rem] font-bold tracking-tight text-gray-900">Forgot your password?</h1>
        <p class="mt-2 text-sm leading-relaxed text-gray-500">
            Passwords aren't reset by email. The Guidance Office resets them in person, so only you can get back into your account.
        </p>

        <div class="mt-7 space-y-3">
            <div class="flex gap-4 rounded-xl ring-1 ring-gray-200 p-4" data-reset-staff>
                <span class="w-10 h-10 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center flex-shrink-0">
                    <i class="ti ti-user-shield text-xl"></i>
                </span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Counselors and staff</p>
                    <p class="mt-1 text-xs leading-relaxed text-gray-500">
                        Ask the <strong class="text-gray-700">Super Admin</strong> to reset your password. You'll get a temporary password and choose a new one the first time you sign in with it.
                    </p>
                </div>
            </div>

            <div class="flex gap-4 rounded-xl ring-1 ring-gray-200 p-4" data-reset-teacher-student>
                <span class="w-10 h-10 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center flex-shrink-0">
                    <i class="ti ti-school text-xl"></i>
                </span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Teachers and students</p>
                    <p class="mt-1 text-xs leading-relaxed text-gray-500">
                        Visit the <strong class="text-gray-700">Guidance Office</strong> for a new activation code, then activate your account again in the app and set a new password.
                    </p>
                </div>
            </div>
        </div>

        <p class="mt-5 flex items-start gap-2 text-xs leading-relaxed text-gray-400">
            <i class="ti ti-info-circle text-sm mt-px"></i>
            Bring your school ID. The office will never ask for your password.
        </p>

        <a href="{{ route('login') }}"
           class="mt-7 w-full inline-flex items-center justify-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-blue-500/15">
            <i class="ti ti-arrow-left text-base"></i> Back to sign in
        </a>
    </div>
</x-guest-layout>
