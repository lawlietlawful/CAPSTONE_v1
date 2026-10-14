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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"></noscript>

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
        .brand-grid {
            background-image:
                linear-gradient(rgba(255,255,255,0.04) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.04) 1px, transparent 1px);
            background-size: 44px 44px;
            mask-image: radial-gradient(ellipse at 30% 40%, #000 20%, transparent 75%);
        }
    </style>
</head>
<body class="min-h-screen bg-white text-gray-900 antialiased selection:bg-blue-100 selection:text-blue-900">

<div class="min-h-screen flex">

    {{-- ── Brand panel (large screens) ─────────────────────────── --}}
    <aside class="hidden lg:flex lg:w-[46%] xl:w-1/2 relative overflow-hidden bg-gradient-to-br from-slate-950 via-slate-900 to-slate-800 text-white">
        <div class="absolute inset-0 brand-grid" aria-hidden="true"></div>
        <div class="absolute -top-32 -left-24 w-[28rem] h-[28rem] rounded-full bg-blue-600/25 blur-3xl" aria-hidden="true"></div>
        <div class="absolute -bottom-40 right-[-6rem] w-[26rem] h-[26rem] rounded-full bg-indigo-500/15 blur-3xl" aria-hidden="true"></div>

        <div class="relative z-10 flex flex-col justify-between w-full p-12 xl:p-16">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center shadow-lg shadow-blue-900/40 ring-1 ring-white/10">
                    <i class="ti ti-heart-handshake text-2xl"></i>
                </div>
                <div>
                    <p class="text-sm font-semibold leading-tight">Student Referral System</p>
                    <p class="text-xs text-white/50 mt-0.5">Misamis University · Guidance &amp; Counseling</p>
                </div>
            </div>

            <div class="max-w-md">
                <p class="inline-flex items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-blue-300/90 bg-blue-400/10 ring-1 ring-blue-300/20 rounded-full px-3 py-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span> Guidance Office Portal
                </p>
                <h2 class="mt-6 text-4xl xl:text-[2.75rem] font-bold leading-[1.15] tracking-tight">
                    Every referral,<br><span class="text-transparent bg-clip-text bg-gradient-to-r from-blue-300 to-indigo-300">followed through.</span>
                </h2>
                <p class="mt-5 text-[15px] leading-relaxed text-white/60">
                    One place to receive referrals, assess risk early and plan the right intervention, so no student slips through the cracks.
                </p>

                <ul class="mt-10 space-y-4">
                    @foreach ([
                        ['ti-send', 'Referrals from teachers, tracked to resolution'],
                        ['ti-chart-line', 'Risk assessment that flags students early'],
                        ['ti-calendar-event', 'Seminars and interventions in one calendar'],
                    ] as [$icon, $text])
                        <li class="flex items-center gap-3.5 text-sm text-white/75">
                            <span class="w-9 h-9 rounded-lg bg-white/[0.06] ring-1 ring-white/10 flex items-center justify-center flex-shrink-0">
                                <i class="ti {{ $icon }} text-lg text-blue-300"></i>
                            </span>
                            {{ $text }}
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="flex items-center justify-between text-xs text-white/40">
                <p class="flex items-center gap-2"><i class="ti ti-lock text-sm"></i> Authorized personnel only. Sign-ins are recorded.</p>
                <p>&copy; {{ date('Y') }} Misamis University</p>
            </div>
        </div>
    </aside>

    {{-- ── Form panel ──────────────────────────────────────────── --}}
    <main class="flex-1 flex flex-col bg-gray-50/60 lg:bg-white">
        {{-- Compact brand bar on small screens --}}
        <div class="lg:hidden flex items-center gap-3 px-6 py-5 bg-gradient-to-r from-slate-900 to-slate-800 text-white">
            <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center">
                <i class="ti ti-heart-handshake text-xl"></i>
            </div>
            <div>
                <p class="text-sm font-semibold leading-tight">Student Referral System</p>
                <p class="text-[11px] text-white/50">Misamis University · Guidance &amp; Counseling</p>
            </div>
        </div>

        <div class="flex-1 flex items-center justify-center px-5 sm:px-8 py-10">
            <div class="w-full max-w-[400px] bg-white lg:bg-transparent rounded-2xl lg:rounded-none p-7 sm:p-8 lg:p-0 shadow-xl shadow-gray-200/60 lg:shadow-none ring-1 ring-gray-100 lg:ring-0">
                {{ $slot }}
            </div>
        </div>

        <p class="lg:hidden text-center text-[11px] text-gray-400 pb-6 px-6 flex items-center justify-center gap-1.5">
            <i class="ti ti-lock"></i> Authorized personnel only. Sign-ins are recorded.
        </p>
    </main>
</div>

</body>
</html>
