@extends('layouts.counselor')

@section('title', 'Referral Management')
@section('page-title', 'Referral Management')
@section('page-sub', 'Track student referrals, assign counselors, and monitor resolution progress')

@section('content')

{{-- ── Summary Cards ─────────────────────────────────────────── --}}
<div class="grid grid-cols-4 gap-3 mb-5">
    <div class="bg-white border border-gray-100 rounded-xl p-4 flex flex-col gap-1">
        <div class="w-9 h-9 rounded-lg bg-gray-50 flex items-center justify-center">
            <i class="ti ti-file-text text-gray-500 text-lg"></i>
        </div>
        <div class="text-2xl font-medium text-gray-900">{{ number_format($totalCount) }}</div>
        <div class="text-xs text-gray-400">Total Referrals</div>
    </div>
    <div class="bg-white border border-gray-100 rounded-xl p-4 flex flex-col gap-1">
        <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center">
            <i class="ti ti-clock text-blue-600 text-lg"></i>
        </div>
        <div class="text-2xl font-medium text-blue-700">{{ number_format($pendingCount) }}</div>
        <div class="text-xs text-gray-400">Pending</div>
    </div>
    <div class="bg-white border border-gray-100 rounded-xl p-4 flex flex-col gap-1">
        <div class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center">
            <i class="ti ti-progress text-amber-600 text-lg"></i>
        </div>
        <div class="text-2xl font-medium text-amber-700">{{ number_format($inProgressCount) }}</div>
        <div class="text-xs text-gray-400">In Progress</div>
    </div>
    <div class="bg-white border border-gray-100 rounded-xl p-4 flex flex-col gap-1">
        <div class="w-9 h-9 rounded-lg bg-green-50 flex items-center justify-center">
            <i class="ti ti-circle-check text-green-600 text-lg"></i>
        </div>
        <div class="text-2xl font-medium text-green-700">{{ number_format($resolvedCount) }}</div>
        <div class="text-xs text-gray-400">Resolved</div>
    </div>
</div>

{{-- ── Filters ───────────────────────────────────────────────── --}}
<div class="bg-white border border-gray-100 rounded-xl p-4 mb-4">
    <form method="GET" action="{{ route('counselor.referrals.index') }}" class="flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-xs font-medium text-gray-500 mb-1">Search Student</label>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Name or Student ID..."
                class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
            <select name="status" class="rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                <option value="">All Statuses</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                <option value="resolved" {{ request('status') == 'resolved' ? 'selected' : '' }}>Resolved</option>
                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Priority</label>
            <select name="priority" class="rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                <option value="">All Priorities</option>
                <option value="low" {{ request('priority') == 'low' ? 'selected' : '' }}>Low</option>
                <option value="moderate" {{ request('priority') == 'moderate' ? 'selected' : '' }}>Moderate</option>
                <option value="high" {{ request('priority') == 'high' ? 'selected' : '' }}>High</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-1.5">
                <i class="ti ti-filter"></i> Filter
            </button>
            <a href="{{ route('counselor.referrals.index') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5">
                <i class="ti ti-x"></i> Clear
            </a>
            <a href="{{ route('counselor.referrals.create') }}" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-1.5">
                <i class="ti ti-plus"></i> New Referral
            </a>
        </div>
    </form>
</div>

{{-- ── Referrals Table ───────────────────────────────────────── --}}
<div class="bg-white border border-gray-100 rounded-xl overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50/50 border-b border-gray-100">
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Student</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Referral Type</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Concern Type</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider text-center">AI Assessment</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider text-center">Status</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Counselor</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Date</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                @forelse($referrals as $referral)
                    <tr class="hover:bg-gray-50/50 transition">
                        <td class="px-5 py-3">
                            <p class="font-medium text-gray-900">{{ $referral->student->last_name }}, {{ $referral->student->first_name }}</p>
                            <p class="text-xs text-gray-500">{{ $referral->student->student_id_number }}</p>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $referral->referral_type_label }}</td>
                        <td class="px-5 py-3">
                            @php
                                $concernClass = match($referral->concern_type) {
                                    'academic'      => 'bg-blue-100 text-blue-700',
                                    'behavioral'    => 'bg-red-100 text-red-700',
                                    'emotional'     => 'bg-purple-100 text-purple-700',
                                    'family'        => 'bg-amber-100 text-amber-700',
                                    'peer_conflict' => 'bg-orange-100 text-orange-700',
                                    'attendance'    => 'bg-gray-200 text-gray-700',
                                    default         => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $concernClass }}">
                                {{ ucfirst(str_replace('_', ' ', $referral->concern_type ?? 'other')) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-center">
                            @if($referral->riskAssessment)
                                @php
                                    $aiClass = match($referral->riskAssessment->risk_level) {
                                        'high' => 'bg-red-100 text-red-800',
                                        'moderate' => 'bg-yellow-100 text-yellow-800',
                                        default => 'bg-green-100 text-green-800',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-bold {{ $aiClass }}">
                                    {{ ucfirst($referral->riskAssessment->risk_level) }} ({{ $referral->riskAssessment->risk_score }}%)
                                </span>
                            @else
                                <span class="text-xs text-gray-400 italic">Not Assessed</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-center">
                            @php
                                $statusClass = match($referral->status) {
                                    'pending'     => 'bg-blue-50 text-blue-700',
                                    'in_progress' => 'bg-amber-50 text-amber-700',
                                    'resolved'    => 'bg-green-50 text-green-700',
                                    'cancelled'   => 'bg-gray-100 text-gray-500',
                                    default       => 'bg-gray-100 text-gray-500',
                                };
                                $statusLabel = match($referral->status) {
                                    'pending'     => 'Pending',
                                    'in_progress' => 'In Progress',
                                    'resolved'    => 'Resolved',
                                    'cancelled'   => 'Cancelled',
                                    default       => ucfirst($referral->status),
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statusClass }}">
                                {{ $statusLabel }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $referral->counselor->name ?? 'Unassigned' }}</td>
                        <td class="px-5 py-3 text-xs text-gray-500">{{ $referral->created_at->format('M d, Y') }}</td>
                        <td class="px-5 py-3 text-center">
                            <a href="{{ route('counselor.referrals.show', $referral->id) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-16 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center text-gray-300 mb-3">
                                    <i class="ti ti-file-off text-2xl"></i>
                                </div>
                                <h3 class="text-base font-medium text-gray-900 mb-1">No Referrals Found</h3>
                                <p class="text-sm text-gray-500 mb-4">Referrals will appear here once teachers or counselors submit them.</p>
                                <a href="{{ route('counselor.referrals.create') }}" class="px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition shadow-sm">
                                    Create Referral
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($referrals->hasPages())
        <div class="px-5 py-4 bg-gray-50/50 border-t border-gray-100">
            {{ $referrals->links() }}
        </div>
    @endif
</div>

@endsection
