@extends('layouts.admin')

@section('title', 'At-Risk Students')
@section('page-title', 'Early Warning System')
@section('page-sub', 'Monitor students identified as at-risk by the predictive analytics engine')

@section('content')

{{-- ── Summary Cards ─────────────────────────────────────────── --}}
<div class="grid grid-cols-4 gap-3 mb-5">
    <a href="{{ route('admin.risk.index') }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-gray-300 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center shrink-0">
            <i class="ti ti-users text-gray-500 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-gray-900 leading-none">{{ number_format($totalAssessed) }}</div>
            <div class="text-[11px] font-medium text-gray-400 mt-0.5">Total Assessed</div>
        </div>
    </a>
    <a href="{{ route('admin.risk.index', ['risk_level' => 'high']) }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-red-200 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center shrink-0">
            <i class="ti ti-alert-triangle text-red-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-red-700 leading-none">{{ number_format($highRiskCount) }}</div>
            <div class="text-[11px] font-medium text-gray-400 mt-0.5">High Risk</div>
        </div>
    </a>
    <a href="{{ route('admin.risk.index', ['risk_level' => 'moderate']) }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-amber-200 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">
            <i class="ti ti-alert-circle text-amber-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-amber-700 leading-none">{{ number_format($moderateRiskCount) }}</div>
            <div class="text-[11px] font-medium text-gray-400 mt-0.5">Moderate Risk</div>
        </div>
    </a>
    <a href="{{ route('admin.risk.index', ['risk_level' => 'low']) }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-green-200 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center shrink-0">
            <i class="ti ti-check text-green-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-green-700 leading-none">{{ number_format($lowRiskCount) }}</div>
            <div class="text-[11px] font-medium text-gray-400 mt-0.5">Low Risk</div>
        </div>
    </a>
</div>

{{-- ── Filters ───────────────────────────────────────────────── --}}
<div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-4 mb-4">
    <form method="GET" action="{{ route('admin.risk.index') }}" class="flex flex-wrap gap-3 items-end" id="filterForm">
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
        
        <div class="w-full lg:w-48">
            <label class="block text-xs font-medium text-gray-500 mb-1">Risk Level</label>
            <select name="risk_level" onchange="document.getElementById('filterForm').submit();" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                <option value="">All Levels</option>
                <option value="high" {{ request('risk_level') == 'high' ? 'selected' : '' }}>High Risk</option>
                <option value="moderate" {{ request('risk_level') == 'moderate' ? 'selected' : '' }}>Moderate Risk</option>
                <option value="low" {{ request('risk_level') == 'low' ? 'selected' : '' }}>Low Risk</option>
            </select>
        </div>
        
        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-1.5 h-[38px]">
                <i class="ti ti-filter"></i> Filter
            </button>
            <a href="{{ route('admin.risk.index') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5 h-[38px]">
                <i class="ti ti-x"></i> Clear
            </a>
        </div>
    </form>
</div>

