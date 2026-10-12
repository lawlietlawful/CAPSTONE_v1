@extends('layouts.admin')

@section('title', 'Activity Log')
@section('page-title', 'Activity Log')
@section('page-sub', 'Who did what, and when — sign-ins, deletions, reassignments, account and settings changes')

@section('content')

@php
    // Action prefix → icon + colour, matching the app's badge palette.
    $styles = [
        'auth'         => ['ti-login', 'bg-slate-50 text-slate-600'],
        'referral'     => ['ti-file-text', 'bg-blue-50 text-blue-600'],
        'user'         => ['ti-user-cog', 'bg-violet-50 text-violet-600'],
        'student'      => ['ti-school', 'bg-emerald-50 text-emerald-600'],
        'seminar'      => ['ti-presentation', 'bg-amber-50 text-amber-600'],
        'course'       => ['ti-books', 'bg-cyan-50 text-cyan-600'],
        'intervention' => ['ti-heart-handshake', 'bg-rose-50 text-rose-600'],
        'risk'         => ['ti-alert-triangle', 'bg-orange-50 text-orange-600'],
        'settings'     => ['ti-settings', 'bg-gray-100 text-gray-600'],
    ];
    $danger = ['auth.failed', 'auth.lockout', 'referral.deleted', 'student.deleted', 'user.deleted', 'seminar.deleted', 'course.deleted', 'intervention.deleted'];
@endphp

@if($failedToday > 0)
    <div class="mb-5 p-3 rounded-xl bg-amber-50 border border-amber-200 text-sm text-amber-800 flex items-center gap-2" data-failed-today>
        <i class="ti ti-shield-exclamation text-amber-600"></i>
        {{ $failedToday }} failed sign-in {{ $failedToday === 1 ? 'attempt' : 'attempts' }} today.
        <a href="{{ route('admin.activity.index', ['group' => 'auth', 'search' => 'Failed']) }}" class="font-semibold underline">Review</a>
    </div>
@endif

<form method="GET" action="{{ route('admin.activity.index') }}" class="mb-5">
    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-4 flex flex-col lg:flex-row gap-3 items-end">
        <div class="flex-1 w-full">
            <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
            <div class="relative">
                <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Name, record, email or IP address..."
                       class="block w-full pl-10 pr-3 py-2 border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm">
            </div>
        </div>
        <div class="w-full lg:w-44">
            <label class="block text-xs font-medium text-gray-500 mb-1">Type</label>
            <select name="group" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm py-2 px-3">
                <option value="">All activity</option>
                @foreach(\App\Models\AuditLog::GROUPS as $value => $label)
                    <option value="{{ $value }}" @selected(request('group') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-full lg:w-48">
            <label class="block text-xs font-medium text-gray-500 mb-1">By</label>
            <select name="user_id" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm py-2 px-3">
                <option value="">Anyone</option>
                @foreach($staff as $person)
                    <option value="{{ $person->id }}" @selected((string) request('user_id') === (string) $person->id)>{{ $person->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex gap-2">
            @if(request()->hasAny(['search', 'group', 'user_id']) && collect(['search', 'group', 'user_id'])->contains(fn ($k) => request()->filled($k)))
                <a href="{{ route('admin.activity.index') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5 h-[38px]"><i class="ti ti-x"></i> Reset</a>
            @endif
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-1.5 h-[38px]"><i class="ti ti-filter"></i> Filter</button>
        </div>
    </div>
</form>

<div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50/50">
                    <th class="px-6 py-3 text-[11px] font-semibold uppercase tracking-wider text-gray-500">When</th>
                    <th class="px-6 py-3 text-[11px] font-semibold uppercase tracking-wider text-gray-500">Who</th>
                    <th class="px-6 py-3 text-[11px] font-semibold uppercase tracking-wider text-gray-500">What</th>
                    <th class="px-6 py-3 text-[11px] font-semibold uppercase tracking-wider text-gray-500">IP Address</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50 text-sm">
                @forelse($logs as $log)
                    @php
                        [$icon, $tone] = $styles[strtok($log->action, '.')] ?? ['ti-point', 'bg-gray-100 text-gray-500'];
                        $isDanger = in_array($log->action, $danger, true);
                    @endphp
                    <tr class="hover:bg-gray-50/50 transition" data-audit-row="{{ $log->action }}">
                        <td class="px-6 py-3 whitespace-nowrap">
                            <div class="text-gray-900">{{ $log->created_at->format('M d, Y') }}</div>
                            <div class="text-xs text-gray-400">{{ $log->created_at->format('h:i:s A') }} · {{ $log->created_at->diffForHumans() }}</div>
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-gray-800">{{ $log->user_name ?? '—' }}</td>
                        <td class="px-6 py-3">
                            <div class="flex items-start gap-3">
                                <span class="w-8 h-8 rounded-lg {{ $isDanger ? 'bg-red-50 text-red-600' : $tone }} flex items-center justify-center shrink-0"><i class="ti {{ $icon }}"></i></span>
                                <div class="min-w-0">
                                    <p class="{{ $isDanger ? 'text-red-700' : 'text-gray-800' }}">{{ $log->description }}</p>
                                    <p class="text-[11px] text-gray-400 font-mono mt-0.5">{{ $log->action }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-3 whitespace-nowrap text-xs text-gray-500 font-mono">{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-6 py-14 text-center">
                            <div class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 mx-auto mb-3"><i class="ti ti-history text-xl"></i></div>
                            <p class="text-sm font-medium text-gray-900">No activity {{ request()->hasAny(['search', 'group', 'user_id']) ? 'matches these filters' : 'recorded yet' }}</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($logs->hasPages())
        <div class="px-6 py-4 border-t border-gray-100">{{ $logs->links() }}</div>
    @endif
</div>

@endsection
