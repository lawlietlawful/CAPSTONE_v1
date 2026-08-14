@extends('layouts.counselor')

@section('title', 'Intervention Details')
@section('page-title', 'Intervention Details')
@section('page-sub', 'Review notes and update the outcome of this session')

@section('content')

<div class="mb-6 flex items-center justify-between">
    <a href="{{ route('counselor.interventions.index') }}" class="text-sm font-medium text-gray-500 hover:text-blue-600 transition flex items-center gap-1">
        <i class="ti ti-arrow-left"></i> Back to Logs
    </a>
    <div class="flex gap-2">
        <a href="{{ route('counselor.interventions.edit', $intervention->id) }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition">
            <i class="ti ti-edit"></i> Edit Details
        </a>
        <form id="delete-intervention-{{ $intervention->id }}" action="{{ route('counselor.interventions.destroy', $intervention->id) }}" method="POST">
            @csrf @method('DELETE')
            <button type="button" @click="$dispatch('open-confirm-modal', { 
                    formId: 'delete-intervention-{{ $intervention->id }}', 
                    title: 'Delete Intervention Log', 
                    message: 'Are you sure you want to delete this intervention log? This action cannot be undone.',
                    confirmText: 'Yes, Delete Log'
                })" class="bg-red-50 text-red-600 hover:bg-red-100 px-4 py-2 rounded-xl text-sm font-semibold transition">
                <i class="ti ti-trash"></i> Delete Log
            </button>
        </form>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Left: Session Details --}}
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-notes text-blue-500"></i> Session Record
                </h3>
                <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($intervention->intervention_date)->format('l, F d, Y') }}</span>
            </div>
            
            <div class="p-6 space-y-6">
                <div>
                    <div class="text-xs text-gray-400 uppercase tracking-wide font-medium mb-1">Intervention Type</div>
                    <div class="font-medium text-gray-900 bg-blue-50 text-blue-700 px-3 py-1.5 rounded-lg inline-block text-sm border border-blue-100">
                        {{ $intervention->intervention_type }}
                    </div>
                </div>

                <div>
                    <div class="text-xs text-gray-400 uppercase tracking-wide font-medium mb-1">Session Notes & Description</div>
                    <div class="text-sm text-gray-700 bg-gray-50 p-4 rounded-xl leading-relaxed whitespace-pre-wrap border border-gray-100">{{ $intervention->description }}</div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-100">
                    <div>
                        <div class="text-xs text-gray-400 uppercase tracking-wide font-medium mb-1">Current Outcome</div>
                        @if($intervention->outcome === 'improving')
                            <div class="text-emerald-600 font-medium flex items-center gap-1.5"><i class="ti ti-trending-up"></i> Improving</div>
                        @elseif($intervention->outcome === 'worsening')
                            <div class="text-red-600 font-medium flex items-center gap-1.5"><i class="ti ti-trending-down"></i> Worsening</div>
                        @elseif($intervention->outcome === 'resolved')
                            <div class="text-indigo-600 font-medium flex items-center gap-1.5"><i class="ti ti-discount-check-filled"></i> Resolved</div>
                        @elseif($intervention->outcome === 'no_change')
                            <div class="text-amber-600 font-medium flex items-center gap-1.5"><i class="ti ti-minus"></i> No Change</div>
                        @else
                            <div class="text-gray-400 italic">Not evaluated</div>
                        @endif
                    </div>
                    
                    <div>
                        <div class="text-xs text-gray-400 uppercase tracking-wide font-medium mb-1">Follow-up Date</div>
                        @if($intervention->follow_up_date)
                            <div class="text-sm text-gray-900 font-medium flex items-center gap-2">
                                <i class="ti ti-calendar-due text-blue-500"></i>
                                {{ \Carbon\Carbon::parse($intervention->follow_up_date)->format('F d, Y') }}
                            </div>
                        @else
                            <div class="text-gray-400 italic text-sm">No follow-up scheduled</div>
                        @endif
                    </div>
                </div>

                @if($intervention->follow_up_notes)
                    <div>
                        <div class="text-xs text-gray-400 uppercase tracking-wide font-medium mb-1">Follow-up Requirements / Goals</div>
                        <div class="text-sm text-amber-900 bg-amber-50 p-3 rounded-lg border border-amber-100 whitespace-pre-wrap">{{ $intervention->follow_up_notes }}</div>
                    </div>
                @endif

            </div>
        </div>
    </div>

    {{-- Right: Student & Referral Context --}}
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-user text-blue-500"></i> Student Profile
                </h3>
            </div>
            <div class="p-5 text-center">
                <div class="w-16 h-16 bg-blue-100 text-blue-700 rounded-full flex items-center justify-center text-xl font-bold mx-auto mb-3">
                    {{ strtoupper(substr($intervention->referral->student->first_name ?? 'X', 0, 1)) }}
                </div>
                <h4 class="font-bold text-gray-900">{{ $intervention->referral->student->first_name ?? 'Unknown' }} {{ $intervention->referral->student->last_name ?? 'Student' }}</h4>
                <p class="text-xs text-gray-500 mt-1">ID: {{ $intervention->referral->student->student_id_number ?? 'N/A' }}</p>
                <div class="mt-4 pt-4 border-t border-gray-100 flex justify-center gap-4 text-sm">
                    <div class="text-center">
                        <div class="text-xs text-gray-400">Course</div>
                        <div class="font-medium text-gray-900">{{ $intervention->referral->student->course ?? 'N/A' }}</div>
                    </div>
                    <div class="text-center">
                        <div class="text-xs text-gray-400">Year</div>
                        <div class="font-medium text-gray-900">{{ $intervention->referral->student->year_level ?? 'N/A' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-file-text text-amber-500"></i> Linked Referral
                </h3>
            </div>
            <div class="p-5">
                <div class="text-xs text-gray-500 mb-1">Referral #{{ str_pad($intervention->referral_id, 4, '0', STR_PAD_LEFT) }}</div>
                <div class="font-medium text-gray-900 text-sm mb-2">{{ $intervention->referral->referral_type_label }}</div>
                <div class="text-xs text-gray-600 line-clamp-3 italic">"{{ $intervention->referral->display_reason }}"</div>
                
                <div class="mt-4 pt-4 border-t border-gray-100 text-center">
                    <a href="{{ route('counselor.referrals.show', $intervention->referral_id) }}" class="text-xs font-medium text-blue-600 hover:text-blue-800 transition">View Full Referral →</a>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection
