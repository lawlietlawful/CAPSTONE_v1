@extends('layouts.admin')

@section('title', 'Referral Management')
@section('page-title', 'Referral Management')
@section('page-sub', 'Track student referrals, assign counselors, and monitor resolution progress')

@section('content')

<div x-data="{ activeModal: null, activeId: null }">

{{-- ── Summary Cards ─────────────────────────────────────────── --}}
<div class="grid grid-cols-4 gap-3 mb-5">
    <a href="{{ route('admin.referrals.index') }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-gray-300 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center shrink-0">
            <i class="ti ti-file-text text-gray-500 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-gray-900 leading-none">{{ number_format($totalCount) }}</div>
            <div class="text-[11px] font-medium text-gray-400 mt-0.5">Total Referrals</div>
        </div>
    </a>
    <a href="{{ route('admin.referrals.index', ['status' => 'pending']) }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-blue-200 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
            <i class="ti ti-clock text-blue-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-blue-700 leading-none">{{ number_format($pendingCount) }}</div>
            <div class="text-[11px] font-medium text-gray-400 mt-0.5">Pending</div>
        </div>
    </a>
    <a href="{{ route('admin.referrals.index', ['status' => 'in_progress']) }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-amber-200 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">
            <i class="ti ti-progress text-amber-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-amber-700 leading-none">{{ number_format($inProgressCount) }}</div>
            <div class="text-[11px] font-medium text-gray-400 mt-0.5">In Progress</div>
        </div>
    </a>
    <a href="{{ route('admin.referrals.index', ['status' => 'resolved']) }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-green-200 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center shrink-0">
            <i class="ti ti-circle-check text-green-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-green-700 leading-none">{{ number_format($resolvedCount) }}</div>
            <div class="text-[11px] font-medium text-gray-400 mt-0.5">Resolved</div>
        </div>
    </a>
</div>

