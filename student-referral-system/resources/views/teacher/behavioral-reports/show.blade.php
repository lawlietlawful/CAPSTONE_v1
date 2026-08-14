@extends('layouts.teacher')

@section('title', 'Report Details')
@section('page-title', 'Behavioral Report Details')
@section('page-sub', 'View details and status of your submitted report')

@section('content')

<div class="mb-6 flex items-center justify-between">
    <a href="{{ route('teacher.behavioral-reports.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 flex items-center gap-1">
        <i class="ti ti-arrow-left"></i> Back to My Reports
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Left Column: Incident Details -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden transition-all duration-300 hover:shadow-hover">
            <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 text-lg flex items-center gap-2">
                    <i class="ti ti-message-report text-blue-600"></i> Incident Report #{{ str_pad($behavioral_report->id, 4, '0', STR_PAD_LEFT) }}
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
                        Status: {{ ucfirst($behavioral_report->status) }}
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
                        <span class="block text-xs text-gray-400 uppercase tracking-wider mb-0.5">Date Filed</span>
                        <span class="font-medium text-gray-900">{{ $behavioral_report->created_at->format('F j, Y — h:i A') }}</span>
                    </div>
                </div>

                @if($behavioral_report->escalatedReferral)
                    <div class="pt-4 border-t border-gray-100">
                        <div class="flex items-center gap-2 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                            <i class="ti ti-alert-triangle text-lg"></i>
                            <span>This report was auto-escalated to <span class="font-semibold">Referral #{{ $behavioral_report->escalatedReferral->id }}</span>. The Guidance Office is now handling this case.</span>
                        </div>
                    </div>
                @endif

                <div class="pt-4 border-t border-gray-100">
                    <span class="block text-xs text-gray-400 uppercase tracking-wider mb-1.5">Incident Description</span>
                    <p class="text-sm text-gray-700 leading-relaxed bg-gray-50 rounded-lg p-4 border border-gray-100">{{ $behavioral_report->description }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Student Info -->
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
            </div>
        </div>
        
        <div class="bg-blue-50 border border-blue-100 rounded-2xl p-5">
            <div class="flex items-start gap-3">
                <i class="ti ti-info-circle text-blue-600 text-xl mt-0.5"></i>
                <div>
                    <h4 class="text-sm font-semibold text-blue-900 mb-1">Status Updates</h4>
                    <p class="text-xs text-blue-700 leading-relaxed">
                        This report is currently marked as <strong>{{ $behavioral_report->status }}</strong>. The Guidance Office and Administration handle status updates and parent interventions based on this initial report.
                    </p>
                </div>
            </div>
        </div>
        
    </div>
</div>

@endsection
