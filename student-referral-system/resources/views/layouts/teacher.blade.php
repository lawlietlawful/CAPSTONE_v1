<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Teacher Portal') | MU Guidance</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@2.44.0/tabler-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Alpine JS for interactive components -->
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Tom Select for Searchable Dropdowns -->
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

    {{-- Google Fonts: Plus Jakarta Sans --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; }
        .sidebar { transition: all 0.3s ease; }
        .nav-item { transition: all 0.2s ease; }
        .nav-item:hover { background: rgba(255,255,255,0.05); transform: translateX(4px); color: #ffffff; }
        .nav-item.active { background: rgba(59, 130, 246, 0.1); border-left: 3px solid #3b82f6; color: #60a5fa !important; font-weight: 500; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 6px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.2); }
        
        /* Premium Shadows */
        .shadow-premium { box-shadow: 0 4px 20px -4px rgba(0,0,0,0.05), 0 2px 8px -2px rgba(0,0,0,0.02); }
        .shadow-hover { box-shadow: 0 10px 30px -5px rgba(0,0,0,0.08), 0 4px 12px -3px rgba(0,0,0,0.04); }
    </style>
</head>
<body class="bg-gray-50/50 text-gray-700 antialiased h-screen flex overflow-hidden selection:bg-blue-100 selection:text-blue-900">

    {{-- Sidebar --}}
    <aside class="sidebar w-64 h-full flex flex-col text-gray-300 shadow-xl z-20 flex-shrink-0 hidden md:flex bg-gradient-to-b from-slate-900 to-slate-800 border-r border-slate-700/50">
        <div class="p-5 flex items-center gap-3 border-b border-white/10">
            <div class="w-10 h-10 bg-amber-500 rounded-lg flex items-center justify-center text-white font-bold shadow-lg shadow-amber-500/20">
                <i class="ti ti-chalkboard text-xl"></i>
            </div>
            <div>
                <h1 class="text-white font-bold text-base tracking-tight leading-none">MU Portal</h1>
                <span class="text-amber-400 text-[11px] font-medium uppercase tracking-wider">Teacher</span>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto py-5 space-y-1 custom-scrollbar">
            <div class="px-5 text-[11px] font-semibold text-gray-500 uppercase tracking-wider mb-2 mt-4">Main Menu</div>
            
            <a href="{{ route('teacher.dashboard') }}" 
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm 
                      {{ request()->routeIs('teacher.dashboard') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-layout-dashboard text-base w-5"></i> Dashboard Overview
            </a>
            
            
            <a href="{{ route('teacher.referrals.index') }}" 
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm 
                      {{ request()->routeIs('teacher.referrals.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-file-alert text-base w-5"></i> File Referral
            </a>

            <a href="{{ route('teacher.behavioral-reports.index') }}" 
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm 
                      {{ request()->routeIs('teacher.behavioral-reports.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-report text-base w-5"></i> Behavioral Reports
            </a>
        </div>

        <div class="p-4 border-t border-white/10 mt-auto">
            <div class="flex items-center gap-3 px-2 py-2">
                <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center text-white font-bold">
                    {{ strtoupper(substr(auth()->user()->name ?? 'T', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white truncate">{{ auth()->user()->name }}</p>
                    <p class="text-[11px] text-gray-400 truncate">{{ auth()->user()->email }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="mt-2">
                @csrf
                <button type="submit" class="w-full text-left nav-item flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-red-400 hover:text-red-300 hover:bg-red-400/10 transition">
                    <i class="ti ti-logout text-base w-5"></i> Sign Out
                </button>
            </form>
        </div>
    </aside>

    {{-- Main Content --}}
    <main class="flex-1 flex flex-col h-full overflow-hidden relative bg-gray-50/50">
        {{-- Topbar --}}
        <header class="bg-white border-b border-gray-100 h-16 flex items-center justify-between px-6 z-10 shadow-sm flex-shrink-0">
            <div class="flex items-center gap-4">
                <div class="md:hidden w-8 h-8 bg-amber-500 rounded-md flex items-center justify-center text-white">
                    <i class="ti ti-chalkboard"></i>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800 leading-tight">@yield('page-title', 'Dashboard')</h2>
                    @hasSection('page-sub')
                        <p class="text-[11px] text-gray-500 font-medium tracking-wide">@yield('page-sub')</p>
                    @endif
                </div>
            </div>
            
            <div class="flex items-center gap-4">
                {{-- Date/Time Display --}}
                <div class="hidden md:flex items-center gap-2 text-sm text-gray-500 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-100">
                    <i class="ti ti-calendar"></i>
                    <span id="current-date" class="font-medium"></span>
                </div>
                
                {{-- Notifications --}}
                @include('components.notification-bell', [
                    'prefix' => 'teacher',
                    'accent' => 'amber',
                    'buttonClass' => 'w-9 h-9 flex items-center justify-center rounded-full bg-gray-50 text-gray-600 hover:bg-gray-100 border border-gray-200 transition relative',
                    'badgeClass' => 'absolute top-1 right-1 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white',
                ])
            </div>
        </header>

        {{-- Scrollable Content Area --}}
        <div class="flex-1 overflow-y-auto p-6 scroll-smooth">
            <div class="max-w-7xl mx-auto pb-10">
                @yield('content')
            </div>
        </div>
    </main>

    <script>
        // Setup Date display
        const dateEl = document.getElementById('current-date');
        if(dateEl) {
            const options = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' };
            dateEl.textContent = new Date().toLocaleDateString('en-US', options);
        }
    </script>
    
    @stack('scripts')
    @yield('scripts')
</body>
</html>
