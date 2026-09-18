<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Modal')</title>

    {{-- Tailwind CSS via CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Alpine.js --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    {{-- Tabler Icons --}}
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">

    {{-- Google Fonts: Plus Jakarta Sans --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, sans-serif; background: #f8fafc; margin: 0; padding: 0; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.1); border-radius: 6px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.2); }
        .shadow-premium { box-shadow: 0 4px 20px -4px rgba(0,0,0,0.05), 0 2px 8px -2px rgba(0,0,0,0.02); }
    </style>
</head>
<body class="text-gray-900 antialiased h-screen flex flex-col bg-gray-50/50">
    {{-- Modal Header --}}
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50 flex justify-between items-center flex-shrink-0 sticky top-0 z-50">
        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
            <i class="ti ti-messages text-indigo-600"></i> Counselor Notices
        </h3>
        <button onclick="window.parent.postMessage('close-messages', window.location.origin)" class="text-gray-400 hover:text-gray-600 transition">
            <i class="ti ti-x text-xl"></i>
        </button>
    </div>

    <div class="flex-1 overflow-auto p-4">
        @if(session('success'))
            <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)" class="mb-4 bg-emerald-50 text-emerald-600 px-4 py-3 rounded-xl text-sm border border-emerald-100 flex items-center justify-between">
                <span class="flex items-center gap-2"><i class="ti ti-check"></i> {{ session('success') }}</span>
            </div>
        @endif
        @yield('content')
    </div>
    @stack('scripts')
</body>
</html>
