<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Counselor') — Student Referral System</title>

    {{-- Tailwind CSS via CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Chart.js --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>

    {{-- Alpine.js --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    {{-- Tabler Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

    {{-- Google Fonts: Plus Jakarta Sans --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
        .sidebar { width: 220px; min-width: 220px; transition: all 0.3s ease; }
        .main-content { flex: 1; min-width: 0; }
        .nav-item { transition: all 0.2s ease; }
        .nav-item:hover { background: rgba(255,255,255,0.05); transform: translateX(4px); color: #ffffff; }
        .nav-item.active { background: rgba(59, 130, 246, 0.1); border-left: 3px solid #3b82f6; color: #60a5fa !important; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 6px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.2); }
        
        /* Premium Shadows */
        .shadow-premium { box-shadow: 0 4px 20px -4px rgba(0,0,0,0.05), 0 2px 8px -2px rgba(0,0,0,0.02); }
        .shadow-hover { box-shadow: 0 10px 30px -5px rgba(0,0,0,0.08), 0 4px 12px -3px rgba(0,0,0,0.04); }
    </style>

    @stack('styles')
</head>
<body class="bg-gray-50/50 text-gray-900 selection:bg-blue-100 selection:text-blue-900">

<div class="flex min-h-screen">

    {{-- ── Sidebar ─────────────────────────────────────────── --}}
    <aside class="sidebar bg-gradient-to-b from-slate-900 to-slate-800 border-r border-slate-700/50 shadow-xl flex flex-col fixed top-0 left-0 h-screen z-30">

        {{-- Logo --}}
        <div class="px-5 py-5 border-b border-white/10">
            <p class="text-white text-sm font-medium leading-tight">Student Referral System</p>
            <p class="text-white/40 text-xs mt-0.5">Misamis University</p>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 overflow-y-auto py-2">

            <p class="px-5 pt-4 pb-1 text-white/30 text-[10px] uppercase tracking-widest">Main</p>

            <a href="{{ route('counselor.dashboard') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('counselor.dashboard') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-layout-dashboard text-base w-5"></i> Dashboard
            </a>

            <p class="px-5 pt-4 pb-1 text-white/30 text-[10px] uppercase tracking-widest">Counseling Services</p>

            <a href="{{ route('counselor.referrals.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('counselor.referrals.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-file-text text-base w-5"></i> Referrals
            </a>

            <a href="{{ route('counselor.interventions.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('counselor.interventions.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-heart-handshake text-base w-5"></i> Interventions
            </a>

            <a href="{{ route('counselor.behavioral-reports.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('counselor.behavioral-reports.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-message-report text-base w-5"></i> Behavioral Reports
            </a>

            <p class="px-5 pt-4 pb-1 text-white/30 text-[10px] uppercase tracking-widest">Management</p>

            <a href="{{ route('counselor.seminars.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('counselor.seminars.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-school text-base w-5"></i> Seminars
            </a>

        </nav>

        {{-- Bottom user --}}
        <div class="px-3 py-3 border-t border-white/10">
            <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-full bg-[#1E3A5F] flex items-center justify-center
                            text-[#7BB3F0] text-xs font-medium flex-shrink-0">
                    {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                </div>
                <div class="min-w-0">
                    <p class="text-white text-xs font-medium truncate">{{ auth()->user()->name }}</p>
                    <p class="text-white/40 text-[11px]">Guidance Counselor</p>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="ml-auto">
                    @csrf
                    <button type="submit" class="text-white/40 hover:text-white/80 transition-colors" title="Logout">
                        <i class="ti ti-logout text-base"></i>
                    </button>
                </form>
            </div>
        </div>

    </aside>

    {{-- ── Main content (offset by sidebar width) ─────────── --}}
    <div class="main-content flex flex-col ml-[220px]">

        {{-- Top bar --}}
        <header class="bg-white border-b border-gray-100 px-6 py-3 flex items-center justify-between sticky top-0 z-20">
            <div>
                <h1 class="text-[15px] font-medium text-gray-900">@yield('page-title', 'Dashboard')</h1>
                <p class="text-xs text-gray-400 mt-0.5">@yield('page-sub', 'Guidance & Counseling Office')</p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-xs text-gray-400">
                    <i class="ti ti-calendar text-sm align-[-2px] mr-1"></i>
                    {{ now()->format('F d, Y') }}
                </span>

                {{-- Notifications --}}
                <div class="relative cursor-pointer text-gray-400 hover:text-gray-600">
                    <i class="ti ti-bell text-xl"></i>
                    @if(isset($unreadNotifications) && $unreadNotifications > 0)
                        <span class="absolute -top-0.5 -right-0.5 w-2 h-2 bg-red-500 rounded-full
                                     border-2 border-white"></span>
                    @endif
                </div>
            </div>
        </header>

        {{-- Page content --}}
        <main class="flex-1 p-6">

            {{-- Flash messages (Toast) --}}
            @if(session('success'))
                <div x-data="{ show: true }" x-show="show" 
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform translate-y-[-1rem]"
                     x-transition:enter-end="opacity-100 transform translate-y-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 transform translate-y-0"
                     x-transition:leave-end="opacity-0 transform translate-y-[-1rem]"
                     x-init="setTimeout(() => show = false, 4000)"
                     class="fixed top-6 right-6 z-[100] bg-white border border-gray-100 text-gray-800 text-sm
                            rounded-xl px-5 py-4 flex items-center gap-4 shadow-2xl shadow-gray-200/50 max-w-sm w-full print:hidden ring-1 ring-black/5" x-cloak>
                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-emerald-50 text-emerald-600 flex-shrink-0">
                        <i class="ti ti-check text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-gray-900 text-sm">Success</p>
                        <p class="text-gray-500 text-xs mt-0.5">{{ session('success') }}</p>
                    </div>
                    <button @click="show = false" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
                        <i class="ti ti-x text-lg"></i>
                    </button>
                </div>
            @endif

            @if(session('error'))
                <div x-data="{ show: true }" x-show="show" 
                     x-transition:enter="transition ease-out duration-300"
                     x-transition:enter-start="opacity-0 transform translate-y-[-1rem]"
                     x-transition:enter-end="opacity-100 transform translate-y-0"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100 transform translate-y-0"
                     x-transition:leave-end="opacity-0 transform translate-y-[-1rem]"
                     x-init="setTimeout(() => show = false, 5000)"
                     class="fixed top-6 right-6 z-[100] bg-white border border-gray-100 text-gray-800 text-sm
                            rounded-xl px-5 py-4 flex items-center gap-4 shadow-2xl shadow-gray-200/50 max-w-sm w-full print:hidden ring-1 ring-black/5" x-cloak>
                    <div class="flex items-center justify-center w-10 h-10 rounded-full bg-red-50 text-red-600 flex-shrink-0">
                        <i class="ti ti-alert-circle text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold text-gray-900 text-sm">Error</p>
                        <p class="text-gray-500 text-xs mt-0.5">{{ session('error') }}</p>
                    </div>
                    <button @click="show = false" class="text-gray-400 hover:text-gray-600 transition flex-shrink-0">
                        <i class="ti ti-x text-lg"></i>
                    </button>
                </div>
            @endif

            @yield('content')
        </main>

    </div>
</div>

@include('components.confirm-modal')
@stack('scripts')
</body>
</html>
