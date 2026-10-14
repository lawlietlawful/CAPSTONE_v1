<x-guest-layout>
    <x-slot:title>Forgot password</x-slot:title>

    <h1 class="text-2xl font-semibold text-gray-900">Forgot your password?</h1>
    <p class="mt-1 text-sm text-gray-500">Passwords are reset in person at the Guidance Office, not by email.</p>

    <div class="mt-6 divide-y divide-gray-200 border-y border-gray-200 text-sm">
        <div class="py-4" data-reset-staff>
            <p class="font-medium text-gray-900">Counselors and staff</p>
            <p class="mt-1 text-gray-600">Ask the Super Admin to reset your password. You'll get a temporary password and choose a new one when you sign in.</p>
        </div>
        <div class="py-4" data-reset-teacher-student>
            <p class="font-medium text-gray-900">Teachers and students</p>
            <p class="mt-1 text-gray-600">Ask the Guidance Office for a new activation code, then activate your account again in the app.</p>
        </div>
    </div>

    <p class="mt-4 text-xs text-gray-500">Bring your school ID. The office will never ask for your password.</p>

    <a href="{{ route('login') }}"
       class="mt-6 block w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50">
        Back to sign in
    </a>
</x-guest-layout>