{{-- ── Filters ───────────────────────────────────────────────── --}}
<div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-4 mb-4">
    <form method="GET" action="{{ route('admin.referrals.index') }}" class="flex flex-wrap gap-3 items-end" id="filterForm">
        <div class="flex-1 min-w-[250px] w-full relative">
            <label class="block text-xs font-medium text-gray-500 mb-1">Search Student</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="ti ti-search text-gray-400"></i>
                </div>
                <input type="text" id="searchInput" name="search" value="{{ request('search') }}" placeholder="Name or Student ID..."
                    class="block w-full pl-10 pr-3 py-2 border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition" autocomplete="off">
            </div>
        </div>
        
        <div class="w-full lg:w-40">
            <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
            <select name="status" onchange="document.getElementById('filterForm').submit();" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                <option value="">All Statuses</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                <option value="resolved" {{ request('status') == 'resolved' ? 'selected' : '' }}>Resolved</option>
                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
        </div>
        
        <div class="w-full lg:w-40">
            <label class="block text-xs font-medium text-gray-500 mb-1">Priority</label>
            <select name="priority" onchange="document.getElementById('filterForm').submit();" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                <option value="">All Priorities</option>
                <option value="low" {{ request('priority') == 'low' ? 'selected' : '' }}>Low</option>
                <option value="moderate" {{ request('priority') == 'moderate' ? 'selected' : '' }}>Moderate</option>
                <option value="high" {{ request('priority') == 'high' ? 'selected' : '' }}>High</option>
            </select>
        </div>
        
        <div class="w-full lg:w-40">
            <label class="block text-xs font-medium text-gray-500 mb-1">Date Range</label>
            <select name="date_range" onchange="document.getElementById('filterForm').submit();" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                <option value="">All Time</option>
                <option value="today" {{ request('date_range') == 'today' ? 'selected' : '' }}>Today</option>
                <option value="this_week" {{ request('date_range') == 'this_week' ? 'selected' : '' }}>This Week</option>
                <option value="this_month" {{ request('date_range') == 'this_month' ? 'selected' : '' }}>This Month</option>
                <option value="last_month" {{ request('date_range') == 'last_month' ? 'selected' : '' }}>Last Month</option>
            </select>
        </div>

        <div class="w-full lg:w-40">
            <label class="block text-xs font-medium text-gray-500 mb-1">Counselor</label>
            <select name="counselor_id" onchange="document.getElementById('filterForm').submit();" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                <option value="">All Counselors</option>
                @foreach($counselors as $c)
                    <option value="{{ $c->id }}" {{ request('counselor_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        
        <div class="flex gap-2 w-full lg:w-auto mt-3 lg:mt-0">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                <i class="ti ti-filter"></i> Filter
            </button>
            <a href="{{ route('admin.referrals.index') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                <i class="ti ti-x"></i> Clear
            </a>
            <a href="{{ route('admin.referrals.export', request()->query()) }}" class="px-4 py-2 bg-emerald-600 text-white text-sm font-medium rounded-lg hover:bg-emerald-700 transition shadow-sm flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                <i class="ti ti-download"></i> Export Data
            </a>
            <button type="button" @click="activeModal = 'create'" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                <i class="ti ti-plus"></i> New Referral
            </button>
        </div>
    </form>
</div>

{{-- ── Referrals Table ───────────────────────────────────────── --}}
<form method="POST" action="{{ route('admin.referrals.bulkAction') }}" x-data="{ selected: [], selectAll: false, showAssignModal: false, assignCounselorId: '' }">
    @csrf
    
    <!-- Floating Action Bar -->
    <div x-cloak x-show="selected.length > 0"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-8"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-8"
         class="fixed bottom-8 left-1/2 -translate-x-1/2 z-50 bg-white text-gray-900 px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-6 border border-gray-200">
        <div class="flex items-center gap-3 pr-6 border-r border-gray-200">
            <span class="flex items-center justify-center w-6 h-6 rounded-full bg-blue-500 text-xs font-bold text-white" x-text="selected.length"></span>
            <span class="text-sm font-medium text-gray-700">Selected</span>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" name="action" value="in_progress" class="text-sm px-3.5 py-1.5 rounded-lg transition font-medium bg-amber-50 text-amber-700 border border-amber-200 hover:bg-amber-100">In Progress</button>
            <button type="submit" name="action" value="resolved" class="text-sm px-3.5 py-1.5 rounded-lg transition font-medium bg-green-50 text-green-700 border border-green-200 hover:bg-green-100">Resolved</button>
            <button type="button" @click="showAssignModal = true" class="text-sm px-3.5 py-1.5 rounded-lg transition font-medium text-white bg-gray-900 hover:bg-gray-800 flex items-center gap-1.5">
                <i class="ti ti-user-plus text-gray-300"></i> Assign
            </button>
            <button type="submit" name="action" value="export_selected" class="text-sm px-3.5 py-1.5 rounded-lg transition font-medium bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200 flex items-center gap-1.5">
                <i class="ti ti-download text-gray-400"></i> Export
            </button>
            <button type="submit" name="action" value="delete" onclick="return confirm('Delete selected referrals?')" class="text-sm px-3.5 py-1.5 rounded-lg transition font-medium bg-red-50 text-red-600 border border-red-200 hover:bg-red-100">Delete</button>
        </div>
    </div>

    <!-- Assign Counselor Modal -->
    <div x-cloak x-show="showAssignModal" class="fixed inset-0 z-[100] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 text-center">
            <div class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" @click="showAssignModal = false"></div>
            <div class="inline-block w-full max-w-sm p-6 overflow-hidden text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl relative z-[101]">
                <h3 class="text-lg font-bold text-gray-900 mb-4">Assign Counselor</h3>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Select Counselor</label>
                    <select name="assign_counselor_id" x-model="assignCounselorId" class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                        <option value="">-- Select Counselor --</option>
                        @foreach($counselors as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3 w-full">
                    <button type="button" @click="showAssignModal = false" class="w-full px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition">Cancel</button>
                    <button type="submit" name="action" value="assign_counselor" :disabled="!assignCounselorId" class="w-full px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">Assign</button>
                </div>
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50/50 border-b border-gray-100">
                    <th class="px-5 py-3 w-10 text-center">
                        <input type="checkbox" x-model="selectAll" @change="selected = selectAll ? {{ json_encode($referrals->pluck('id')) }} : []" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 shadow-sm cursor-pointer">
                    </th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Student</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Referral Type</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Referred By</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider text-center">Priority</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider text-center">Status</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Counselor</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Date</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                @forelse($referrals as $referral)
                    <tr class="hover:bg-gray-50/50 transition">
                        <td class="px-5 py-3 text-center">
                            <input type="checkbox" name="referral_ids[]" value="{{ $referral->id }}" x-model="selected" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 shadow-sm cursor-pointer">
                        </td>
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.referrals.show', $referral->id) }}" class="font-medium text-gray-900 hover:text-blue-600 hover:underline transition block">
                                {{ $referral->student->last_name }}, {{ $referral->student->first_name }}
                            </a>
                            <p class="text-xs text-gray-500">{{ $referral->student->student_id_number }}</p>
                        </td>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-1.5" x-data="{ showTooltip: false }">
                                <span class="text-gray-600">{{ $referral->referral_type_label }}</span>
                                <div class="relative flex items-center">
                                    <button type="button" @mouseenter="showTooltip = true" @mouseleave="showTooltip = false" class="text-gray-400 hover:text-blue-500 transition">
                                        <i class="ti ti-info-circle"></i>
                                    </button>
                                    
                                    <!-- Tooltip Popover -->
                                    <div x-cloak x-show="showTooltip" 
                                         x-transition.opacity.duration.200ms
                                         class="absolute z-50 w-64 p-3 mt-1 text-sm text-left bg-gray-900 text-white rounded-lg shadow-xl bottom-full mb-2 left-1/2 -translate-x-1/2 before:content-[''] before:absolute before:top-full before:left-1/2 before:-translate-x-1/2 before:border-4 before:border-transparent before:border-t-gray-900 pointer-events-none">
                                        <div class="font-medium text-gray-400 mb-1 text-[10px] uppercase tracking-wider">Reason:</div>
                                        <p class="mb-2 line-clamp-4">{{ $referral->display_reason }}</p>
                                        
                                        @if($referral->counselor_notes)
                                            <div class="font-medium text-gray-400 mb-1 text-[10px] uppercase tracking-wider border-t border-gray-700 pt-2 mt-2">Counselor Notes:</div>
                                            <p class="line-clamp-3 text-gray-300">{{ $referral->counselor_notes }}</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-gray-600">{{ $referral->referredBy->name ?? '—' }}</td>
                        <td class="px-5 py-3 text-center">
                            @php
                                $priorityClass = match($referral->priority) {
                                    'high'     => 'bg-red-50 text-red-700',
                                    'moderate' => 'bg-amber-50 text-amber-700',
                                    default    => 'bg-green-50 text-green-700',
                                };
                            @endphp
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-medium {{ $priorityClass }}">
                                @if($referral->priority === 'high')
                                    <span class="relative flex h-2 w-2">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                                    </span>
                                @endif
                                {{ ucfirst($referral->priority) }}
                            </span>
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
                            <div class="flex justify-center items-center gap-2">
                                <a href="{{ route('admin.referrals.show', $referral->id) }}" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition text-lg" title="View Details">
                                    <i class="ti ti-eye"></i>
                                </a>
                                
                                <!-- Edit Button -->
                                <button type="button" @click.prevent="activeModal = 'edit'; activeId = {{ $referral->id }}" class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition text-lg" title="Edit Referral">
                                    <i class="ti ti-edit"></i>
                                </button>
                                
                                <!-- Delete Button -->
                                <button type="button" @click.prevent="activeModal = 'delete'; activeId = {{ $referral->id }}" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition text-lg" title="Delete Referral">
                                    <i class="ti ti-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="py-16 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center text-gray-300 mb-3">
                                    <i class="ti ti-file-off text-2xl"></i>
                                </div>
                                <h3 class="text-base font-medium text-gray-900 mb-1">No Referrals Found</h3>
                                <p class="text-sm text-gray-500 mb-4">Referrals will appear here once teachers or counselors submit them.</p>
                                <a href="{{ route('admin.referrals.create') }}" class="px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition shadow-sm">
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
        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/30 flex justify-end">
            {{ $referrals->links() }}
        </div>
    @endif
</div>
</form>

<!-- Modal Declarations Outside Main Form -->
    <div x-cloak x-show="activeModal === 'create'" x-data="{ referralType: '' }">
        <!-- Create Modal -->
        <div class="fixed inset-0 z-[100] overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" @click="activeModal = null"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block w-full max-w-2xl p-6 text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl sm:p-8 relative z-[101]">
                    <div class="flex justify-between items-center mb-5 border-b border-gray-100 pb-4">
                        <h3 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
                            <i class="ti ti-plus text-blue-500"></i> New Referral
                        </h3>
                        <button type="button" @click="activeModal = null" class="text-gray-400 hover:text-gray-600 transition">
                            <i class="ti ti-x text-xl"></i>
                        </button>
                    </div>
                    <form action="{{ route('admin.referrals.store') }}" method="POST">
                        @csrf
                        <!-- Modal Content -->
                        <div class="space-y-5">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Student <span class="text-red-500">*</span></label>
                                <select name="student_id" required class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                    <option value="">Select a student...</option>
                                    @foreach($students as $s)
                                        <option value="{{ $s->id }}">{{ $s->last_name }}, {{ $s->first_name }} ({{ $s->student_id_number }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="grid grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Referral Type <span class="text-red-500">*</span></label>
                                    <select name="referral_type" required x-model="referralType" class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        <option value="">Select type...</option>
                                        @foreach(\App\Models\Referral::REFERRAL_TYPES as $type)
                                            <option value="{{ $type }}">{{ $type }}</option>
                                        @endforeach
                                    </select>
                                    <div x-show="referralType === 'Other'" x-cloak class="mt-2">
                                        <input type="text" name="referral_type_other" placeholder="Please specify"
                                            class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Priority Level <span class="text-red-500">*</span></label>
                                    <select name="priority" required class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        <option value="low">Low</option>
                                        <option value="moderate">Moderate</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Assign to Counselor</label>
                                <select name="counselor_id" class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                    <option value="">Unassigned (Counselor will pick up)</option>
                                    @foreach($counselors as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Reason for Referral <span class="text-red-500">*</span></label>
                                <textarea name="reason" rows="3" required placeholder="Describe the concern or reason for referring this student..." class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm"></textarea>
                            </div>
                        </div>
                        <div class="mt-8 flex justify-end gap-3 pt-5 border-t border-gray-100">
                            <button type="button" @click="activeModal = null" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">Cancel</button>
                            <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-2"><i class="ti ti-send"></i> Submit Referral</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@foreach($referrals as $referral)
    <div x-cloak x-show="activeModal === 'edit' && activeId === {{ $referral->id }}" x-data="{ referralType: '{{ $referral->referral_type }}' }">
        <!-- Edit Modal -->
        <div class="fixed inset-0 z-[100] overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" @click="activeModal = null; activeId = null"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block w-full max-w-2xl p-6 text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl sm:p-8 relative z-[101]">
                    <div class="flex justify-between items-center mb-5 border-b border-gray-100 pb-4">
                        <h3 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
                            <i class="ti ti-edit text-amber-500"></i> Edit Referral
                        </h3>
                        <button type="button" @click="activeModal = null; activeId = null" class="text-gray-400 hover:text-gray-600 transition">
                            <i class="ti ti-x text-xl"></i>
                        </button>
                    </div>
                    <form action="{{ route('admin.referrals.update', $referral->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <!-- Modal Content -->
                        <div class="space-y-5">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Student</label>
                                <div class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-600 text-left">
                                    {{ $referral->student->last_name }}, {{ $referral->student->first_name }} ({{ $referral->student->student_id_number }})
                                </div>
                            </div>
                            <div class="grid grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Referral Type <span class="text-red-500">*</span></label>
                                    <select name="referral_type" required x-model="referralType" class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        @unless(in_array($referral->referral_type, \App\Models\Referral::REFERRAL_TYPES))
                                            {{-- Preserve a legacy value (e.g. "Automated Risk Alert") that predates the fixed option list, so saving without changing this field doesn't silently overwrite it. --}}
                                            <option value="{{ $referral->referral_type }}">{{ $referral->referral_type }} (legacy)</option>
                                        @endunless
                                        @foreach(\App\Models\Referral::REFERRAL_TYPES as $type)
                                            <option value="{{ $type }}">{{ $type }}</option>
                                        @endforeach
                                    </select>
                                    <div x-show="referralType === 'Other'" x-cloak class="mt-2">
                                        <input type="text" name="referral_type_other" value="{{ $referral->referral_type_other }}" placeholder="Please specify"
                                            class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Priority Level <span class="text-red-500">*</span></label>
                                    <select name="priority" required class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        <option value="low" {{ $referral->priority == 'low' ? 'selected' : '' }}>Low</option>
                                        <option value="moderate" {{ $referral->priority == 'moderate' ? 'selected' : '' }}>Moderate</option>
                                        <option value="high" {{ $referral->priority == 'high' ? 'selected' : '' }}>High</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Status <span class="text-red-500">*</span></label>
                                    <select name="status" required class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        <option value="pending" {{ $referral->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                        <option value="in_progress" {{ $referral->status == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                        <option value="resolved" {{ $referral->status == 'resolved' ? 'selected' : '' }}>Resolved</option>
                                        <option value="cancelled" {{ $referral->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Assigned Counselor</label>
                                    <select name="counselor_id" class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        <option value="">Unassigned</option>
                                        @foreach($counselors as $c)
                                            <option value="{{ $c->id }}" {{ $referral->counselor_id == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Reason for Referral <span class="text-red-500">*</span></label>
                                <textarea name="reason" rows="3" required class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">{{ $referral->reason }}</textarea>
                            </div>
                        </div>
                        <div class="mt-8 flex justify-end gap-3 pt-5 border-t border-gray-100">
                            <button type="button" @click="activeModal = null; activeId = null" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">Cancel</button>
                            <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-2"><i class="ti ti-device-floppy"></i> Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div x-cloak x-show="activeModal === 'delete' && activeId === {{ $referral->id }}">
        <!-- Delete Modal -->
        <div class="fixed inset-0 z-[100] overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 text-center">
                <div class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" @click="activeModal = null; activeId = null"></div>
                <div class="inline-block w-full max-w-md p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl relative z-[101]">
                    <div class="flex flex-col items-center text-center">
                        <div class="w-16 h-16 bg-red-50 rounded-full flex items-center justify-center mb-4">
                            <i class="ti ti-alert-triangle text-3xl text-red-500"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">Delete Referral</h3>
                        <p class="text-gray-500 text-sm mb-6">Are you sure you want to delete this referral for <strong>{{ $referral->student->first_name }}</strong>? This action cannot be undone.</p>
                        
                        <div class="grid grid-cols-2 gap-3 w-full">
                            <button type="button" @click="activeModal = null; activeId = null" class="w-full px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition">
                                Cancel
                            </button>
                            <form action="{{ route('admin.referrals.destroy', $referral->id) }}" method="POST" class="w-full">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="w-full px-4 py-2.5 text-sm font-medium text-white bg-red-600 rounded-xl hover:bg-red-700 transition shadow-sm">
                                    Yes, Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endforeach

</div> <!-- Close Alpine Wrapper -->

@push('scripts')
<script>
    let searchTimeout;
    const searchInput = document.getElementById('searchInput');
    const filterForm = document.getElementById('filterForm');

    if (searchInput && filterForm) {
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const val = e.target.value.trim();
            
            // Auto submit if cleared or if length >= 2
            if (val.length === 0 || val.length >= 2) {
                searchTimeout = setTimeout(() => {
                    filterForm.submit();
                }, 500); // Wait 500ms after user stops typing
            }
        });
        
        // Put cursor at the end of text when page reloads with search value
        if (searchInput.value) {
            const length = searchInput.value.length;
            searchInput.focus();
            searchInput.setSelectionRange(length, length);
        }
    }
</script>
@endpush

@endsection
