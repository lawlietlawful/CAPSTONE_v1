@extends('layouts.counselor')

@section('title', 'Intervention Logs')
@section('page-title', 'Intervention Logs')
@section('page-sub', 'Track counseling sessions, disciplinary actions, and outcomes')

@section('content')

{{-- ── Summary Stats ─────────────────────────────────────────── --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center text-2xl">
            <i class="ti ti-notebook"></i>
        </div>
        <div>
            <div class="text-sm font-medium text-gray-500">Total Interventions</div>
            <div class="text-2xl font-bold text-gray-900">{{ number_format($totalInterventions) }}</div>
        </div>
    </div>
    
    <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl">
            <i class="ti ti-trending-up"></i>
        </div>
        <div>
            <div class="text-sm font-medium text-gray-500">Improving Cases</div>
            <div class="text-2xl font-bold text-gray-900">{{ number_format($improvingCount) }}</div>
        </div>
    </div>
    
    <div class="bg-white border border-gray-100 rounded-xl p-5 shadow-sm flex items-center gap-4">
        <div class="w-12 h-12 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl">
            <i class="ti ti-discount-check-filled"></i>
        </div>
        <div>
            <div class="text-sm font-medium text-gray-500">Resolved Cases</div>
            <div class="text-2xl font-bold text-gray-900">{{ number_format($resolvedCount) }}</div>
        </div>
    </div>
</div>

{{-- ── Filters & Search ──────────────────────────────────────── --}}
<div class="bg-white border border-gray-100 rounded-2xl shadow-sm mb-6">
    <div class="p-4 border-b border-gray-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
        
        <form action="{{ route('counselor.interventions.index') }}" method="GET" class="flex flex-col md:flex-row gap-3 w-full md:w-auto">
            
            <div class="relative">
                <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Search student..."
                       class="pl-9 pr-4 py-2 w-full md:w-64 rounded-lg border-gray-200 focus:border-blue-500 focus:ring focus:ring-blue-200 text-sm">
            </div>

            <select name="outcome" class="py-2 px-3 rounded-lg border-gray-200 focus:border-blue-500 focus:ring focus:ring-blue-200 text-sm">
                <option value="">All Outcomes</option>
                <option value="improving" {{ request('outcome') == 'improving' ? 'selected' : '' }}>Improving</option>
                <option value="no_change" {{ request('outcome') == 'no_change' ? 'selected' : '' }}>No Change</option>
                <option value="worsening" {{ request('outcome') == 'worsening' ? 'selected' : '' }}>Worsening</option>
                <option value="resolved"  {{ request('outcome') == 'resolved' ? 'selected' : '' }}>Resolved</option>
            </select>

            <button type="submit" class="px-4 py-2 bg-gray-100 text-gray-700 hover:bg-gray-200 rounded-lg text-sm font-medium transition">
                Filter
            </button>
            @if(request()->hasAny(['search', 'outcome']))
                <a href="{{ route('counselor.interventions.index') }}" class="px-4 py-2 text-red-600 bg-red-50 hover:bg-red-100 rounded-lg text-sm font-medium transition">
                    Clear
                </a>
            @endif
        </form>

        <a href="{{ route('counselor.interventions.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition flex items-center gap-2 shadow-sm">
            <i class="ti ti-plus"></i> Log New Intervention
        </a>
    </div>

    {{-- ── Data Table ────────────────────────────────────────── --}}
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="text-xs text-gray-500 uppercase bg-gray-50/50">
                <tr>
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
                        <td class="px-6 py-4">
                            <span class="font-medium text-gray-900">{{ \Carbon\Carbon::parse($intervention->intervention_date)->format('M d, Y') }}</span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="font-medium text-gray-900">
                                {{ $intervention->referral->student->first_name ?? 'N/A' }} {{ $intervention->referral->student->last_name ?? '' }}
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
                                <div class="text-xs font-medium text-gray-700">
                                    {{ \Carbon\Carbon::parse($intervention->follow_up_date)->format('M d, Y') }}
                                </div>
                            @else
                                <span class="text-gray-400 italic text-xs">None</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <a href="{{ route('counselor.interventions.show', $intervention->id) }}" class="text-blue-600 hover:text-blue-800 font-medium text-xs">View Details</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <div class="flex flex-col items-center justify-center">
                                <i class="ti ti-notes text-4xl text-gray-300 mb-3"></i>
                                <p class="text-sm">No interventions logged yet.</p>
                                <a href="{{ route('counselor.interventions.create') }}" class="text-blue-600 font-medium text-sm mt-2">Log the first one</a>
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

@endsection
