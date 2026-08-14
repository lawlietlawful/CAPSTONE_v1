@extends('layouts.admin')

@section('title', 'Student Risk Profile')
@section('page-title', 'Student Risk Profile')
@section('page-sub', 'Detailed breakdown of early warning indicators and risk assessment')

@section('content')

<div x-data="{ activeModal: null }">

<div class="mb-6">
    <a href="{{ route('admin.risk.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 flex items-center gap-1">
        <i class="ti ti-arrow-left"></i> Back to At-Risk Students
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Left Column: Student Info & Overall Risk -->
    <div class="lg:col-span-1 space-y-6">

        <!-- Student Profile Card -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden relative group">
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-50 to-blue-100 text-blue-600 flex items-center justify-center font-bold text-2xl shrink-0 shadow-sm border border-blue-200/50">
                        {{ strtoupper(substr($student->first_name, 0, 1)) }}
                    </div>
                    <div>
                        <h3 class="text-xl font-bold text-gray-900 leading-tight group-hover:text-blue-600 transition">{{ $student->first_name }} {{ $student->last_name }}</h3>
                        <p class="text-sm text-gray-500 flex items-center gap-1 mt-1 font-medium">
                            <i class="ti ti-id-badge text-gray-400"></i> {{ $student->student_id_number }}
                        </p>
                        <p class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mt-2.5">
                            {{ $student->course ?? $student->grade_level }} &bull; {{ $student->section }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 pt-5 border-t border-gray-100 flex gap-2">
                    <a href="{{ route('admin.students.show', $student->id) }}" class="flex-1 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-xl hover:bg-gray-50 hover:border-gray-300 transition text-center shadow-sm">
                        Full Profile
                    </a>
                    <button type="button" @click="activeModal = 'create'" class="flex-1 py-2 bg-blue-600 border border-blue-600 text-white text-sm font-medium rounded-xl hover:bg-blue-700 transition shadow-sm flex items-center justify-center gap-1.5 w-full">
                        <i class="ti ti-user-plus text-[15px]"></i> Refer
                    </button>
                </div>
            </div>
        </div>

        <!-- Latest Assessment Summary -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-6">
                <h4 class="font-bold text-gray-900 flex items-center gap-2 mb-6">
                    <i class="ti ti-activity text-blue-600"></i> Risk Status Overview
                </h4>
                
                <div class="flex items-end justify-between mb-6 pb-6 border-b border-gray-100">
                    <div>
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Risk Score</p>
                        <div class="flex items-baseline gap-1">
                            <span class="text-4xl font-bold text-gray-900 tracking-tight">{{ number_format($latestAssessment->risk_score, 1) }}</span>
                            <span class="text-sm text-gray-400 font-medium">/ 100</span>
                        </div>
                    </div>
                    <div class="text-right pb-1">
                        @php
                            $levelClass = match($latestAssessment->risk_level) {
                                'high'     => 'bg-red-50 text-red-700 border-red-200 ring-4 ring-red-50/50',
                                'moderate' => 'bg-amber-50 text-amber-700 border-amber-200 ring-4 ring-amber-50/50',
                                'low'      => 'bg-green-50 text-green-700 border-green-200 ring-4 ring-green-50/50',
                                default    => 'bg-gray-50 text-gray-600 border-gray-200 ring-4 ring-gray-50/50',
                            };
                        @endphp
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-[11px] font-bold uppercase tracking-wider border {{ $levelClass }}">
                            {{ $latestAssessment->risk_level }} Risk
                        </span>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="flex justify-between items-center text-sm group">
                        <span class="text-gray-500 font-medium flex items-center gap-2.5"><i class="ti ti-file-export text-gray-400 group-hover:text-blue-500 transition text-base"></i> Previous Referrals</span>
                        <span class="font-bold {{ $latestAssessment->previous_referrals_count >= 3 ? 'text-red-600' : 'text-gray-900' }}">{{ $latestAssessment->previous_referrals_count }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm group">
                        <span class="text-gray-500 font-medium flex items-center gap-2.5"><i class="ti ti-message-report text-gray-400 group-hover:text-blue-500 transition text-base"></i> Incident Reports</span>
                        <span class="font-bold {{ $latestAssessment->behavioral_reports_count > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $latestAssessment->behavioral_reports_count }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm group">
                        <span class="text-gray-500 font-medium flex items-center gap-2.5"><i class="ti ti-tag text-gray-400 group-hover:text-blue-500 transition text-base"></i> Concern Code</span>
                        <span class="font-bold text-gray-900">{{ $latestAssessment->concern_type_encoded }}</span>
                    </div>
                    <div class="flex justify-between items-center text-sm group">
                        <span class="text-gray-500 font-medium flex items-center gap-2.5"><i class="ti ti-clock text-gray-400 group-hover:text-blue-500 transition text-base"></i> Days Since Last</span>
                        <span class="font-bold text-gray-900">{{ $latestAssessment->days_since_last_referral === 999 ? 'N/A' : $latestAssessment->days_since_last_referral }}</span>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 px-6 py-3.5 border-t border-gray-100 text-[11px] text-gray-400 font-medium flex items-center justify-between">
                <span class="uppercase tracking-wider">Last Evaluated</span>
                <span>{{ $latestAssessment->assessed_at->format('M d, Y • h:i A') }}</span>
            </div>
        </div>

        <!-- Risk Factors -->
        @php
            $factors = is_array($latestAssessment->risk_factors) ? $latestAssessment->risk_factors : [];
            $seminarTag = $factors['recommended_seminar_tag'] ?? null;
            $tagLabel = $seminarTag ? ucwords(str_replace('_', ' ', $seminarTag)) : null;
            $reason = $factors['reason'] ?? null;
        @endphp
        @if(($tagLabel && strtolower($tagLabel) !== 'general') || $reason)
        <div class="bg-red-50/80 border border-red-100 rounded-2xl shadow-sm p-6 relative overflow-hidden">
            <div class="absolute -right-4 -top-4 opacity-[0.03]">
                <i class="ti ti-alert-triangle text-8xl text-red-900"></i>
            </div>
            <h4 class="font-bold text-red-900 mb-4 flex items-center gap-2 relative z-10">
                <i class="ti ti-alert-triangle text-red-500"></i> Risk Assessment Details
            </h4>
            <div class="space-y-4 relative z-10">
                @if($tagLabel && strtolower($tagLabel) !== 'general')
                    <div>
                        <span class="block text-[10px] text-red-400 font-semibold uppercase tracking-wider mb-1.5">Recommended Focus</span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white text-red-800 border border-red-200 text-sm font-semibold">
                            <i class="ti ti-target-arrow"></i> {{ $tagLabel }}
                        </span>
                    </div>
                @endif
                @if($reason)
                    <div>
                        <span class="block text-[10px] text-red-400 font-semibold uppercase tracking-wider mb-1.5">Triggering Reason</span>
                        <p class="text-sm text-red-800 font-medium leading-relaxed">{{ $reason }}</p>
                    </div>
                @endif
            </div>
        </div>
        @endif

    </div>

    <!-- Right Column: Detail Breakdowns -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Referral History Summary -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-6 pb-4 border-b border-gray-100">
                <h4 class="font-bold text-gray-900 flex items-center gap-2">
                    <i class="ti ti-file-alert text-amber-500"></i> Referral History
                </h4>
            </div>
            <div class="p-0">
                @php
                    $referrals = $student->referrals->sortByDesc('created_at')->take(5);
                @endphp
                @if($referrals->count() > 0)
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/50">
                                <th class="px-6 py-3 font-semibold text-gray-500 text-[10px] uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 font-semibold text-gray-500 text-[10px] uppercase tracking-wider">Concern Type</th>
                                <th class="px-6 py-3 font-semibold text-gray-500 text-[10px] uppercase tracking-wider text-center">Priority</th>
                                <th class="px-6 py-3 font-semibold text-gray-500 text-[10px] uppercase tracking-wider">Reason</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 text-sm">
                            @foreach($referrals as $referral)
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td class="px-6 py-3 text-gray-900 font-medium whitespace-nowrap">{{ $referral->created_at->format('M d, Y') }}</td>
                                    <td class="px-6 py-3 text-gray-600">{{ ucfirst(str_replace('_', ' ', $referral->concern_type ?? 'other')) }}</td>
                                    <td class="px-6 py-3 text-center">
                                        @php
                                            $priClass = match($referral->priority) {
                                                'high'     => 'bg-red-50 text-red-700 border-red-100',
                                                'moderate' => 'bg-amber-50 text-amber-700 border-amber-100',
                                                default    => 'bg-green-50 text-green-700 border-green-100',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md border text-[10px] font-bold uppercase tracking-wider {{ $priClass }}">
                                            {{ $referral->priority ?? 'low' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-gray-500 text-xs line-clamp-2" title="{{ $referral->display_reason }}">{{ $referral->display_reason }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="py-12 flex flex-col items-center justify-center text-center">
                        <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                            <i class="ti ti-file-check text-gray-300 text-xl"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-900">No referrals recorded</p>
                        <p class="text-xs text-gray-500 mt-1">This student has no referral history on file.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Behavioral Incidents Summary -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-6 pb-4 border-b border-gray-100">
                <h4 class="font-bold text-gray-900 flex items-center gap-2">
                    <i class="ti ti-message-report text-red-500"></i> Behavioral Incidents
                </h4>
            </div>
            <div class="p-0">
                @php
                    $reports = $student->behavioralReports->sortByDesc('incident_date')->take(5);
                @endphp
                @if($reports->count() > 0)
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/50">
                                <th class="px-6 py-3 font-semibold text-gray-500 text-[10px] uppercase tracking-wider">Date</th>
                                <th class="px-6 py-3 font-semibold text-gray-500 text-[10px] uppercase tracking-wider">Type</th>
                                <th class="px-6 py-3 font-semibold text-gray-500 text-[10px] uppercase tracking-wider text-center">Severity</th>
                                <th class="px-6 py-3 font-semibold text-gray-500 text-[10px] uppercase tracking-wider">Description</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 text-sm">
                            @foreach($reports as $report)
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td class="px-6 py-3 text-gray-900 font-medium whitespace-nowrap">{{ $report->incident_date->format('M d, Y') }}</td>
                                    <td class="px-6 py-3 text-gray-600">{{ $report->incident_type }}</td>
                                    <td class="px-6 py-3 text-center">
                                        @php
                                            $sevClass = match($report->severity) {
                                                'severe', 'Critical', 'High' => 'bg-red-50 text-red-700 border-red-100',
                                                'moderate', 'Medium'         => 'bg-amber-50 text-amber-700 border-amber-100',
                                                default                      => 'bg-green-50 text-green-700 border-green-100',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md border text-[10px] font-bold uppercase tracking-wider {{ $sevClass }}">
                                            {{ $report->severity }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3 text-gray-500 text-xs line-clamp-2" title="{{ $report->description }}">{{ $report->description }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <div class="py-12 flex flex-col items-center justify-center text-center">
                        <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center mb-3">
                            <i class="ti ti-shield-check text-gray-300 text-xl"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-900">No behavioral incidents</p>
                        <p class="text-xs text-gray-500 mt-1">This student has a clean behavioral record.</p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Risk Assessment History Graph (Placeholder for Analytics) -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6">
            <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="ti ti-chart-line text-blue-600"></i> Risk Assessment History
            </h4>
            
            <div class="relative h-48 w-full flex items-end gap-2 pb-6 border-b border-l border-gray-200 pl-4">
                @php
                    $history = $student->riskAssessments->sortBy('assessed_at')->take(10);
                    $maxScore = 100; // Assuming 100 is max possible, adjust if different
                @endphp
                
                @if($history->count() > 1)
                    @foreach($history as $hist)
                        @php
                            $heightPercentage = min(100, max(5, ($hist->risk_score / $maxScore) * 100));
                            $barColor = match($hist->risk_level) {
                                'high'     => 'bg-red-500',
                                'moderate' => 'bg-amber-500',
                                default    => 'bg-green-500',
                            };
                        @endphp
                        <div class="flex-1 flex flex-col items-center group relative">
                            <div class="w-full mx-1 rounded-t-sm {{ $barColor }} transition-all opacity-80 group-hover:opacity-100" style="height: {{ $heightPercentage }}%;"></div>
                            <div class="absolute -bottom-6 text-[10px] text-gray-500 rotate-45 origin-left whitespace-nowrap">{{ $hist->assessed_at->format('M d') }}</div>
                            <!-- Tooltip -->
                            <div class="absolute -top-10 bg-gray-900 text-white text-[10px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none z-10 whitespace-nowrap">
                                Score: {{ number_format($hist->risk_score, 1) }}<br>{{ $hist->assessed_at->format('M d, Y') }}
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="absolute inset-0 flex items-center justify-center text-sm text-gray-400">
                        Not enough historical data to generate trend chart.
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>

    <div x-cloak x-show="activeModal === 'create'" x-data="{ referralType: '' }">
        <!-- Create Modal -->
        <div class="fixed inset-0 z-[100] overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" @click="activeModal = null"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block w-full max-w-2xl p-6 text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl sm:p-8 relative z-[101]">
                    <div class="flex justify-between items-center mb-5 border-b border-gray-100 pb-4">
                        <h3 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
                            <i class="ti ti-user-plus text-blue-500"></i> Refer Student to Guidance
                        </h3>
                        <button type="button" @click="activeModal = null" class="text-gray-400 hover:text-gray-600 transition">
                            <i class="ti ti-x text-xl"></i>
                        </button>
                    </div>
                    <form action="{{ route('admin.referrals.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="student_id" value="{{ $student->id }}">
                        <!-- Modal Content -->
                        <div class="space-y-5">
                            <div class="grid grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Referral Type <span class="text-red-500">*</span></label>
                                    <select name="referral_type" required x-model="referralType" class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        <option value="">Select type...</option>
                                        @foreach(\App\Models\Referral::REFERRAL_TYPES as $type)
                                            <option value="{{ $type }}">{{ $type }}</option>
                                        @endforeach
                                    </select>
                                    <div x-show="referralType === 'Other'" x-cloak class="mt-2">
                                        <input type="text" name="referral_type_other" placeholder="Please specify"
                                            class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Priority Level <span class="text-red-500">*</span></label>
                                    <select name="priority" required class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        <option value="low">Low</option>
                                        <option value="moderate">Moderate</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Assign to Counselor</label>
                                <select name="counselor_id" class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                    <option value="">Unassigned (Counselor will pick up)</option>
                                    @isset($counselors)
                                        @foreach($counselors as $c)
                                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                                        @endforeach
                                    @endisset
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Reason for Referral <span class="text-red-500">*</span></label>
                                <textarea name="reason" rows="3" required placeholder="Describe the concern or reason for referring this student..." class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm"></textarea>
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
</div>

@endsection
