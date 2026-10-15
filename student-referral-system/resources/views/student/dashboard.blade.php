{{-- Students use the Student Portal app; on the web they only sign in to
     check in to a seminar from its QR code. This is where a web sign-in
     lands otherwise. --}}
<x-guest-layout>
    <x-slot:title>Student</x-slot:title>

    <h1 class="text-2xl font-semibold text-gray-900">Hi, {{ Str::before(auth()->user()->name, ' ') ?: auth()->user()->name }}</h1>
    <p class="mt-1 text-sm text-gray-500">You're signed in as a student.</p>

    <div class="mt-6 divide-y divide-gray-200 border-y border-gray-200 text-sm" data-student-home>
        <div class="py-4">
            <p class="font-medium text-gray-900">Use the Student Portal app</p>
            <p class="mt-1 text-gray-600">Your seminars, notifications and messages from Guidance are in the Student Portal app on your phone.</p>
        </div>
        <div class="py-4">
            <p class="font-medium text-gray-900">Checking in to a seminar?</p>
            <p class="mt-1 text-gray-600">Scan the QR code shown at the seminar. It brings you back here and checks you in.</p>
        </div>
    </div>

    <form method="POST" action="{{ route('logout') }}" class="mt-6">
        @csrf
        <button type="submit" class="block w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-center text-sm font-semibold text-gray-700 hover:bg-gray-50">
            Sign out
        </button>
    </form>
</x-guest-layout>
