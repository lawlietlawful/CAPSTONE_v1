<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — Student Referral System</title>

    {{-- Tailwind CSS via CDN (replace with npm build later) --}}
    <script src="{{ asset('vendor/tailwind.play.js') }}"></script>

    {{-- Chart.js --}}
    <script src="{{ asset('vendor/chart.umd.js') }}"></script>

    {{-- Alpine.js --}}
    <script defer src="{{ asset('vendor/alpine.min.js') }}"></script>

    {{-- Tabler Icons --}}
    <link rel="stylesheet" href="{{ asset('vendor/tabler/tabler-icons.min.css') }}">
    
    {{-- Google Fonts: Plus Jakarta Sans --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
        .sidebar { width: 250px; min-width: 250px; }
        .main-content { flex: 1; min-width: 0; }
        .nav-item { transition: all 0.2s ease; }
        .nav-item:hover { background: rgba(255,255,255,0.05); transform: translateX(4px); }
        .nav-item.active { background: rgba(59, 130, 246, 0.1); border-left: 3px solid #3b82f6; color: #60a5fa !important; }
        .risk-bar-low    { background: #16A34A; }
        .risk-bar-mod    { background: #D97706; }
        .risk-bar-high   { background: #DC2626; }
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
<body class="bg-gray-50/50 text-gray-900 antialiased selection:bg-blue-100 selection:text-blue-900">

<div class="flex min-h-screen">

    {{-- ── Sidebar ─────────────────────────────────────────── --}}
    <aside class="sidebar bg-gradient-to-b from-slate-900 to-slate-800 border-r border-slate-700/50 flex flex-col fixed top-0 left-0 h-screen z-30 shadow-xl">

        {{-- Logo --}}
        <div class="px-5 py-5 border-b border-white/10">
            <p class="text-white text-sm font-medium leading-tight">Student Referral System</p>
            <p class="text-white/40 text-xs mt-0.5">Misamis University</p>
        </div>

        {{-- Nav --}}
        <nav class="flex-1 overflow-y-auto py-2">

            <p class="px-5 pt-4 pb-1 text-white/30 text-[10px] uppercase tracking-widest">Main</p>

            <a href="{{ auth()->user()->role === 'super_admin' ? route('admin.dashboard') : route('counselor.dashboard') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('admin.dashboard') || request()->routeIs('counselor.dashboard') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-layout-dashboard text-base w-5"></i> Overview
            </a>

            <a href="{{ route('admin.students.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('admin.students.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-users text-base w-5"></i> Students
            </a>

            <p class="px-5 pt-4 pb-1 text-white/30 text-[10px] uppercase tracking-widest">Counseling Services</p>

            <a href="{{ auth()->user()->role === 'super_admin' ? route('admin.referrals.index') : route('counselor.referrals.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('admin.referrals.*') || request()->routeIs('counselor.referrals.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-file-text text-base w-5"></i> Referrals
            </a>

            <a href="{{ route('counselor.interventions.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('counselor.interventions.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-heart-handshake text-base w-5"></i> Interventions
            </a>

            <a href="{{ route('admin.behavioral-reports.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('admin.behavioral-reports.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-message-report text-base w-5"></i> Behavioral Reports
            </a>

            <a href="{{ route('admin.risk.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('admin.risk.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-alert-triangle text-base w-5"></i> At-Risk Students
            </a>

            <p class="px-5 pt-4 pb-1 text-white/30 text-[10px] uppercase tracking-widest">Management</p>

            <a href="{{ route('admin.teachers.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('admin.teachers.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-user-check text-base w-5"></i> Teachers
            </a>

            <a href="{{ route('admin.users.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('admin.users.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-user-cog text-base w-5"></i> User Management
            </a>

            <a href="{{ route('admin.courses.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('admin.courses.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-books text-base w-5"></i> Courses &amp; Sections
            </a>

            <a href="{{ auth()->user()->role === 'super_admin' ? route('admin.seminars.index') : route('counselor.seminars.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('admin.seminars.*') || request()->routeIs('counselor.seminars.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-school text-base w-5"></i> Seminars
            </a>

            @if(auth()->user()->role === 'super_admin')
            <p class="px-5 pt-4 pb-1 text-white/30 text-[10px] uppercase tracking-widest">System</p>

            <a href="{{ route('admin.analytics.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('admin.analytics.index') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-chart-bar text-base w-5"></i> Analytics
            </a>

            <a href="{{ route('admin.sms-logs.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('admin.sms-logs.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-message text-base w-5"></i> SMS Logs
            </a>


            <a href="{{ route('admin.settings.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('admin.settings.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-settings text-base w-5"></i> Settings
            </a>
            @endif

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
                    <p class="text-white/40 text-[11px]">{{ auth()->user()->role === 'super_admin' ? 'Administrator' : 'Guidance Counselor' }}</p>
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
    <div class="main-content flex flex-col ml-[250px]">

        {{-- Top bar --}}
        <header class="bg-white border-b border-gray-100 px-6 py-3 flex items-center justify-between sticky top-0 z-20">
            <div>
                <h1 class="text-[15px] font-medium text-gray-900">@yield('page-title', 'Overview')</h1>
                <p class="text-xs text-gray-400 mt-0.5">@yield('page-sub', 'S.Y. 2025–2026 · Second Semester')</p>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-xs text-gray-400">
                    <i class="ti ti-calendar text-sm align-[-2px] mr-1"></i>
                    {{ now()->format('F d, Y') }}
                </span>

                {{-- Notifications --}}
                @include('components.notification-bell', ['prefix' => 'admin', 'accent' => 'blue'])
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

            {{-- Activation code — shown ONCE after creating/resetting a teacher.
                 A modal, not an auto-dismissing toast, so the admin can copy it. --}}
            @if(session('activation_code'))
                @php($act = session('activation_code'))
                <div x-data="{ show: true, copied: false }" x-show="show" x-cloak
                     class="fixed inset-0 z-[110] flex items-center justify-center p-4 print:hidden">
                    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="show = false"></div>
                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md border border-gray-100 overflow-hidden"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100">
                        <div class="px-6 py-5 border-b border-gray-100 bg-indigo-50/60 flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-indigo-100 text-indigo-600 flex items-center justify-center flex-shrink-0">
                                <i class="ti ti-key text-xl"></i>
                            </div>
                            <div>
                                <h3 class="font-semibold text-gray-900">Activation code</h3>
                                <p class="text-xs text-gray-500">For {{ $act['name'] }} — shown only once.</p>
                            </div>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <p class="text-[11px] uppercase tracking-wide text-gray-400 font-semibold mb-1">{{ $act['id_label'] ?? 'Employee ID' }}</p>
                                <p class="font-mono text-lg text-gray-900">{{ $act['school_id'] }}</p>
                            </div>
                            <div>
                                <p class="text-[11px] uppercase tracking-wide text-gray-400 font-semibold mb-1">One-time code</p>
                                <div class="flex items-center gap-2">
                                    <code class="font-mono text-2xl font-bold text-indigo-700 bg-indigo-50 px-4 py-2 rounded-xl tracking-[0.2em]">{{ $act['code'] }}</code>
                                    <button type="button"
                                        @click="navigator.clipboard && navigator.clipboard.writeText('{{ $act['code'] }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                        class="p-2.5 rounded-xl border border-gray-200 hover:bg-gray-50 text-gray-600 transition" title="Copy code">
                                        <i class="ti" :class="copied ? 'ti-check text-emerald-600' : 'ti-copy'"></i>
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 mt-2 leading-relaxed">
                                    They enter their {{ $act['id_label'] ?? 'Employee ID' }} + this code in {{ $act['portal'] ?? 'the mobile app' }} to set their own password. It won't be shown again — regenerate it if lost.
                                </p>
                            </div>
                        </div>
                        <div class="px-6 py-4 bg-gray-50/50 border-t border-gray-100 flex justify-end">
                            <button @click="show = false" class="px-5 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">Done</button>
                        </div>
                    </div>
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
