@extends('layouts.admin')

@section('title', 'Student Profile')
@section('page-title', 'Student Profile')
@section('page-sub', 'Comprehensive view of student records, risk status, and history')

@section('content')

<div class="mb-6 flex items-center justify-between print:hidden">
    <a href="{{ route('admin.students.index') }}" class="text-sm font-medium text-gray-400 hover:text-gray-900 flex items-center gap-2 transition-colors">
        <i class="ti ti-arrow-left text-lg"></i> Back to Students
    </a>
    <div class="flex items-center gap-3">
        <button onclick="window.print()" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 transition shadow-sm gap-2">
            <i class="ti ti-printer"></i> Print Dossier
        </button>
        <!-- Quick Actions Dropdown -->
        <div class="relative" x-data="{ open: false }">
            <button @click="open = !open" @click.away="open = false" class="inline-flex items-center justify-center px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-xl hover:bg-gray-800 transition shadow-sm gap-2">
                <i class="ti ti-bolt"></i> Quick Actions <i class="ti ti-chevron-down text-xs"></i>
            </button>
            <div x-show="open" x-transition class="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 z-50 overflow-hidden" style="display: none;" x-cloak>
                <a href="{{ route('admin.referrals.create', ['student_id' => $student->id]) }}" class="block px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2 transition">
                    <i class="ti ti-file-description text-blue-500 text-lg"></i> Create Referral
                </a>
                <a href="{{ route('counselor.messages.index') }}" class="block px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2 transition">
                    <i class="ti ti-send text-indigo-500 text-lg"></i> Send Notice
                </a>
                <div class="border-t border-gray-100"></div>
                <a href="{{ route('admin.seminars.index', ['student' => $student->id]) }}" class="block px-4 py-3 text-sm text-gray-700 hover:bg-gray-50 flex items-center gap-2 transition">
                    <i class="ti ti-users text-amber-500 text-lg"></i> Assign Seminar
                </a>
            </div>
        </div>
        <button type="button" x-data @click="$dispatch('open-edit-modal-{{ $student->id }}')" class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition shadow-sm gap-2">
            <i class="ti ti-pencil"></i> Edit Profile
        </button>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6" x-data="{ tab: 'demographics' }">
    <!-- Left Column: Summary Card -->
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6 flex flex-col items-center text-center relative overflow-hidden transition-all duration-300 hover:shadow-hover print:shadow-none print:border-gray-300 print:break-inside-avoid">
            <div class="w-28 h-28 rounded-full bg-gradient-to-tr from-blue-100 to-indigo-50 text-blue-700 flex items-center justify-center text-4xl font-bold mb-5 ring-4 ring-white shadow-xl z-10">
                {{ substr($student->first_name, 0, 1) }}{{ substr($student->last_name, 0, 1) }}
            </div>
            
            <h3 class="font-bold text-gray-900 text-xl tracking-tight">{{ $student->first_name }} {{ $student->last_name }}</h3>
            <p class="text-gray-400 text-sm font-medium mt-0.5">{{ $student->student_id_number }}</p>

            <!-- Quick Metrics -->
            <div class="mt-6 pt-5 border-t border-gray-50 w-full grid grid-cols-3 gap-3 text-center">
                <div class="rounded-xl border border-gray-100 p-3 hover:border-gray-200 transition-colors flex flex-col justify-center items-center">
                    <span class="block text-2xl font-bold text-gray-800">{{ $student->referrals->count() }}</span>
                    <span class="block text-[9px] text-gray-400 font-semibold uppercase tracking-widest mt-1">Referrals</span>
                </div>
                <div class="rounded-xl border border-gray-100 p-3 hover:border-gray-200 transition-colors flex flex-col justify-center items-center">
                    <span class="block text-2xl font-bold text-gray-800">{{ $student->behavioralReports->count() }}</span>
                    <span class="block text-[9px] text-gray-400 font-semibold uppercase tracking-widest mt-1">Incidents</span>
                </div>
                <div class="rounded-xl border border-gray-100 p-3 hover:border-gray-200 transition-colors flex flex-col justify-center items-center">
                    @php
                        $latestRisk = $student->latestRiskAssessment;
                        $riskColor = match($latestRisk?->risk_level) {
                            'high' => 'text-red-500',
                            'moderate' => 'text-amber-500',
                            'low' => 'text-emerald-500',
                            default => 'text-gray-400',
                        };
                    @endphp
                    <span class="block text-[15px] font-bold {{ $riskColor }} leading-tight mb-0.5">{{ $latestRisk ? ucfirst($latestRisk->risk_level) : 'Not Assessed' }}</span>
                    <span class="block text-[9px] text-gray-400 font-semibold uppercase tracking-widest mt-1">Risk Lvl</span>
                </div>
            </div>

            <div class="mt-5 pt-5 border-t border-gray-50 w-full text-left print:hidden">
                <span class="block text-[10px] text-gray-400 font-semibold uppercase tracking-widest mb-2">Portal Account</span>
                @if($student->user && $student->user->isActivated())
                    <div class="flex items-center gap-2 text-sm text-emerald-600 font-medium">
                        <i class="ti ti-check bg-emerald-50 rounded-full p-1 text-xs"></i> Active Account
                    </div>
                    <p class="text-xs text-gray-400 mt-1 ml-6">Username: {{ $student->user->username }}</p>
                @elseif($student->user)
                    <div class="flex items-center gap-2 text-sm text-amber-600 font-medium">
                        <i class="ti ti-clock bg-amber-50 rounded-full p-1 text-xs"></i> Pending Activation
                    </div>
                    <p class="text-xs text-gray-400 mt-1 ml-6 mb-2">Student ID: {{ $student->user->username }}</p>
                    <form method="POST" action="{{ route('admin.students.activation-code', $student) }}" class="ml-6">
                        @csrf
                        <button type="submit" class="text-xs font-medium text-blue-600 hover:text-blue-800 inline-flex items-center gap-1">
                            <i class="ti ti-key"></i> Generate activation code
                        </button>
                    </form>
                @else
                    <div class="flex items-center gap-2 text-sm text-red-500 font-medium">
                        <i class="ti ti-x bg-red-50 rounded-full p-1 text-xs"></i> No Account
                    </div>
                @endif
            </div>
        </div>

        <!-- Parent Info Card -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden transition-all duration-300 hover:shadow-hover print:shadow-none print:border-gray-300 print:break-inside-avoid">
            <div class="bg-emerald-50/30 px-6 py-4 border-b border-gray-50 flex items-center gap-2">
                <i class="ti ti-users text-emerald-600 text-lg"></i>
                <h4 class="font-semibold text-gray-800 text-sm tracking-wide">Parent / Guardian</h4>
            </div>
            <div class="p-6 space-y-5">
                <div>
                    <span class="block text-[10px] text-gray-400 font-semibold uppercase tracking-widest mb-1.5">Name</span>
                    <span class="font-medium text-gray-900">{{ $student->parent_name }}</span>
                </div>
                <div>
                    <span class="block text-[10px] text-gray-400 font-semibold uppercase tracking-widest mb-1.5">Contact (SMS)</span>
                    <span class="font-medium text-gray-900 flex items-center gap-2">
                        {{ $student->parent_contact }}
                        <a href="tel:{{ $student->parent_contact }}" class="text-blue-500 hover:text-blue-700 bg-blue-50 p-1.5 rounded-lg transition-colors print:hidden">
                            <i class="ti ti-phone-call"></i>
                        </a>
                    </span>
                </div>
                @if($student->parent_email)
                <div>
                    <span class="block text-[10px] text-gray-400 font-semibold uppercase tracking-widest mb-1.5">Email</span>
                    <span class="font-medium text-gray-900">{{ $student->parent_email }}</span>
                </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Right Column: Tabs -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden transition-all duration-300 hover:shadow-hover print:shadow-none print:border-transparent print:col-span-full flex flex-col self-start">
            <div class="px-6 pt-6 print:hidden">
                <div class="bg-gray-50/80 p-1.5 rounded-xl inline-flex flex-wrap gap-1 border border-gray-100/50" id="profile-tabs">
                    <button @click="tab = 'demographics'" :class="{'bg-white text-blue-600 shadow-sm ring-1 ring-gray-900/5': tab === 'demographics', 'text-gray-500 hover:text-gray-700 hover:bg-gray-100/50': tab !== 'demographics'}" class="px-5 py-2.5 text-sm font-semibold rounded-lg focus:outline-none transition-all flex items-center gap-2">
                        <i class="ti ti-id text-lg"></i> Demographics
                    </button>
                    <button @click="tab = 'timeline'" :class="{'bg-white text-blue-600 shadow-sm ring-1 ring-gray-900/5': tab === 'timeline', 'text-gray-500 hover:text-gray-700 hover:bg-gray-100/50': tab !== 'timeline'}" class="px-5 py-2.5 text-sm font-semibold rounded-lg focus:outline-none transition-all flex items-center gap-2">
                        <i class="ti ti-history text-lg"></i> Behavioral Timeline
                    </button>
                    <button @click="tab = 'referrals'" :class="{'bg-white text-blue-600 shadow-sm ring-1 ring-gray-900/5': tab === 'referrals', 'text-gray-500 hover:text-gray-700 hover:bg-gray-100/50': tab !== 'referrals'}" class="px-5 py-2.5 text-sm font-semibold rounded-lg focus:outline-none transition-all flex items-center gap-2">
                        <i class="ti ti-file-description text-lg"></i> Referrals
                    </button>
                    <button @click="tab = 'behavioral'" :class="{'bg-white text-blue-600 shadow-sm ring-1 ring-gray-900/5': tab === 'behavioral', 'text-gray-500 hover:text-gray-700 hover:bg-gray-100/50': tab !== 'behavioral'}" class="px-5 py-2.5 text-sm font-semibold rounded-lg focus:outline-none transition-all flex items-center gap-2">
                        <i class="ti ti-message-report text-lg"></i> Behavioral Reports
                    </button>
                </div>
            </div>
            
            <div class="p-6 flex-1">
                <!-- Tab: Demographics -->
                <div x-show="tab === 'demographics'" class="space-y-8 print:block" x-cloak>
                    <div>
                        <h4 class="text-[11px] font-bold text-gray-800 border-b border-gray-100 pb-3 mb-5 uppercase tracking-widest flex items-center gap-2">
                            <i class="ti ti-user-scan text-blue-500 text-lg"></i> Personal Information
                        </h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            <div class="bg-gray-50/50 border border-gray-100/50 p-4 rounded-xl">
                                <span class="block text-[10px] text-gray-400 font-semibold uppercase tracking-widest mb-1.5">Gender</span>
                                <span class="font-medium text-gray-900">{{ ucfirst($student->gender) }}</span>
                            </div>
                            <div class="bg-gray-50/50 border border-gray-100/50 p-4 rounded-xl">
                                <span class="block text-[10px] text-gray-400 font-semibold uppercase tracking-widest mb-1.5">Birthdate</span>
                                <span class="font-medium text-gray-900">{{ \Carbon\Carbon::parse($student->birthdate)->format('M d, Y') }}</span>
                            </div>
                            <div class="bg-gray-50/50 border border-gray-100/50 p-4 rounded-xl">
                                <span class="block text-[10px] text-gray-400 font-semibold uppercase tracking-widest mb-1.5">Age</span>
                                <span class="font-medium text-gray-900">{{ \Carbon\Carbon::parse($student->birthdate)->age }} years old</span>
                            </div>
                            <div class="col-span-2 md:col-span-2 bg-gray-50/50 border border-gray-100/50 p-4 rounded-xl">
                                <span class="block text-[10px] text-gray-400 font-semibold uppercase tracking-widest mb-1.5">Home Address</span>
                                <span class="font-medium text-gray-900">{{ $student->address }}</span>
                            </div>
                            <div class="col-span-2 md:col-span-1 bg-gray-50/50 border border-gray-100/50 p-4 rounded-xl">
                                <span class="block text-[10px] text-gray-400 font-semibold uppercase tracking-widest mb-1.5">Contact No.</span>
                                <span class="font-medium text-gray-900">{{ $student->student_contact ?: '—' }}</span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h4 class="text-[11px] font-bold text-gray-800 border-b border-gray-100 pb-3 mb-5 uppercase tracking-widest flex items-center gap-2">
                            <i class="ti ti-school text-amber-500 text-lg"></i> Academic Information
                        </h4>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-gray-50/50 border border-gray-100/50 p-4 rounded-xl">
                                <span class="block text-[10px] text-gray-400 font-semibold uppercase tracking-widest mb-1.5">Education Level</span>
                                <span class="font-medium text-gray-900">{{ $student->education_level }}</span>
                            </div>
                            <div class="bg-gray-50/50 border border-gray-100/50 p-4 rounded-xl">
                                <span class="block text-[10px] text-gray-400 font-semibold uppercase tracking-widest mb-1.5">Course / Program</span>
                                <span class="font-medium text-gray-900">{{ $student->course }}</span>
                            </div>
                            <div class="bg-gray-50/50 border border-gray-100/50 p-4 rounded-xl">
                                <span class="block text-[10px] text-gray-400 font-semibold uppercase tracking-widest mb-1.5">{{ $student->education_level === 'Basic Education' ? 'Grade Level' : 'Year Level' }}</span>
                                <span class="font-medium text-gray-900">{{ $student->grade_level }}@if($student->strand) · {{ $student->strand }}@endif</span>
                            </div>
                            <div class="bg-gray-50/50 border border-gray-100/50 p-4 rounded-xl">
                                <span class="block text-[10px] text-gray-400 font-semibold uppercase tracking-widest mb-1.5">School Year</span>
                                <span class="font-medium text-gray-900">{{ $student->school_year }}</span>
                            </div>
                            <div class="bg-gray-50/50 border border-gray-100/50 p-4 rounded-xl">
                                <span class="block text-[10px] text-gray-400 font-semibold uppercase tracking-widest mb-1.5">Status</span>
                                <div class="mt-0.5">
                                    @if($student->status === 'active')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-600 border border-emerald-100">Active</span>
                                    @elseif($student->status === 'inactive')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-gray-50 text-gray-500 border border-gray-200">Inactive</span>
                                    @elseif($student->status === 'transferred')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-amber-50 text-amber-600 border border-amber-100">Transferred</span>
                                    @elseif($student->status === 'graduated')
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-blue-50 text-blue-600 border border-blue-100">Graduated</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab: Timeline -->
                <div x-show="tab === 'timeline'" class="hidden print:block print:mt-10 max-h-[700px] overflow-y-auto" :class="{'hidden': tab !== 'timeline' && !window.matchMedia('print').matches}" x-cloak>
                    <h4 class="text-[11px] font-bold text-gray-800 border-b border-gray-100 pb-3 mb-6 uppercase tracking-widest flex items-center gap-2">
                        <i class="ti ti-history text-indigo-500 text-lg"></i> Behavioral & Intervention Timeline
                    </h4>

                    @php
                        $latestRisk = $student->latestRiskAssessment;
                        // risk_factors is cast to 'array' on the model — already decoded here.
                        $riskFactors = $latestRisk ? $latestRisk->risk_factors : null;
                    @endphp

                    @if($latestRisk)
                        <div class="mb-8 bg-gradient-to-br from-indigo-50 to-blue-50 border border-indigo-100/50 rounded-2xl p-5 shadow-sm">
                            <h5 class="text-sm font-bold text-indigo-900 mb-4 flex items-center gap-2">
                                <i class="ti ti-brain text-indigo-600"></i> AI Risk Analysis Breakdown
                                <span class="ml-auto inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-widest bg-white text-indigo-700 shadow-sm">
                                    Risk Score: {{ $latestRisk->risk_score }}%
                                </span>
                            </h5>
                            
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                                @if($latestRisk->previous_referrals_count > 0 || $latestRisk->behavioral_reports_count > 0)
                                <div class="bg-white rounded-xl p-3 border border-indigo-50 shadow-sm">
                                    <span class="block text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-1">History</span>
                                    <p class="text-xs font-medium text-gray-700">Referrals: <span class="text-red-500 font-bold">{{ $latestRisk->previous_referrals_count }}</span></p>
                                    <p class="text-xs font-medium text-gray-700">Reports: <span class="text-amber-500 font-bold">{{ $latestRisk->behavioral_reports_count }}</span></p>
                                </div>
                                @endif

                                @if($latestRisk->concern_type_encoded)
                                <div class="bg-white rounded-xl p-3 border border-indigo-50 shadow-sm">
                                    <span class="block text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-1">Concern</span>
                                    <p class="text-xs font-medium text-gray-700">Code: <span class="text-indigo-500 font-bold">{{ $latestRisk->concern_type_encoded }}</span></p>
                                </div>
                                @endif
                                
                                @if($latestRisk->days_since_last_referral < 999)
                                <div class="bg-white rounded-xl p-3 border border-indigo-50 shadow-sm">
                                    <span class="block text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-1">Timing</span>
                                    <p class="text-xs font-medium text-gray-700">Days since last: <span class="text-gray-900 font-bold">{{ $latestRisk->days_since_last_referral }}</span></p>
                                </div>
                                @endif

                                @if(isset($riskFactors['recommended_seminar_tag']))
                                <div class="bg-white rounded-xl p-3 border border-indigo-50 shadow-sm">
                                    <span class="block text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-1">AI Recommendation</span>
                                    <p class="text-xs font-bold text-indigo-600 truncate" title="{{ ucwords(str_replace('_', ' ', $riskFactors['recommended_seminar_tag'])) }}">
                                        {{ ucwords(str_replace('_', ' ', $riskFactors['recommended_seminar_tag'])) }}
                                    </p>
                                </div>
                                @endif
                            </div>
                        </div>
                    @endif
                    
                    <div class="relative border-l-2 border-gray-100 ml-3 md:ml-4 space-y-8">
                        @forelse($timeline as $event)
                            <div class="relative pl-6 md:pl-8 group">
                                <div class="absolute -left-3 md:-left-3.5 top-0 w-6 h-6 md:w-7 md:h-7 rounded-full bg-{{ $event['color'] }}-50 flex items-center justify-center border-4 border-white shadow-sm transition-transform group-hover:scale-110">
                                    <i class="ti {{ $event['icon'] }} text-{{ $event['color'] }}-600 text-[10px]"></i>
                                </div>
                                <div>
                                    <span class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">{{ \Carbon\Carbon::parse($event['date'])->format('M d, Y h:i A') }}</span>
                                    <h5 class="text-gray-900 font-semibold mt-1">{{ $event['title'] }}</h5>
                                    @if($event['description'])
                                        <p class="text-sm text-gray-500 mt-2 bg-gray-50/50 p-4 rounded-xl border border-gray-100/50 leading-relaxed">{{ $event['description'] }}</p>
                                    @endif
                                    @if($event['status'])
                                        <div class="mt-3">
                                            <span class="inline-flex items-center px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-gray-50 text-gray-600 border border-gray-100/50">
                                                Status: {{ $event['status'] }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="pl-6 md:pl-8 py-4">
                                <p class="text-sm text-gray-500 italic">No timeline events recorded yet.</p>
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- Tab: Referrals -->
                <div x-show="tab === 'referrals'" class="hidden print:block print:mt-10" :class="{'hidden': tab !== 'referrals' && !window.matchMedia('print').matches}" x-cloak>
                    <div class="flex items-center justify-between border-b border-gray-100 pb-3 mb-5">
                        <h4 class="text-[11px] font-bold text-gray-800 uppercase tracking-widest flex items-center gap-2">
                            <i class="ti ti-file-description text-blue-500 text-lg"></i> Referral History
                        </h4>
                        <a href="{{ route('admin.referrals.create', ['student_id' => $student->id]) }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition-colors print:hidden">
                            + New Referral
                        </a>
                    </div>
                    
                    <div class="overflow-x-auto rounded-xl border border-gray-100/50">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-50/50 border-b border-gray-100/50">
                                    <th class="py-3 px-4 font-bold text-gray-500 text-[10px] uppercase tracking-widest">Date</th>
                                    <th class="py-3 px-4 font-bold text-gray-500 text-[10px] uppercase tracking-widest">Type</th>
                                    <th class="py-3 px-4 font-bold text-gray-500 text-[10px] uppercase tracking-widest">Status</th>
                                    <th class="py-3 px-4 font-bold text-gray-500 text-[10px] uppercase tracking-widest print:hidden">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100/50 bg-white">
                                @forelse($student->referrals as $ref)
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="py-3 px-4 text-gray-500">{{ $ref->created_at->format('M d, Y') }}</td>
                                        <td class="py-3 px-4 font-medium text-gray-900">{{ $ref->referral_type_label }}</td>
                                        <td class="py-3 px-4">
                                            <span class="inline-flex items-center px-2 py-1 rounded-md text-[9px] font-bold uppercase tracking-wider bg-gray-50 text-gray-600 border border-gray-100/50">
                                                {{ ucfirst($ref->status) }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 print:hidden">
                                            <a href="{{ route('admin.referrals.show', $ref->id) }}" class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-blue-50 text-blue-600 hover:bg-blue-100 transition" title="View Referral">
                                                <i class="ti ti-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-6 text-center text-sm text-gray-500">No referrals found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tab: Behavioral Reports -->
                <div x-show="tab === 'behavioral'" class="hidden print:block print:mt-10" :class="{'hidden': tab !== 'behavioral' && !window.matchMedia('print').matches}" x-cloak>
                    <h4 class="text-[11px] font-bold text-gray-800 border-b border-gray-100 pb-3 mb-5 uppercase tracking-widest flex items-center gap-2">
                        <i class="ti ti-message-report text-teal-500 text-lg"></i> Behavioral Reports
                    </h4>

                    <div class="overflow-x-auto rounded-xl border border-gray-100/50">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-50/50 border-b border-gray-100/50">
                                    <th class="py-3 px-4 font-bold text-gray-500 text-[10px] uppercase tracking-widest">Date</th>
                                    <th class="py-3 px-4 font-bold text-gray-500 text-[10px] uppercase tracking-widest">Type</th>
                                    <th class="py-3 px-4 font-bold text-gray-500 text-[10px] uppercase tracking-widest">Severity</th>
                                    <th class="py-3 px-4 font-bold text-gray-500 text-[10px] uppercase tracking-widest">Description</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100/50 bg-white">
                                @forelse($student->behavioralReports->sortByDesc('incident_date')->take(15) as $report)
                                    <tr class="hover:bg-gray-50/50 transition-colors">
                                        <td class="py-3 px-4 text-gray-900 font-medium">{{ $report->incident_date->format('M d, Y') }}</td>
                                        <td class="py-3 px-4 text-gray-600">{{ $report->incident_type }}</td>
                                        <td class="py-3 px-4">
                                            @php
                                                $sevColor = match($report->severity) {
                                                    'severe', 'Critical', 'High' => 'bg-red-50 text-red-600 border-red-100',
                                                    'moderate', 'Medium' => 'bg-amber-50 text-amber-600 border-amber-100',
                                                    default => 'bg-emerald-50 text-emerald-600 border-emerald-100',
                                                };
                                            @endphp
                                            <span class="inline-flex items-center px-2 py-1 rounded-md text-[9px] font-bold uppercase tracking-wider border {{ $sevColor }}">
                                                {{ $report->severity }}
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-gray-500 text-xs">{{ $report->description ?: '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-6 text-center text-sm text-gray-500">No behavioral reports found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                        @if($student->behavioralReports->count() > 15)
                            <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-widest mt-4 text-center print:hidden">Showing last 15 records.</p>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Edit Student Modal -->
<div x-data="{ showEditModal: {{ $errors->any() && old('edit_student_id') == $student->id ? 'true' : 'false' }} }" @open-edit-modal-{{ $student->id }}.window="showEditModal = true">
    <div x-show="showEditModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showEditModal" x-transition.opacity class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" aria-hidden="true" @click="showEditModal = false"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="showEditModal" x-transition.scale.origin.bottom class="inline-block w-full max-w-4xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl sm:p-8">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">Edit Student</h3>
                        <p class="text-sm text-gray-500 mt-1">Update the student's profile information.</p>
                    </div>
                    <button type="button" @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 transition bg-gray-50 hover:bg-gray-100 rounded-full p-2">
                        <i class="ti ti-x text-xl"></i>
                    </button>
                </div>

                <form action="{{ route('admin.students.update', $student->id) }}" method="POST"
                      x-data='{
                          education_level: "{{ old('education_level', $student->education_level) }}",
                          course: "{{ old('course', $student->course) }}",
                          grade_level: "{{ old('grade_level', $student->grade_level) }}",
                          strand: "{{ old('strand', $student->strand) }}",
                          section: "{{ old('section', $student->section) }}",
                          combos: @json($courseCombos),
                          seniorHighGrades: {{ json_encode($seniorHighGrades) }},
                          isBasicEd() { return this.education_level === "Basic Education"; },
                          isSeniorHigh() { return this.isBasicEd() && this.seniorHighGrades.includes(this.grade_level); },
                          coursesFor() { return [...new Set(this.combos.filter(c => c.education_level === this.education_level).map(c => c.course))]; },
                          gradesFor(course) { return [...new Set(this.combos.filter(c => c.course === course && c.education_level === this.education_level).map(c => c.grade_level))]; },
                          sectionsFor(course, grade) { return [...new Set(this.combos.filter(c => c.course === course && c.education_level === this.education_level && (!grade || c.grade_level === grade)).map(c => c.section))]; }
                      }'>
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="edit_student_id" value="{{ $student->id }}">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                        
                        <!-- Left Column: Personal Details -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-800 border-b border-gray-100 pb-2 flex items-center gap-2 text-sm uppercase tracking-wider">
                                <i class="ti ti-user-edit text-blue-600"></i> Personal Details
                            </h4>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Student ID Number <span class="text-red-500">*</span></label>
                                <input type="text" name="student_id_number" value="{{ old('student_id_number', $student->student_id_number) }}" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_student_id') == $student->id) @error('student_id_number') border-red-500 @enderror @endif">
                                @if(old('edit_student_id') == $student->id) @error('student_id_number') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror @endif
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">First Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="first_name" value="{{ old('first_name', $student->first_name) }}" required
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_student_id') == $student->id) @error('first_name') border-red-500 @enderror @endif">
                                    @if(old('edit_student_id') == $student->id) @error('first_name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror @endif
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Last Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="last_name" value="{{ old('last_name', $student->last_name) }}" required
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_student_id') == $student->id) @error('last_name') border-red-500 @enderror @endif">
                                    @if(old('edit_student_id') == $student->id) @error('last_name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror @endif
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Middle Name</label>
                                    <input type="text" name="middle_name" value="{{ old('middle_name', $student->middle_name) }}"
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_student_id') == $student->id) @error('middle_name') border-red-500 @enderror @endif">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Gender <span class="text-red-500">*</span></label>
                                    <select name="gender" required
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_student_id') == $student->id) @error('gender') border-red-500 @enderror @endif">
                                        <option value="">Select...</option>
                                        <option value="Male" {{ old('gender', $student->gender) === 'Male' ? 'selected' : '' }}>Male</option>
                                        <option value="Female" {{ old('gender', $student->gender) === 'Female' ? 'selected' : '' }}>Female</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Birthdate <span class="text-red-500">*</span></label>
                                    <input type="date" name="birthdate" value="{{ old('birthdate', $student->birthdate ? $student->birthdate->format('Y-m-d') : '') }}" required
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_student_id') == $student->id) @error('birthdate') border-red-500 @enderror @endif">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Student Contact</label>
                                    <input type="text" name="student_contact" value="{{ old('student_contact', $student->student_contact) }}"
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Home Address <span class="text-red-500">*</span></label>
                                <input type="text" name="address" value="{{ old('address', $student->address) }}" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_student_id') == $student->id) @error('address') border-red-500 @enderror @endif">
                            </div>
                        </div>

                        <!-- Right Column: Academic & Parent Details -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-800 border-b border-gray-100 pb-2 flex items-center gap-2 text-sm uppercase tracking-wider">
                                <i class="ti ti-school text-amber-600"></i> Academic Details
                            </h4>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Education Level <span class="text-red-500">*</span></label>
                                <select name="education_level" x-model="education_level" @change="course = ''; grade_level = ''; strand = ''; section = '';" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_student_id') == $student->id) @error('education_level') border-red-500 @enderror @endif">
                                    <option value="College" {{ old('education_level', $student->education_level) === 'College' ? 'selected' : '' }}>College</option>
                                    <option value="Basic Education" {{ old('education_level', $student->education_level) === 'Basic Education' ? 'selected' : '' }}>Basic Education</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Course / Program <span class="text-red-500">*</span></label>
                                <select name="course" x-model="course" @change="grade_level = ''; strand = ''; section = '';" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_student_id') == $student->id) @error('course') border-red-500 @enderror @endif">
                                    <option value="">Select course...</option>
                                    <template x-for="c in coursesFor()" :key="c">
                                        <option :value="c" x-text="c" :selected="c === course"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div class="col-span-1">
                                    <label class="block text-xs font-semibold text-gray-600 mb-1" x-text="isBasicEd() ? 'Grade *' : 'Year *'"></label>
                                    <select name="grade_level" x-model="grade_level" @change="strand = ''; section = ''" required
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        <option value="">Select</option>
                                        <template x-for="g in gradesFor(course)" :key="g">
                                            <option :value="g" x-text="g" :selected="g === grade_level"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Section <span class="text-red-500">*</span></label>
                                    <select name="section" x-model="section" required
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        <option value="">Select...</option>
                                        <template x-for="s in sectionsFor(course, grade_level)" :key="s">
                                            <option :value="s" x-text="s" :selected="s === section"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Status <span class="text-red-500">*</span></label>
                                    <select name="status" required class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        <option value="active" {{ old('status', $student->status) == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ old('status', $student->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                        <option value="transferred" {{ old('status', $student->status) == 'transferred' ? 'selected' : '' }}>Transferred</option>
                                        <option value="graduated" {{ old('status', $student->status) == 'graduated' ? 'selected' : '' }}>Graduated</option>
                                    </select>
                                </div>
                            </div>

                            <div x-show="isSeniorHigh()" x-cloak>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Strand <span class="text-red-500">*</span></label>
                                <select name="strand" x-model="strand" :required="isSeniorHigh()" class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                    <option value="">Select strand...</option>
                                    @foreach(\App\Models\Course::STRANDS as $strandOption)
                                        <option value="{{ $strandOption }}">{{ $strandOption }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <h4 class="font-semibold text-gray-800 border-b border-gray-100 pb-2 mt-6 flex items-center gap-2 text-sm uppercase tracking-wider">
                                <i class="ti ti-users text-green-600"></i> Parent / Guardian
                            </h4>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Parent Name <span class="text-red-500">*</span></label>
                                <input type="text" name="parent_name" value="{{ old('parent_name', $student->parent_name) }}" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Parent Contact <span class="text-red-500">*</span></label>
                                <input type="text" name="parent_contact" value="{{ old('parent_contact', $student->parent_contact) }}" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                            </div>

                            <input type="hidden" name="school_year" value="{{ $student->school_year }}">
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3 pt-5 border-t border-gray-100 bg-gray-50 -mx-8 -mb-8 px-8 py-4 rounded-b-2xl">
                        <button type="button" @click="showEditModal = false" class="px-5 py-2.5 text-sm font-semibold text-gray-600 hover:text-gray-900 transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm shadow-blue-200 flex items-center gap-2">
                            <i class="ti ti-device-floppy"></i> Update Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
    [x-cloak] { display: none !important; }
    @media print {
        body { background: white !important; }
        .sidebar, header { display: none !important; }
        .main-content { margin-left: 0 !important; }
        .shadow-premium { box-shadow: none !important; }
        @page { margin: 1cm; }
    }
</style>
@endpush
