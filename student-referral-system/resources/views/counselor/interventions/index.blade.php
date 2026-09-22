@extends('layouts.counselor')

@section('title', 'Intervention Logs')
@section('page-title', 'Intervention Logs')
@section('page-sub', 'Track counseling sessions, disciplinary actions, and outcomes')

@section('content')

<div x-data="{ activeModal: {!! $errors->any() ? "'create'" : 'null' !!} }">

{{-- ── Summary Stats ─────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-5">
    <a href="{{ route('counselor.interventions.index') }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-gray-300 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
            <i class="ti ti-notebook text-blue-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-gray-900 leading-none">{{ number_format($totalInterventions) }}</div>
            <div class="text-[11px] font-medium text-gray-400 mt-0.5">Total Interventions</div>
        </div>
    </a>

    <a href="{{ route('counselor.interventions.index', ['outcome' => 'improving']) }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-emerald-200 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
            <i class="ti ti-trending-up text-emerald-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-emerald-700 leading-none">{{ number_format($improvingCount) }}</div>
            <div class="text-[11px] font-medium text-gray-400 mt-0.5">Improving Cases</div>
        </div>
    </a>

    <a href="{{ route('counselor.interventions.index', ['outcome' => 'resolved']) }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-indigo-200 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0">
            <i class="ti ti-discount-check-filled text-indigo-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-indigo-700 leading-none">{{ number_format($resolvedCount) }}</div>
            <div class="text-[11px] font-medium text-gray-400 mt-0.5">Resolved Cases</div>
        </div>
    </a>

    <a href="{{ route('counselor.interventions.index', ['overdue' => 1]) }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-red-200 transition block cursor-pointer {{ request()->boolean('overdue') ? 'ring-2 ring-red-200' : '' }}">
        <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center shrink-0">
            <i class="ti ti-calendar-exclamation text-red-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold {{ $overdueCount > 0 ? 'text-red-600' : 'text-gray-900' }} leading-none">{{ number_format($overdueCount) }}</div>
            <div class="text-[11px] font-medium text-gray-400 mt-0.5">Overdue Follow-ups</div>
        </div>
    </a>
</div>

{{-- ── Filters & Search ──────────────────────────────────────── --}}
<div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-4 mb-4">
    <form action="{{ route('counselor.interventions.index') }}" method="GET" class="flex flex-wrap items-end gap-3" id="filterForm">
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
        <div class="w-full lg:w-48">
            <label class="block text-xs font-medium text-gray-500 mb-1">Outcome</label>
            <select name="outcome" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                <option value="">All Outcomes</option>
                <option value="improving" {{ request('outcome') == 'improving' ? 'selected' : '' }}>Improving</option>
                <option value="no_change" {{ request('outcome') == 'no_change' ? 'selected' : '' }}>No Change</option>
                <option value="worsening" {{ request('outcome') == 'worsening' ? 'selected' : '' }}>Worsening</option>
                <option value="resolved"  {{ request('outcome') == 'resolved' ? 'selected' : '' }}>Resolved</option>
            </select>
        </div>
        <div class="w-full lg:w-56">
            <label class="block text-xs font-medium text-gray-500 mb-1">Intervention Type</label>
            <select name="intervention_type" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                <option value="">All Types</option>
                @foreach($interventionTypes as $type)
                    <option value="{{ $type }}" {{ request('intervention_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                @endforeach
            </select>
        </div>
        <div class="w-full lg:w-44">
            <label class="block text-xs font-medium text-gray-500 mb-1">Date Range</label>
            <select name="date_range" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                <option value="">Any Time</option>
                <option value="today" {{ request('date_range') == 'today' ? 'selected' : '' }}>Today</option>
                <option value="this_week" {{ request('date_range') == 'this_week' ? 'selected' : '' }}>This Week</option>
                <option value="this_month" {{ request('date_range') == 'this_month' ? 'selected' : '' }}>This Month</option>
                <option value="last_month" {{ request('date_range') == 'last_month' ? 'selected' : '' }}>Last Month</option>
            </select>
        </div>
        <div class="flex gap-2 w-full lg:w-auto mt-3 lg:mt-0">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                <i class="ti ti-filter"></i> Filter
            </button>
            @if(request()->hasAny(['search', 'outcome', 'overdue', 'intervention_type', 'date_range']))
                <a href="{{ route('counselor.interventions.index') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                    <i class="ti ti-x"></i> Clear
                </a>
            @endif
            <a href="{{ route('counselor.interventions.export', request()->query()) }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                <i class="ti ti-download"></i> Export
            </a>
            <a href="{{ route('counselor.interventions.followups') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                <i class="ti ti-calendar-event"></i> Follow-up Agenda
            </a>
            <button type="button" @click="activeModal = 'create'" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                <i class="ti ti-plus"></i> Log New Intervention
            </button>
        </div>
    </form>
</div>

{{-- ── Intervention Logs Table ──────────────────────────────────── --}}
<form method="GET" action="{{ route('counselor.interventions.export') }}" x-data="{ selected: [], selectAll: false }">
    {{-- Floating bulk action bar --}}
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
        <button type="submit" class="text-sm px-3.5 py-1.5 rounded-lg transition font-medium bg-gray-100 text-gray-700 border border-gray-200 hover:bg-gray-200 flex items-center gap-1.5">
            <i class="ti ti-download text-gray-400"></i> Export Selected
        </button>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="text-xs text-gray-500 uppercase bg-gray-50/50">
                <tr>
                    <th class="px-5 py-4 w-10 text-center">
                        <input type="checkbox" x-model="selectAll" @change="selected = selectAll ? {{ json_encode($interventions->pluck('id')) }} : []" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 shadow-sm cursor-pointer">
                    </th>
                    <th class="px-6 py-4 font-medium">Date</th>
                    <th class="px-6 py-4 font-medium">Student</th>
                    <th class="px-6 py-4 font-medium">Intervention Type</th>
                    <th class="px-6 py-4 font-medium">Outcome</th>
                    <th class="px-6 py-4 font-medium">Follow-up</th>
                    <th class="px-6 py-4 font-medium text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($interventions as $intervention)
                    <tr class="hover:bg-gray-50/50 transition">
                        <td class="px-5 py-4 text-center">
                            <input type="checkbox" name="ids[]" value="{{ $intervention->id }}" x-model="selected" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 shadow-sm cursor-pointer">
                        </td>
                        <td class="px-6 py-4">
                            <span class="font-medium text-gray-900">{{ \Carbon\Carbon::parse($intervention->intervention_date)->format('M d, Y') }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">
                                @if($intervention->referral->student)
                                    <a href="{{ route('counselor.interventions.show', $intervention->id) }}" class="hover:text-blue-600 hover:underline transition">
                                        {{ $intervention->referral->student->first_name }} {{ $intervention->referral->student->last_name }}
                                    </a>
                                @else
                                    N/A
                                @endif
                            </div>
                            <div class="text-xs text-gray-500">Ref #{{ str_pad($intervention->referral_id, 4, '0', STR_PAD_LEFT) }}</div>
                        </td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                                {{ $intervention->intervention_type }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            @if($intervention->outcome === 'improving')
                                <span class="text-emerald-600 font-medium flex items-center gap-1.5"><i class="ti ti-trending-up"></i> Improving</span>
                            @elseif($intervention->outcome === 'worsening')
                                <span class="text-red-600 font-medium flex items-center gap-1.5"><i class="ti ti-trending-down"></i> Worsening</span>
                            @elseif($intervention->outcome === 'resolved')
                                <span class="text-indigo-600 font-medium flex items-center gap-1.5"><i class="ti ti-discount-check-filled"></i> Resolved</span>
                            @elseif($intervention->outcome === 'no_change')
                                <span class="text-amber-600 font-medium flex items-center gap-1.5"><i class="ti ti-minus"></i> No Change</span>
                            @else
                                <span class="text-gray-400 italic">Not evaluated</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($intervention->follow_up_date)
                                @if($intervention->is_follow_up_overdue)
                                    <div class="text-xs font-semibold text-red-600 flex items-center gap-1">
                                        <i class="ti ti-alert-triangle"></i>
                                        {{ \Carbon\Carbon::parse($intervention->follow_up_date)->format('M d, Y') }} (Overdue)
                                    </div>
                                @else
                                    <div class="text-xs font-medium text-gray-700">
                                        {{ \Carbon\Carbon::parse($intervention->follow_up_date)->format('M d, Y') }}
                                    </div>
                                @endif
                            @else
                                <span class="text-gray-400 italic text-xs">None</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('counselor.interventions.show', $intervention->id) }}" class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-blue-50 text-blue-600 hover:bg-blue-100 transition" title="View Details">
                                <i class="ti ti-eye"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <div class="flex flex-col items-center justify-center">
                                <i class="ti ti-notes text-4xl text-gray-300 mb-3"></i>
                                @if(request()->hasAny(['search', 'outcome', 'overdue', 'intervention_type', 'date_range']))
                                    <p class="text-sm">No interventions match your current search or filters.</p>
                                    <a href="{{ route('counselor.interventions.index') }}" class="text-blue-600 font-medium text-sm mt-2">Clear Filters</a>
                                @else
                                    <p class="text-sm">No interventions logged yet.</p>
                                    <a href="{{ route('counselor.interventions.create') }}" class="text-blue-600 font-medium text-sm mt-2">Log the first one</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($interventions->hasPages())
        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
            {{ $interventions->links() }}
        </div>
    @endif
    </div>
</form>

{{-- Log New Intervention Modal --}}
<div x-cloak x-show="activeModal === 'create'">
    <div class="fixed inset-0 z-[100] overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" @click="activeModal = null"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
            <div class="inline-block w-full max-w-2xl p-6 text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl sm:p-8 relative z-[101]">
                <div class="flex justify-between items-center mb-5 border-b border-gray-100 pb-4">
                    <h3 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
                        <i class="ti ti-heart-handshake text-blue-500"></i> Log New Intervention
                    </h3>
                    <button type="button" @click="activeModal = null" class="text-gray-400 hover:text-gray-600 transition">
                        <i class="ti ti-x text-xl"></i>
                    </button>
                </div>
                <form action="{{ route('counselor.interventions.store') }}" method="POST">
                    @csrf
                    <div class="space-y-5">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Target Student / Referral <span class="text-red-500">*</span></label>
                            <select name="referral_id" required class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('referral_id') border-red-500 @enderror">
                                <option value="" disabled {{ old('referral_id') ? '' : 'selected' }}>Select a pending or active referral...</option>
                                @foreach($referrals as $ref)
                                    <option value="{{ $ref->id }}" {{ old('referral_id') == $ref->id ? 'selected' : '' }}>
                                        {{ $ref->student->first_name }} {{ $ref->student->last_name }} (Ref #{{ str_pad($ref->id, 4, '0', STR_PAD_LEFT) }} - {{ $ref->referral_type_label }})
                                    </option>
                                @endforeach
                            </select>
                            @error('referral_id') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Intervention Type <span class="text-red-500">*</span></label>
                                <select name="intervention_type" required class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('intervention_type') border-red-500 @enderror">
                                    <option value="" disabled {{ old('intervention_type') ? '' : 'selected' }}>Select type...</option>
                                    @foreach($interventionTypes as $type)
                                        <option value="{{ $type }}" {{ old('intervention_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                                    @endforeach
                                </select>
                                @error('intervention_type') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Date of Intervention <span class="text-red-500">*</span></label>
                                <input type="date" name="intervention_date" required value="{{ old('intervention_date', date('Y-m-d')) }}"
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('intervention_date') border-red-500 @enderror">
                                @error('intervention_date') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Session Notes / Description <span class="text-red-500">*</span></label>
                            <textarea name="description" rows="3" required placeholder="Describe what was discussed or action taken..."
                                class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('description') border-red-500 @enderror">{{ old('description') }}</textarea>
                            @error('description') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>
                        <div class="grid grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Current Outcome</label>
                                <select name="outcome" class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                    <option value="" {{ old('outcome') ? '' : 'selected' }}>-- Not yet evaluated --</option>
                                    <option value="improving" {{ old('outcome') == 'improving' ? 'selected' : '' }}>Improving</option>
                                    <option value="no_change" {{ old('outcome') == 'no_change' ? 'selected' : '' }}>No Change</option>
                                    <option value="worsening" {{ old('outcome') == 'worsening' ? 'selected' : '' }}>Worsening</option>
                                    <option value="resolved" {{ old('outcome') == 'resolved' ? 'selected' : '' }}>Resolved (Closes Referral)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Scheduled Follow-up Date</label>
                                <input type="date" name="follow_up_date" value="{{ old('follow_up_date') }}"
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('follow_up_date') border-red-500 @enderror">
                                @error('follow_up_date') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Follow-up Requirements / Goals</label>
                            <textarea name="follow_up_notes" rows="2" placeholder="Goals set for the student before next session..."
                                class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">{{ old('follow_up_notes') }}</textarea>
                        </div>
                    </div>
                    <div class="mt-8 flex justify-end gap-3 pt-5 border-t border-gray-100">
                        <button type="button" @click="activeModal = null" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">Cancel</button>
                        <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-2"><i class="ti ti-send"></i> Save Intervention</button>
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
