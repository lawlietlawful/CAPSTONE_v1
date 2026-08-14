@extends('layouts.counselor')

@section('title', 'Report Details')
@section('page-title', 'Behavioral Report Details')
@section('page-sub', 'View incident details and update review status')

@section('content')

<div class="mb-6 flex items-center justify-between">
    <a href="{{ route('counselor.behavioral-reports.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 flex items-center gap-1">
        <i class="ti ti-arrow-left"></i> Back to Reports
    </a>
    
    <a href="{{ route('counselor.behavioral-reports.print', $behavioral_report->id) }}" target="_blank"
       class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition shadow-sm">
        <i class="ti ti-printer text-gray-500"></i> Print Official Report
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Left Column: Incident Details -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden transition-all duration-300 hover:shadow-hover">
            <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 text-lg flex items-center gap-2">
                    <i class="ti ti-message-report text-amber-600"></i> Incident Report #{{ $behavioral_report->id }}
                </h3>
                <div class="flex items-center gap-2">
                    @php
                        $severityClass = match($behavioral_report->severity) {
                            'severe', 'Critical', 'High' => 'bg-red-50 text-red-700 border-red-200',
                            'moderate', 'Medium'         => 'bg-amber-50 text-amber-700 border-amber-200',
                            default                      => 'bg-green-50 text-green-700 border-green-200',
                        };
                        $statusClass = match($behavioral_report->status) {
                            'pending'  => 'bg-blue-50 text-blue-700 border-blue-200',
                            'reviewed' => 'bg-amber-50 text-amber-700 border-amber-200',
                            'resolved' => 'bg-green-50 text-green-700 border-green-200',
                            default    => 'bg-gray-100 text-gray-500 border-gray-200',
                        };
                    @endphp
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border {{ $severityClass }}">
                        {{ ucfirst($behavioral_report->severity) }} Severity
                    </span>
                    <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border {{ $statusClass }}">
                        {{ ucfirst($behavioral_report->status) }}
                    </span>
                </div>
            </div>

            <div class="p-6 space-y-5">
                <div class="grid grid-cols-2 gap-6">
                    <div>
                        <span class="block text-xs text-gray-400 uppercase tracking-wider mb-0.5">Incident Type</span>
                        <span class="font-medium text-gray-900">{{ $behavioral_report->incident_type }}</span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-400 uppercase tracking-wider mb-0.5">Incident Date</span>
                        <span class="font-medium text-gray-900">{{ $behavioral_report->incident_date->format('F j, Y') }}</span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-400 uppercase tracking-wider mb-0.5">Location</span>
                        <span class="font-medium text-gray-900">{{ $behavioral_report->location ?? 'Not specified' }}</span>
                    </div>
                    <div>
                        <span class="block text-xs text-gray-400 uppercase tracking-wider mb-0.5">Reported By</span>
                        <span class="font-medium text-gray-900">{{ $behavioral_report->reportedBy->name ?? '—' }}</span>
                    </div>
                </div>

                @if($behavioral_report->escalatedReferral)
                    <div class="pt-4 border-t border-gray-100">
                        <a href="{{ route('counselor.referrals.show', $behavioral_report->escalatedReferral->id) }}"
                            class="flex items-center gap-2 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-3 hover:bg-red-100 transition">
                            <i class="ti ti-alert-triangle text-lg"></i>
                            <span>This report was auto-escalated to <span class="font-semibold">Referral #{{ $behavioral_report->escalatedReferral->id }}</span> — click to view the case</span>
                        </a>
                    </div>
                @endif

                <div class="pt-4 border-t border-gray-100">
                    <span class="block text-xs text-gray-400 uppercase tracking-wider mb-1.5">Incident Description</span>
                    <p class="text-sm text-gray-700 leading-relaxed bg-gray-50 rounded-lg p-4 border border-gray-100">{{ $behavioral_report->description }}</p>
                </div>

                <div class="pt-4 border-t border-gray-100">
                    <span class="block text-xs text-gray-400 uppercase tracking-wider mb-0.5">Date Filed</span>
                    <span class="text-sm text-gray-600">{{ $behavioral_report->created_at->format('F j, Y — h:i A') }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Student Info + Status Update -->
    <div class="lg:col-span-1 space-y-6">

        <!-- Student Info -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6">
            <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="ti ti-user text-blue-600"></i> Student Information
            </h4>
            <div class="space-y-3 text-sm">
                <div>
                    <span class="block text-xs text-gray-400 uppercase tracking-wider">Full Name</span>
                    <span class="font-medium text-gray-900">{{ $behavioral_report->student->last_name }}, {{ $behavioral_report->student->first_name }} {{ $behavioral_report->student->middle_name }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-400 uppercase tracking-wider">Student ID</span>
                    <span class="font-medium text-gray-900">{{ $behavioral_report->student->student_id_number }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-400 uppercase tracking-wider">Course & Year</span>
                    <span class="font-medium text-gray-900">{{ $behavioral_report->student->course ?? $behavioral_report->student->grade_level }} — {{ $behavioral_report->student->section }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-400 uppercase tracking-wider">Parent / Guardian</span>
                    <span class="font-medium text-gray-900">{{ $behavioral_report->student->parent_name }}</span>
                    <span class="block text-xs text-gray-500">{{ $behavioral_report->student->parent_contact }}</span>
                </div>
            </div>
        </div>

        <!-- Status Update -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden transition-all duration-300 hover:shadow-hover">
            <div class="bg-gray-50/50 px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <i class="ti ti-edit text-amber-600"></i>
                <h4 class="font-semibold text-gray-800">Update Status</h4>
            </div>
            <div class="p-5">
                <form action="{{ route('counselor.behavioral-reports.updateStatus', $behavioral_report->id) }}" method="POST">
                    @csrf
                    @method('PATCH')

                    <div class="space-y-4">
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Review Status</label>
                            <select name="status" id="status" required
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                                <option value="pending" {{ $behavioral_report->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="reviewed" {{ $behavioral_report->status == 'reviewed' ? 'selected' : '' }}>Reviewed</option>
                                <option value="resolved" {{ $behavioral_report->status == 'resolved' ? 'selected' : '' }}>Resolved</option>
                            </select>
                        </div>

                        <div>
                            <label for="counselor_notes" class="block text-sm font-medium text-gray-700 mb-1">Internal Notes</label>
                            <textarea name="counselor_notes" id="counselor_notes" rows="4" placeholder="Add internal notes or feedback here..."
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">{{ $behavioral_report->counselor_notes }}</textarea>
                            <span class="text-[10px] text-gray-400 mt-1 block">These notes are only visible to Admins and Counselors.</span>
                        </div>

                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center justify-center gap-2">
                            <i class="ti ti-check"></i> Save & Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
