@extends('layouts.counselor')

@section('title', 'Referral Management')
@section('page-title', 'Referral Management')
@section('page-sub', 'Track student referrals, assign counselors, and monitor resolution progress')

@section('content')

<div x-data="{ activeModal: {!! $errors->any() ? "'create'" : 'null' !!} }">

{{-- ── Summary Cards ─────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-5">
    <a href="{{ route('counselor.referrals.index') }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-gray-300 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center shrink-0">
            <i class="ti ti-file-text text-gray-500 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-gray-900 leading-none">{{ number_format($totalCount) }}</div>
            <div class="text-[11px] font-medium text-gray-400 mt-0.5">Total Referrals</div>
        </div>
    </a>
    <a href="{{ route('counselor.referrals.index', ['status' => 'pending']) }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-blue-200 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
            <i class="ti ti-clock text-blue-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-blue-700 leading-none">{{ number_format($pendingCount) }}</div>
            <div class="text-[11px] font-medium text-gray-400 mt-0.5">Pending</div>
        </div>
    </a>
    <a href="{{ route('counselor.referrals.index', ['status' => 'in_progress']) }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-amber-200 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">
            <i class="ti ti-progress text-amber-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-amber-700 leading-none">{{ number_format($inProgressCount) }}</div>
            <div class="text-[11px] font-medium text-gray-400 mt-0.5">In Progress</div>
        </div>
    </a>
    <a href="{{ route('counselor.referrals.index', ['status' => 'resolved']) }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-green-200 transition block cursor-pointer">
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
    <form method="GET" action="{{ route('counselor.referrals.index') }}" class="flex flex-wrap items-end gap-3" id="filterForm">
        <div class="flex-1 min-w-[250px]">
            <label class="block text-xs font-medium text-gray-500 mb-1">Search Student</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i class="ti ti-search text-gray-400"></i>
                </div>
                <input type="text" id="searchInput" name="search" value="{{ request('search') }}" placeholder="Name or Student ID..."
                    class="block w-full pl-10 pr-3 py-2 border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition">
            </div>
        </div>
        <div class="w-full lg:w-40">
            <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
            <select name="status" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                <option value="">All Statuses</option>
                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                <option value="resolved" {{ request('status') == 'resolved' ? 'selected' : '' }}>Resolved</option>
                <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
        </div>
        <div class="w-full lg:w-40">
            <label class="block text-xs font-medium text-gray-500 mb-1">Priority</label>
            <select name="priority" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                <option value="">All Priorities</option>
                <option value="low" {{ request('priority') == 'low' ? 'selected' : '' }}>Low</option>
                <option value="moderate" {{ request('priority') == 'moderate' ? 'selected' : '' }}>Moderate</option>
                <option value="high" {{ request('priority') == 'high' ? 'selected' : '' }}>High</option>
            </select>
        </div>
        <div class="flex gap-2 w-full lg:w-auto mt-3 lg:mt-0">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                <i class="ti ti-filter"></i> Filter
            </button>
            <a href="{{ route('counselor.referrals.index') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                <i class="ti ti-x"></i> Clear
            </a>
            <button type="button" @click="activeModal = 'create'" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                <i class="ti ti-plus"></i> New Referral
            </button>
        </div>
    </form>
</div>

{{-- ── Referrals Table ───────────────────────────────────────── --}}
<div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
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
                            <a href="{{ route('counselor.referrals.show', $referral->id) }}" class="font-medium text-gray-900 hover:text-blue-600 hover:underline transition block">
                                {{ $referral->student->last_name }}, {{ $referral->student->first_name }}
                            </a>
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
                            <a href="{{ route('counselor.referrals.show', $referral->id) }}" class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-blue-50 text-blue-600 hover:bg-blue-100 transition" title="View Referral">
                                <i class="ti ti-eye"></i>
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
                                @if(request()->anyFilled(['search', 'status', 'priority']))
                                    <h3 class="text-base font-medium text-gray-900 mb-1">No Matching Referrals</h3>
                                    <p class="text-sm text-gray-500 mb-4">No referrals match your current search or filters.</p>
                                    <a href="{{ route('counselor.referrals.index') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 transition">
                                        Clear Filters
                                    </a>
                                @else
                                    <h3 class="text-base font-medium text-gray-900 mb-1">No Referrals Found</h3>
                                    <p class="text-sm text-gray-500 mb-4">Referrals will appear here once teachers or counselors submit them.</p>
                                    <a href="{{ route('counselor.referrals.create') }}" class="px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition shadow-sm">
                                        Create Referral
                                    </a>
                                @endif
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

{{-- New Referral Modal --}}
<div x-cloak x-show="activeModal === 'create'" x-data="{ referralType: '{{ old('referral_type') }}' }">
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
                <form action="{{ route('counselor.referrals.store') }}" method="POST">
                    @csrf
                    <div class="space-y-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Student <span class="text-red-500">*</span></label>
                            <select name="student_id" required class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('student_id') border-red-500 @enderror">
                                <option value="">Select a student...</option>
                                @foreach($students as $s)
                                    <option value="{{ $s->id }}" {{ old('student_id') == $s->id ? 'selected' : '' }}>{{ $s->last_name }}, {{ $s->first_name }} ({{ $s->student_id_number }})</option>
                                @endforeach
                            </select>
                            @error('student_id') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Referral Type <span class="text-red-500">*</span></label>
                            <select name="referral_type" required x-model="referralType" class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('referral_type') border-red-500 @enderror">
                                <option value="">Select type...</option>
                                @foreach(\App\Models\Referral::REFERRAL_TYPES as $type)
                                    <option value="{{ $type }}">{{ $type }}</option>
                                @endforeach
                            </select>
                            @error('referral_type') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            <div x-show="referralType === 'Other'" x-cloak class="mt-2">
                                <input type="text" name="referral_type_other" value="{{ old('referral_type_other') }}" placeholder="Please specify"
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('referral_type_other') border-red-500 @enderror">
                                @error('referral_type_other') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Priority is set automatically by the AI risk assessment once submitted.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Assign to Counselor</label>
                            <select name="counselor_id" class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('counselor_id') border-red-500 @enderror">
                                <option value="">Unassigned (Counselor will pick up)</option>
                                @foreach($counselors as $c)
                                    <option value="{{ $c->id }}" {{ old('counselor_id') == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                                @endforeach
                            </select>
                            @error('counselor_id') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Reason for Referral <span class="text-red-500">*</span></label>
                            <textarea name="reason" rows="3" required placeholder="Describe the concern or reason for referring this student..." class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('reason') border-red-500 @enderror">{{ old('reason') }}</textarea>
                            @error('reason') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
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

</div> {{-- Close Alpine Wrapper --}}

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

@endsection
