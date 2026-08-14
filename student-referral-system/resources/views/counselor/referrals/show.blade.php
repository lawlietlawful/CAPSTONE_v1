@extends('layouts.counselor')

@section('title', 'Referral Details')
@section('page-title', 'Referral Details')
@section('page-sub', 'View referral information and manage its status')

@section('content')

<div class="mb-6 flex justify-between items-center">
    <a href="{{ route('counselor.referrals.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 flex items-center gap-1">
        <i class="ti ti-arrow-left"></i> Back to Referrals
    </a>
    <div class="flex gap-2">
        <a href="{{ route('counselor.referrals.print', $referral->id) }}" target="_blank" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition shadow-sm flex items-center gap-2">
            <i class="ti ti-printer"></i> Print / Export PDF
        </a>
        <button onclick="document.getElementById('parent-contact-modal').classList.remove('hidden')" class="px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition shadow-sm flex items-center gap-2">
            <i class="ti ti-headset"></i> Log Parent Contact
        </button>
        <a href="{{ route('counselor.interventions.create', ['referral_id' => $referral->id]) }}" class="px-4 py-2 bg-amber-500 text-white text-sm font-medium rounded-lg hover:bg-amber-600 transition shadow-sm flex items-center gap-2">
            <i class="ti ti-plus"></i> Log Intervention
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column: Referral Details -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Main Info Card -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 text-lg flex items-center gap-2">
                    <i class="ti ti-file-text text-blue-600"></i> Referral #{{ $referral->id }}
                </h3>
                <div class="flex flex-col items-end gap-2">
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

                    @if($referral->riskAssessment)
                        @php
                            $aiClass = match($referral->riskAssessment->risk_level) {
                                'high' => 'bg-red-600 text-white shadow-sm',
                                'moderate' => 'bg-yellow-500 text-white shadow-sm',
                                default => 'bg-green-500 text-white shadow-sm',
                            };
                        @endphp
                        <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg text-xs font-bold {{ $aiClass }}">
                            <i class="ti ti-brain"></i> AI Assessment: {{ ucfirst($referral->riskAssessment->risk_level) }} Risk ({{ $referral->riskAssessment->risk_score }}%)
                        </div>
                    @endif
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
                        <a href="{{ route('counselor.behavioral-reports.show', $referral->behavioralReport->id) }}"
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

        <!-- Live Student Context Panel -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-chart-radar text-indigo-600"></i> Live Student Profile
                </h4>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-4 rounded-xl border border-indigo-100 bg-indigo-50/30 flex items-center gap-4">
                        <div class="w-10 h-10 rounded-lg bg-indigo-100 text-indigo-600 flex items-center justify-center text-xl shrink-0">
                            <i class="ti ti-file-export"></i>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 font-medium uppercase tracking-wide">Total Referrals</div>
                            <div class="text-2xl font-bold text-gray-900">{{ $referral->student->referrals()->count() }}</div>
                        </div>
                    </div>
                    
                    <div class="p-4 rounded-xl border border-amber-100 bg-amber-50/30 flex items-center gap-4">
                        <div class="w-10 h-10 rounded-lg bg-amber-100 text-amber-600 flex items-center justify-center text-xl shrink-0">
                            <i class="ti ti-tag"></i>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 font-medium uppercase tracking-wide">Concern Type</div>
                            @php
                                $ctClass = match($referral->concern_type) {
                                    'academic'      => 'bg-blue-100 text-blue-700',
                                    'behavioral'    => 'bg-red-100 text-red-700',
                                    'emotional'     => 'bg-purple-100 text-purple-700',
                                    'family'        => 'bg-amber-100 text-amber-700',
                                    'peer_conflict' => 'bg-orange-100 text-orange-700',
                                    'attendance'    => 'bg-gray-200 text-gray-700',
                                    default         => 'bg-gray-100 text-gray-600',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium mt-1 {{ $ctClass }}">
                                {{ ucfirst(str_replace('_', ' ', $referral->concern_type ?? 'other')) }}
                            </span>
                        </div>
                    </div>

                    <div class="p-4 rounded-xl border border-purple-100 bg-purple-50/30 flex items-center gap-4">
                        <div class="w-10 h-10 rounded-lg bg-purple-100 text-purple-600 flex items-center justify-center text-xl shrink-0">
                            <i class="ti ti-clipboard-data"></i>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 font-medium uppercase tracking-wide">Behavioral Reports</div>
                            <div class="text-2xl font-bold text-gray-900">{{ $referral->student->behavioralReports()->count() }}</div>
                        </div>
                    </div>
                </div>
                
                @if(count($recommendedSeminars) > 0)
                <div class="mt-6 pt-5 border-t border-gray-100">
                    <h5 class="text-sm font-semibold text-gray-800 mb-3 flex items-center gap-2">
                        <i class="ti ti-sparkles text-yellow-500"></i> AI Recommended Seminars
                    </h5>
                    <div class="space-y-3">
                        @foreach($recommendedSeminars as $seminar)
                            <div class="flex items-center justify-between p-3 rounded-lg border border-indigo-100 bg-indigo-50/30">
                                <div>
                                    <div class="font-medium text-indigo-900 text-sm">{{ $seminar->title }}</div>
                                    <div class="text-xs text-indigo-600/70">{{ \Carbon\Carbon::parse($seminar->date)->format('M d, Y') }}</div>
                                </div>
                                <form action="{{ route('counselor.seminars.assign', $seminar->id) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="student_ids[]" value="{{ $referral->student_id }}">
                                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold bg-indigo-600 text-white rounded hover:bg-indigo-700 transition shadow-sm">
                                        Assign Student
                                    </button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>

        <!-- Student Journey Timeline -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-timeline text-blue-600"></i> Student Journey Timeline
                </h4>
            </div>
            <div class="p-6 relative">
                <div class="absolute left-10 top-6 bottom-6 w-px bg-gray-200"></div>
                <div class="space-y-6 relative z-10">
                    @forelse($timeline as $event)
                        <div class="flex gap-4">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 border-4 border-white shadow-sm z-10 {{ $event['color'] }}">
                                <i class="ti {{ $event['icon'] }} text-sm"></i>
                            </div>
                            <div class="flex-1 pt-1">
                                <div class="flex justify-between items-start mb-1">
                                    <h5 class="text-sm font-semibold text-gray-900">{{ $event['title'] }}</h5>
                                    <span class="text-xs text-gray-400 font-medium">{{ \Carbon\Carbon::parse($event['date'])->format('M d, Y h:i A') }}</span>
                                </div>
                                <p class="text-sm text-gray-600 leading-relaxed">{{ $event['description'] }}</p>
                                @if(isset($event['id']) && $event['type'] === 'intervention')
                                    <a href="{{ route('counselor.interventions.show', $event['id']) }}" class="inline-block mt-2 text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                                        View Details &rarr;
                                    </a>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-sm text-gray-400">No events found.</div>
                    @endforelse
                </div>
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
            <div class="space-y-3 text-sm">
                <div>
                    <span class="block text-xs text-gray-400 uppercase tracking-wider">Full Name</span>
                    <span class="font-medium text-gray-900">{{ $referral->student->last_name }}, {{ $referral->student->first_name }} {{ $referral->student->middle_name }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-400 uppercase tracking-wider">Student ID</span>
                    <span class="font-medium text-gray-900">{{ $referral->student->student_id_number }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-400 uppercase tracking-wider">Course & Year</span>
                    <span class="font-medium text-gray-900">{{ $referral->student->course ?? $referral->student->grade_level }} — {{ $referral->student->section }}</span>
                </div>
                <div>
                    <span class="block text-xs text-gray-400 uppercase tracking-wider">Parent / Guardian</span>
                    <span class="font-medium text-gray-900">{{ $referral->student->parent_name }}</span>
                    <span class="block text-xs text-gray-500">{{ $referral->student->parent_contact }}</span>
                </div>
            </div>
        </div>

        <!-- AI Risk Assessment & Seminars -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6">
            <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="ti ti-brain text-purple-600"></i> AI Risk Assessment
            </h4>
            <div class="space-y-4">
                @if($referral->riskAssessment)
                    <div>
                        <span class="block text-xs text-gray-400 uppercase tracking-wider mb-1">Risk Level</span>
                        @if($referral->riskAssessment->risk_level == 'high')
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium bg-red-100 text-red-800 border border-red-200">High Risk ({{ $referral->riskAssessment->risk_score }}%)</span>
                        @elseif($referral->riskAssessment->risk_level == 'moderate')
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">Moderate Risk ({{ $referral->riskAssessment->risk_score }}%)</span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium bg-green-100 text-green-800 border border-green-200">Low Risk ({{ $referral->riskAssessment->risk_score }}%)</span>
                        @endif
                    </div>
                @else
                    <p class="text-sm text-gray-500 italic">No AI Risk Assessment was generated for this referral.</p>
                @endif

                @if($referral->student->seminars->count() > 0)
                    <div class="pt-3 border-t border-gray-100">
                        <span class="block text-xs text-gray-400 uppercase tracking-wider mb-2">Auto-Assigned Seminars</span>
                        @foreach($referral->student->seminars as $seminar)
                            <div class="p-3 bg-blue-50/50 rounded-lg border border-blue-100 text-sm">
                                <p class="font-semibold text-blue-900"><i class="ti ti-books text-blue-500"></i> {{ $seminar->title }}</p>
                                <p class="text-xs text-gray-500 mt-1">{{ \Carbon\Carbon::parse($seminar->date)->format('M d, Y') }} at {{ \Carbon\Carbon::parse($seminar->time)->format('h:i A') }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <!-- Update Status Form -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="bg-gray-50/50 px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <i class="ti ti-edit text-amber-600"></i>
                <h4 class="font-semibold text-gray-800">Update Referral</h4>
            </div>
            <div class="p-5">
                <form action="{{ route('counselor.referrals.updateStatus', $referral->id) }}" method="POST">
                    @csrf
                    @method('PATCH')

                    <div class="space-y-4">
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select name="status" id="status" required
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                                <option value="pending" {{ $referral->status == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="in_progress" {{ $referral->status == 'in_progress' ? 'selected' : '' }}>In Progress</option>
                                <option value="resolved" {{ $referral->status == 'resolved' ? 'selected' : '' }}>Resolved</option>
                                <option value="cancelled" {{ $referral->status == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label for="counselor_id" class="block text-sm font-medium text-gray-700 mb-1">Assign Counselor</label>
                            <select name="counselor_id" id="counselor_id"
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                                <option value="">Unassigned</option>
                                @foreach($counselors as $counselor)
                                    <option value="{{ $counselor->id }}" {{ $referral->counselor_id == $counselor->id ? 'selected' : '' }}>
                                        {{ $counselor->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="counselor_notes" class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <textarea name="counselor_notes" id="counselor_notes" rows="3" placeholder="Add notes about progress, actions taken..."
                                class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">{{ $referral->counselor_notes }}</textarea>
                        </div>

                        <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center justify-center gap-2">
                            <i class="ti ti-check"></i> Update Referral
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Parent Contact Modal -->
<div id="parent-contact-modal" class="fixed inset-0 z-50 hidden">
    <div class="fixed inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="document.getElementById('parent-contact-modal').classList.add('hidden')"></div>
    <div class="fixed inset-0 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden relative z-10">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex justify-between items-center">
                <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-headset text-purple-600 text-lg"></i> Log Parent Communication
                </h3>
                <button type="button" onclick="document.getElementById('parent-contact-modal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600">
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>
            
            <form action="{{ route('counselor.referrals.logParentContact', $referral->id) }}" method="POST" class="p-6">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label for="contact_method" class="block text-sm font-medium text-gray-700 mb-1">Contact Method</label>
                        <select name="contact_method" id="contact_method" required class="w-full rounded-lg border-gray-300 focus:border-purple-500 focus:ring focus:ring-purple-200 text-sm">
                            <option value="call">Phone Call</option>
                            <option value="sms">SMS / Text</option>
                            <option value="email">Email</option>
                            <option value="visit">Office Visit</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="summary" class="block text-sm font-medium text-gray-700 mb-1">Summary / Notes</label>
                        <textarea name="summary" id="summary" rows="4" required placeholder="What was discussed?" class="w-full rounded-lg border-gray-300 focus:border-purple-500 focus:ring focus:ring-purple-200 text-sm"></textarea>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('parent-contact-modal').classList.add('hidden')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-lg hover:bg-purple-700 transition">
                        Save Log
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