{{-- ── Risk Assessment Table ─────────────────────────────────── --}}
<form action="{{ route('admin.risk.bulkAction') }}" method="POST" x-data="{ selected: [], selectAll: false, showAssignModal: false, assignCounselorId: '' }" class="relative">
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
        <div class="flex items-center gap-3">
            <button type="submit" name="action" value="export_selected" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-200 rounded-xl hover:bg-gray-200 transition flex items-center gap-2">
                <i class="ti ti-download text-gray-400"></i> Export
            </button>
            <button type="button" @click="showAssignModal = true" class="px-4 py-2 text-sm font-medium text-white bg-gray-900 rounded-xl hover:bg-gray-800 transition flex items-center gap-2">
                <i class="ti ti-user-plus text-gray-300"></i> Assign Counselor
            </button>
        </div>
    </div>

    <!-- Assign Counselor Modal (moved outside the floating bar: that bar has a
         permanent -translate-x-1/2 transform, and a `fixed` descendant of a
         transformed ancestor gets trapped in that ancestor's box instead of
         covering the viewport — this is why the modal used to render as a
         tiny clipped sliver instead of a full-screen overlay.) -->
    <div x-cloak x-show="showAssignModal" class="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto overflow-x-hidden bg-gray-900/50 backdrop-blur-sm p-4">
        <div @click.away="showAssignModal = false" class="relative w-full max-w-md bg-white rounded-2xl shadow-2xl p-6">
            <h3 class="text-lg font-bold text-gray-900 mb-4">Assign to Counselor</h3>
            <div class="mb-5">
                <label class="block text-xs font-semibold text-gray-600 uppercase tracking-wider mb-2">Select Counselor</label>
                <select x-model="assignCounselorId" name="assign_counselor_id" class="w-full rounded-xl border-gray-300 text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition">
                    <option value="">-- Select Counselor --</option>
                    @isset($counselors)
                        @foreach($counselors as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    @endisset
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3 w-full">
                <button type="button" @click="showAssignModal = false" class="w-full px-4 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition">Cancel</button>
                <button type="submit" name="action" value="assign_counselor" :disabled="!assignCounselorId" class="w-full px-4 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">Assign</button>
            </div>
        </div>
    </div>

<div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50/50 border-b border-gray-100">
                    <th class="px-5 py-3 w-10 text-center">
                        <input type="checkbox" x-model="selectAll" @change="selected = selectAll ? {{ json_encode($assessments->pluck('id')) }} : []" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 shadow-sm cursor-pointer">
                    </th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Student</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider text-center">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'risk_score', 'dir' => request('sort', 'risk_score') === 'risk_score' && request('dir', 'desc') === 'desc' ? 'asc' : 'desc']) }}" class="hover:text-blue-600 flex items-center justify-center gap-1 transition">
                            Risk Score
                            @if(request('sort', 'risk_score') === 'risk_score')
                                <i class="ti {{ request('dir', 'desc') === 'desc' ? 'ti-caret-down-filled' : 'ti-caret-up-filled' }} text-[10px]"></i>
                            @endif
                        </a>
                    </th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider text-center">Risk Level</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider text-center">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'previous_referrals_count', 'dir' => request('sort') === 'previous_referrals_count' && request('dir', 'desc') === 'desc' ? 'asc' : 'desc']) }}" class="hover:text-blue-600 flex items-center justify-center gap-1 transition">
                            Referrals
                            @if(request('sort') === 'previous_referrals_count')
                                <i class="ti {{ request('dir', 'desc') === 'desc' ? 'ti-caret-down-filled' : 'ti-caret-up-filled' }} text-[10px]"></i>
                            @endif
                        </a>
                    </th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider text-center">
                        <a href="{{ request()->fullUrlWithQuery(['sort' => 'behavioral_reports_count', 'dir' => request('sort') === 'behavioral_reports_count' && request('dir', 'desc') === 'desc' ? 'asc' : 'desc']) }}" class="hover:text-blue-600 flex items-center justify-center gap-1 transition">
                            Incidents
                            @if(request('sort') === 'behavioral_reports_count')
                                <i class="ti {{ request('dir', 'desc') === 'desc' ? 'ti-caret-down-filled' : 'ti-caret-up-filled' }} text-[10px]"></i>
                            @endif
                        </a>
                    </th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Factors</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Last Assessed</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                @forelse($assessments as $assessment)
                    <tr class="hover:bg-gray-50/50 transition">
                        <td class="px-5 py-3 text-center">
                            <input type="checkbox" name="assessment_ids[]" value="{{ $assessment->id }}" x-model="selected" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 shadow-sm cursor-pointer">
                        </td>
                        <td class="px-5 py-3">
                            <a href="{{ route('admin.risk.show', $assessment->student->id) }}" class="font-medium text-gray-900 hover:text-blue-600 transition block">{{ $assessment->student->last_name }}, {{ $assessment->student->first_name }}</a>
                            <div class="flex items-center gap-2 mt-0.5">
                                <p class="text-xs text-gray-500">{{ $assessment->student->student_id_number }}</p>
                                @if($assessment->student->referrals->count() > 0)
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-100 shrink-0" title="Active Referral/Action Taken">
                                        <i class="ti ti-shield-check"></i> Action Taken
                                    </span>
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-3 text-center">
                            <div class="flex items-center justify-center gap-1.5">
                                <span class="font-mono font-medium text-gray-900">{{ number_format($assessment->risk_score, 1) }}</span>
                                @if($assessment->previousAssessment)
                                    @php
                                        $diff = $assessment->risk_score - $assessment->previousAssessment->risk_score;
                                    @endphp
                                    @if($diff > 0)
                                        <div class="flex items-center text-red-500 bg-red-50 px-1 rounded" title="+{{ number_format($diff, 1) }} since last assessment">
                                            <i class="ti ti-trending-up text-[10px]"></i>
                                        </div>
                                    @elseif($diff < 0)
                                        <div class="flex items-center text-green-500 bg-green-50 px-1 rounded" title="{{ number_format($diff, 1) }} since last assessment">
                                            <i class="ti ti-trending-down text-[10px]"></i>
                                        </div>
                                    @else
                                        <div class="flex items-center text-gray-400 bg-gray-50 px-1 rounded" title="No change">
                                            <i class="ti ti-minus text-[10px]"></i>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        </td>
                        <td class="px-5 py-3 text-center">
                            @php
                                $levelClass = match($assessment->risk_level) {
                                    'high'     => 'bg-red-50 text-red-700',
                                    'moderate' => 'bg-amber-50 text-amber-700',
                                    'low'      => 'bg-green-50 text-green-700',
                                    default    => 'bg-gray-100 text-gray-500',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $levelClass }}">
                                {{ ucfirst($assessment->risk_level) }}
                            </span>
                        </td>
                        <td class="px-5 py-3 text-center text-gray-600">
                            {{ $assessment->previous_referrals_count }}
                        </td>
                        <td class="px-5 py-3 text-center text-gray-600">
                            {{ $assessment->behavioral_reports_count }}
                        </td>
                        <td class="px-5 py-3">
                            @php
                                $factors = is_array($assessment->risk_factors) ? $assessment->risk_factors : [];
                                $seminarTag = $factors['recommended_seminar_tag'] ?? null;
                                $tagLabel = $seminarTag ? ucwords(str_replace('_', ' ', $seminarTag)) : null;
                                $reason = $factors['reason'] ?? null;
                            @endphp
                            @if($tagLabel || $reason)
                                <div class="flex items-center gap-1.5">
                                    @if($tagLabel && strtolower($tagLabel) !== 'general')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-indigo-50 text-indigo-700 border border-indigo-100 text-[11px] font-medium whitespace-nowrap">
                                            <i class="ti ti-target-arrow"></i> {{ $tagLabel }}
                                        </span>
                                    @else
                                        <span class="text-xs text-gray-400">—</span>
                                    @endif

                                    @if($reason)
                                        <div class="relative flex items-center" x-data="{ showTooltip: false }">
                                            <button type="button" @mouseenter="showTooltip = true" @mouseleave="showTooltip = false" class="text-gray-400 hover:text-blue-500 transition">
                                                <i class="ti ti-info-circle"></i>
                                            </button>

                                            <!-- Tooltip Popover -->
                                            <div x-cloak x-show="showTooltip"
                                                 x-transition.opacity.duration.200ms
                                                 class="absolute z-50 w-64 p-3 mt-1 text-sm text-left bg-gray-900 text-white rounded-lg shadow-xl bottom-full mb-2 left-1/2 -translate-x-1/2 before:content-[''] before:absolute before:top-full before:left-1/2 before:-translate-x-1/2 before:border-4 before:border-transparent before:border-t-gray-900 pointer-events-none">
                                                <div class="font-medium text-gray-400 mb-1 text-[10px] uppercase tracking-wider">Referral Reason:</div>
                                                <p class="text-xs text-gray-200 leading-relaxed">{{ $reason }}</p>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @else
                                <span class="text-xs text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-500">
                            {{ $assessment->assessed_at->diffForHumans() }}
                        </td>
                        <td class="px-5 py-3 text-center">
                            <a href="{{ route('admin.risk.show', $assessment->student->id) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                Details
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-16 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center text-gray-300 mb-3">
                                    <i class="ti ti-shield-check text-2xl"></i>
                                </div>
                                <h3 class="text-base font-medium text-gray-900 mb-1">No At-Risk Students</h3>
                                <p class="text-sm text-gray-500">The predictive engine hasn't flagged any students matching your criteria.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($assessments->hasPages())
        <div class="px-5 py-4 bg-gray-50/50 border-t border-gray-100">
            {{ $assessments->links() }}
        </div>
    @endif
</div>
</form>

@endsection

@push('scripts')
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
    [x-cloak] { display: none !important; }
</style>
<script>
    let searchTimeout = null;
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
