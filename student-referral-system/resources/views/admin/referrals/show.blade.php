@extends('layouts.admin')

@section('title', 'Referral Details')
@section('page-title', 'Referral Details')
@section('page-sub', 'View referral information and manage its status')

@section('content')

<div class="mb-6">
    <a href="{{ route('admin.referrals.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 flex items-center gap-1">
        <i class="ti ti-arrow-left"></i> Back to Referrals
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column: Referral Details -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Main Info Card -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden transition-all duration-300 hover:shadow-hover">
            <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 text-lg flex items-center gap-2">
                    <i class="ti ti-file-text text-blue-600"></i> Referral #{{ $referral->id }}
                </h3>
                <div class="flex items-center gap-2">
                    @php
                        $priorityClass = match($referral->priority) {
                            'high'     => 'bg-red-50 text-red-700 border-red-200',
                            'moderate' => 'bg-amber-50 text-amber-700 border-amber-200',
                            default    => 'bg-green-50 text-green-700 border-green-200',
                        };
                        $statusClass = match($referral->status) {
                            'pending'     => 'bg-blue-50 text-blue-700 border-blue-200',
                            'in_progress' => 'bg-amber-50 text-amber-700 border-amber-200',
                            'resolved'    => 'bg-green-50 text-green-700 border-green-200',
                            'cancelled'   => 'bg-gray-100 text-gray-500 border-gray-200',
                            default       => 'bg-gray-100 text-gray-500 border-gray-200',
                        };
                        $statusLabel = match($referral->status) {
                            'pending'     => 'Pending',
                            'in_progress' => 'In Progress',
                            'resolved'    => 'Resolved',
                            'cancelled'   => 'Cancelled',
                            default       => ucfirst($referral->status),
                        };
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border {{ $priorityClass }}">
                        {{ ucfirst($referral->priority) }} Priority
                    </span>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border {{ $statusClass }}">
                        {{ $statusLabel }}
                    </span>
                </div>
            </div>

            <div class="p-6 space-y-5">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <span class="block text-xs text-gray-400 uppercase tracking-wider mb-0.5">Referral Type</span>
                        <span class="font-medium text-gray-900">{{ $referral->referral_type_label }}</span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-400 uppercase tracking-wider mb-0.5">Date Filed</span>
                        <span class="font-medium text-gray-900">{{ $referral->created_at->format('F j, Y — h:i A') }}</span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-400 uppercase tracking-wider mb-0.5">Referred By</span>
                        <span class="font-medium text-gray-900">{{ $referral->referredBy->name ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-400 uppercase tracking-wider mb-0.5">Assigned Counselor</span>
                        <span class="font-medium text-gray-900">{{ $referral->counselor->name ?? 'Unassigned' }}</span>
                    </div>
                </div>

                @if($referral->behavioralReport)
                    <div class="pt-4 border-t border-gray-100">
                        <a href="{{ route('admin.behavioral-reports.show', $referral->behavioralReport->id) }}"
                            class="flex items-center gap-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 hover:bg-amber-100 transition">
                            <i class="ti ti-arrow-up-right-circle text-lg"></i>
                            <span>Auto-escalated from <span class="font-semibold">Behavioral Report #{{ $referral->behavioralReport->id }}</span> ({{ $referral->behavioralReport->incident_type }}) — click to view the original report</span>
                        </a>
                    </div>
                @endif

                <div class="pt-4 border-t border-gray-100">
                    <span class="block text-xs text-gray-400 uppercase tracking-wider mb-1.5">Reason for Referral</span>
                    <p class="text-sm text-gray-700 leading-relaxed bg-gray-50 rounded-lg p-4 border border-gray-100">{{ $referral->display_reason }}</p>
                </div>

                @if($referral->counselor_notes)
                    <div class="pt-4 border-t border-gray-100">
                        <span class="block text-xs text-gray-400 uppercase tracking-wider mb-1.5">Counselor Notes</span>
                        <p class="text-sm text-gray-700 leading-relaxed bg-blue-50 rounded-lg p-4 border border-blue-100">{{ $referral->counselor_notes }}</p>
                    </div>
                @endif

                @if($referral->resolved_at)
                    <div class="pt-4 border-t border-gray-100">
                        <span class="block text-xs text-gray-400 uppercase tracking-wider mb-0.5">Resolved On</span>
                        <span class="font-medium text-green-700">{{ $referral->resolved_at->format('F j, Y — h:i A') }}</span>
                    </div>
                @endif
            </div>
        </div>

        <!-- Related Interventions -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden transition-all duration-300 hover:shadow-hover">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-list-check text-amber-600"></i> Interventions
                </h4>
            </div>
            <div class="p-6">
                @if($referral->interventions && $referral->interventions->count() > 0)
                    <div class="relative border-l-2 border-gray-100 ml-3 space-y-6">
                        @foreach($referral->interventions as $intervention)
                            <div class="relative pl-6">
                                <div class="absolute w-3 h-3 bg-white border-2 border-amber-500 rounded-full -left-[7px] top-1 ring-4 ring-white"></div>
                                <p class="text-sm font-semibold text-gray-900">{{ $intervention->type }}</p>
                                <p class="text-xs text-gray-400 mt-0.5"><i class="ti ti-calendar text-[10px]"></i> {{ $intervention->created_at->format('M d, Y') }}</p>
                                <p class="text-sm text-gray-600 mt-2 bg-gray-50 rounded-lg p-3 border border-gray-100">{{ $intervention->description }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex flex-col items-center justify-center py-6 text-gray-400">
                        <i class="ti ti-timeline-event-x text-3xl mb-2 text-gray-300"></i>
                        <p class="text-sm text-center">No interventions logged for this referral yet.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Related SMS Logs -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden transition-all duration-300 hover:shadow-hover">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-message text-green-600"></i> SMS Notifications
                </h4>
            </div>
            <div class="p-6">
                @if($referral->smsLogs && $referral->smsLogs->count() > 0)
                    <div class="space-y-3">
                        @foreach($referral->smsLogs as $sms)
                            <div class="p-3 rounded-lg border {{ $sms->status === 'sent' ? 'bg-green-50 border-green-100' : 'bg-red-50 border-red-100' }}">
                                <div class="flex justify-between items-start">
                                    <p class="text-sm font-medium {{ $sms->status === 'sent' ? 'text-green-900' : 'text-red-900' }}">
                                        {{ $sms->recipient_name }} ({{ $sms->recipient_number }})
                                    </p>
                                    <span class="text-[11px] font-medium {{ $sms->status === 'sent' ? 'text-green-600' : 'text-red-600' }}">{{ ucfirst($sms->status) }}</span>
                                </div>
                                <p class="text-xs text-gray-600 mt-1 line-clamp-2">{{ $sms->message }}</p>
                                <p class="text-xs text-gray-400 mt-1">{{ $sms->created_at->format('M d, Y h:i A') }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400 text-center py-4">No SMS notifications linked to this referral.</p>
                @endif
            </div>
        </div>
    </div>

    <!-- Right Column: Student Info + Status Update -->
    <div class="lg:col-span-1 space-y-6">

        <!-- Student Information -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6">
            <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="ti ti-user text-blue-600"></i> Student Information
            </h4>
            <div class="space-y-5 text-sm">
                <div>
                    <span class="block text-xs text-gray-400 uppercase tracking-wider">Full Name</span>
                    <span class="font-medium text-gray-900">{{ $referral->student->last_name }}, {{ $referral->student->first_name }} {{ $referral->student->middle_name }}</span>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                        <i class="ti ti-id"></i>
                    </div>
                    <div>
                        <span class="block text-[11px] text-gray-400 uppercase tracking-wider font-semibold">Student ID</span>
                        <span class="font-medium text-gray-900">{{ $referral->student->student_id_number }}</span>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                        <i class="ti ti-book"></i>
                    </div>
                    <div>
                        <span class="block text-[11px] text-gray-400 uppercase tracking-wider font-semibold">Course & Year</span>
                        <span class="font-medium text-gray-900">{{ $referral->student->course ?? $referral->student->grade_level }} — {{ $referral->student->section }}</span>
                    </div>
                </div>
                <div class="flex items-start gap-3">
                    <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                        <i class="ti ti-phone"></i>
                    </div>
                    <div>
                        <span class="block text-[11px] text-gray-400 uppercase tracking-wider font-semibold">Parent / Guardian</span>
                        <span class="font-medium text-gray-900">{{ $referral->student->parent_name }}</span>
                        <span class="block text-xs text-gray-500 mt-0.5">{{ $referral->student->parent_contact }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Update Status Form -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden transition-all duration-300 hover:shadow-hover">
            <div class="bg-gray-50/50 px-6 py-5 border-b border-gray-100 flex items-center gap-2">
                <i class="ti ti-edit text-amber-600 text-lg"></i>
                <h4 class="font-semibold text-gray-800">Update Referral</h4>
            </div>
            <div class="p-6">
                <form action="{{ route('admin.referrals.updateStatus', $referral->id) }}" method="POST">
                    @csrf
                    @method('PATCH')

                    <div class="space-y-5">
                        <div>
                            <label for="status" class="block text-xs font-semibold text-gray-600 mb-1">Status</label>
                            <select name="status" id="status" required
                                class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                <option value="pending" {{ $referral->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="in_progress" {{ $referral->status == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="resolved" {{ $referral->status == 'resolved' ? 'selected' : '' }}>Resolved</option>
                                <option value="cancelled" {{ $referral->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                        </div>

                        <div>
                            <label for="counselor_id" class="block text-xs font-semibold text-gray-600 mb-1">Assign Counselor</label>
                            <select name="counselor_id" id="counselor_id"
                                class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                <option value="">Unassigned</option>
                                @foreach($counselors as $counselor)
                                    <option value="{{ $counselor->id }}" {{ $referral->counselor_id == $counselor->id ? 'selected' : '' }}>
                                        {{ $counselor->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="counselor_notes" class="block text-xs font-semibold text-gray-600 mb-1">Notes</label>
                            <textarea name="counselor_notes" id="counselor_notes" rows="3" placeholder="Add notes about progress, actions taken..."
                                class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">{{ $referral->counselor_notes }}</textarea>
                        </div>

                        <button type="submit" class="w-full mt-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition shadow-sm flex items-center justify-center gap-2">
                            <i class="ti ti-device-floppy"></i> Save Updates
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
