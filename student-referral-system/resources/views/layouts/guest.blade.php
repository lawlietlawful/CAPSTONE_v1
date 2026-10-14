<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>{{ $title ?? 'Sign in' }} — Student Referral System</title>

    {{-- Same assets as the staff portals, so sign-in looks like the app it opens. --}}
    <script src="{{ asset('vendor/tailwind.play.js') }}"></script>
    <script defer src="{{ asset('vendor/alpine.min.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler-icons.min.css') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased">

<div class="min-h-screen flex">

    {{-- Side panel (large screens): same navy as the staff sidebar --}}
    <aside class="hidden lg:flex lg:w-[42%] bg-slate-900 text-white">
        <div class="flex flex-col justify-between w-full p-12">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg bg-blue-600 flex items-center justify-center">
                    <i class="ti ti-school text-xl"></i>
                </div>
                <p class="text-sm font-semibold">Misamis University</p>
            </div>

            <div>
                <h2 class="text-3xl font-semibold">Student Referral System</h2>
                <p class="mt-2 text-white/60">Guidance and Counseling Office</p>
            </div>

            <p class="text-xs text-white/40">&copy; {{ date('Y') }} Misamis University</p>
        </div>
    </aside>

    {{-- Form --}}
    <main class="flex-1 flex flex-col bg-gray-50 lg:bg-white">
        <div class="lg:hidden flex items-center gap-3 px-6 py-4 bg-slate-900 text-white">
            <div class="w-8 h-8 rounded-md bg-blue-600 flex items-center justify-center">
                <i class="ti ti-school text-lg"></i>
            </div>
            <div>
                <p class="text-sm font-semibold leading-tight">Student Referral System</p>
                <p class="text-xs text-white/60">Misamis University</p>
            </div>
        </div>

        <div class="flex-1 flex items-center justify-center px-5 py-10">
            <div class="w-full max-w-sm bg-white lg:bg-transparent rounded-xl lg:rounded-none p-6 lg:p-0 border border-gray-200 lg:border-0">
                {{ $slot }}
            </div>
        </div>
    </main>
</div>

</body>
</html>
