@extends('layouts.counselor')

@section('title', 'Follow-up Agenda')
@section('page-title', 'Follow-up Agenda')
@section('page-sub', 'Scheduled follow-ups, grouped by when they need attention')

@section('content')

<div class="mb-6">
    <a href="{{ route('counselor.interventions.index') }}" class="text-sm font-medium text-gray-500 hover:text-blue-600 transition flex items-center gap-1 w-fit">
        <i class="ti ti-arrow-left"></i> Back to Logs
    </a>
</div>

<div class="space-y-6">
    {{-- Overdue --}}
    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-red-50/50 flex items-center justify-between">
            <h3 class="font-semibold text-red-700 flex items-center gap-2">
                <i class="ti ti-alert-triangle"></i> Overdue ({{ $overdue->total() }})
            </h3>
        </div>
        @if($overdue->isEmpty())
            <div class="px-6 py-8 text-center text-sm text-gray-400">Nothing overdue — you're caught up.</div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($overdue as $intervention)
                    <a href="{{ route('counselor.interventions.show', $intervention->id) }}" class="flex items-center justify-between gap-4 px-6 py-3 hover:bg-gray-50/50 transition">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 text-sm">{{ $intervention->referral->student->first_name ?? 'Unknown' }} {{ $intervention->referral->student->last_name ?? '' }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-50 text-blue-700 border border-blue-100">{{ $intervention->intervention_type }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">Ref #{{ str_pad($intervention->referral_id, 4, '0', STR_PAD_LEFT) }}</p>
                        </div>
                        <span class="text-xs font-semibold text-red-600 whitespace-nowrap">{{ \Carbon\Carbon::parse($intervention->follow_up_date)->format('M d, Y') }}</span>
                    </a>
                @endforeach
            </div>
            @if($overdue->hasPages())
                <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
                    {{ $overdue->links() }}
                </div>
            @endif
        @endif
    </div>

    {{-- Due Today --}}
    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-amber-50/50 flex items-center justify-between">
            <h3 class="font-semibold text-amber-700 flex items-center gap-2">
                <i class="ti ti-calendar-event"></i> Due Today ({{ $dueToday->count() }})
            </h3>
        </div>
        @if($dueToday->isEmpty())
            <div class="px-6 py-8 text-center text-sm text-gray-400">No follow-ups due today.</div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($dueToday as $intervention)
                    <a href="{{ route('counselor.interventions.show', $intervention->id) }}" class="flex items-center justify-between gap-4 px-6 py-3 hover:bg-gray-50/50 transition">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 text-sm">{{ $intervention->referral->student->first_name ?? 'Unknown' }} {{ $intervention->referral->student->last_name ?? '' }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-50 text-blue-700 border border-blue-100">{{ $intervention->intervention_type }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">Ref #{{ str_pad($intervention->referral_id, 4, '0', STR_PAD_LEFT) }}</p>
                        </div>
                        <span class="text-xs font-semibold text-amber-600 whitespace-nowrap">Today</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    {{-- Upcoming --}}
    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
            <h3 class="font-semibold text-gray-700 flex items-center gap-2">
                <i class="ti ti-calendar-due"></i> Upcoming — Next 30 Days ({{ $upcoming->count() }})
            </h3>
        </div>
        @if($upcoming->isEmpty())
            <div class="px-6 py-8 text-center text-sm text-gray-400">Nothing scheduled in the next 30 days.</div>
        @else
            <div class="divide-y divide-gray-100">
                @foreach($upcoming as $intervention)
                    <a href="{{ route('counselor.interventions.show', $intervention->id) }}" class="flex items-center justify-between gap-4 px-6 py-3 hover:bg-gray-50/50 transition">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-900 text-sm">{{ $intervention->referral->student->first_name ?? 'Unknown' }} {{ $intervention->referral->student->last_name ?? '' }}</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-50 text-blue-700 border border-blue-100">{{ $intervention->intervention_type }}</span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">Ref #{{ str_pad($intervention->referral_id, 4, '0', STR_PAD_LEFT) }}</p>
                        </div>
                        <span class="text-xs font-medium text-gray-500 whitespace-nowrap">{{ \Carbon\Carbon::parse($intervention->follow_up_date)->format('M d, Y') }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>

@endsection
