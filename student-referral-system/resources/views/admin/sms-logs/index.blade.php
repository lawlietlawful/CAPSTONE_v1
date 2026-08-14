@extends('layouts.admin')

@section('title', 'SMS Logs')
@section('page-title', 'SMS Notification Logs')
@section('page-sub', 'View and audit all automated SMS notifications sent by the system')

@section('content')

{{-- ── Summary Cards ─────────────────────────────────────────── --}}
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6 flex flex-col gap-2 transition-all duration-300 hover:-translate-y-1 hover:shadow-hover">
        <div class="w-9 h-9 rounded-lg bg-gray-50 flex items-center justify-center">
            <i class="ti ti-message text-gray-500 text-lg"></i>
        </div>
        <div class="text-2xl font-medium text-gray-900">{{ number_format($totalAll) }}</div>
        <div class="text-xs text-gray-400">Total Messages</div>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6 flex flex-col gap-2 transition-all duration-300 hover:-translate-y-1 hover:shadow-hover">
        <div class="w-9 h-9 rounded-lg bg-green-50 flex items-center justify-center">
            <i class="ti ti-circle-check text-green-600 text-lg"></i>
        </div>
        <div class="text-2xl font-medium text-green-700">{{ number_format($totalSent) }}</div>
        <div class="text-xs text-gray-400">Sent Successfully</div>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6 flex flex-col gap-2 transition-all duration-300 hover:-translate-y-1 hover:shadow-hover">
        <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center">
            <i class="ti ti-circle-x text-red-600 text-lg"></i>
        </div>
        <div class="text-2xl font-medium text-red-700">{{ number_format($totalFailed) }}</div>
        <div class="text-xs text-gray-400">Failed</div>
    </div>
    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6 flex flex-col gap-2 transition-all duration-300 hover:-translate-y-1 hover:shadow-hover">
        <div class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center">
            <i class="ti ti-clock text-amber-600 text-lg"></i>
        </div>
        <div class="text-2xl font-medium text-amber-700">{{ number_format($totalPending) }}</div>
        <div class="text-xs text-gray-400">Pending</div>
    </div>
</div>

{{-- ── Filters ───────────────────────────────────────────────── --}}
<div class="bg-white border border-gray-100 rounded-xl p-4 mb-4">
    <form method="GET" action="{{ route('admin.sms-logs.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-medium text-gray-500 mb-1">Search</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Recipient name, number, or message..."
                class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
            <select name="status" class="rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                <option value="">All Statuses</option>
                <option value="sent" {{ request('status') == 'sent' ? 'selected' : '' }}>Sent</option>
                <option value="failed" {{ request('status') == 'failed' ? 'selected' : '' }}>Failed</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Recipient Type</label>
            <select name="recipient_type" class="rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                <option value="">All Types</option>
                <option value="parent" {{ request('recipient_type') == 'parent' ? 'selected' : '' }}>Parent</option>
                <option value="student" {{ request('recipient_type') == 'student' ? 'selected' : '' }}>Student</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
            <input type="date" name="date_from" value="{{ request('date_from') }}"
                class="rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
            <input type="date" name="date_to" value="{{ request('date_to') }}"
                class="rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-1.5">
                <i class="ti ti-filter"></i> Filter
            </button>
            <a href="{{ route('admin.sms-logs.index') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5">
                <i class="ti ti-x"></i> Clear
            </a>
        </div>
    </form>
</div>

{{-- ── SMS Logs Table ────────────────────────────────────────── --}}
<div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50/50 border-b border-gray-100">
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Recipient</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Type</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Student</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider w-[30%]">Message</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider text-center">Status</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Sent At</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                @forelse($logs as $log)
                    <tr class="hover:bg-gray-50/50 transition">
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-900">{{ $log->recipient_name }}</p>
                            <p class="text-xs text-gray-500">{{ $log->recipient_number }}</p>
                        </td>
                        <td class="px-5 py-3">
                            @if($log->recipient_type === 'parent')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-purple-50 text-purple-700">
                                    <i class="ti ti-users"></i> Parent
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-50 text-blue-700">
                                    <i class="ti ti-school"></i> Student
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-gray-600">
                            @if($log->student)
                                {{ $log->student->last_name }}, {{ $log->student->first_name }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3">
                            <p class="text-xs text-gray-600 line-clamp-2" title="{{ $log->message }}">{{ $log->message }}</p>
                        </td>
                        <td class="px-5 py-3 text-center">
                            @if($log->status === 'sent')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-green-50 text-green-700">
                                    <i class="ti ti-circle-check"></i> Sent
                                </span>
                            @elseif($log->status === 'failed')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-red-50 text-red-700" title="{{ $log->error_message }}">
                                    <i class="ti ti-circle-x"></i> Failed
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium bg-amber-50 text-amber-700">
                                    <i class="ti ti-clock"></i> Pending
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-500">
                            @if($log->sent_at)
                                {{ $log->sent_at->format('M d, Y') }}
                                <br>
                                <span class="text-gray-400">{{ $log->sent_at->format('h:i A') }}</span>
                            @else
                                <span class="text-gray-400">{{ $log->created_at->format('M d, Y h:i A') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="py-16 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center text-gray-300 mb-3">
                                    <i class="ti ti-message-off text-2xl"></i>
                                </div>
                                <h3 class="text-base font-medium text-gray-900 mb-1">No SMS Logs Found</h3>
                                <p class="text-sm text-gray-500">SMS logs will appear here once the system sends automated notifications.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($logs->hasPages())
        <div class="px-5 py-4 bg-gray-50/50 border-t border-gray-100">
            {{ $logs->links() }}
        </div>
    @endif
</div>

@endsection
