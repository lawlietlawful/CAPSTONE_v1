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

                @include('partials.safety-flag', ['safetyFlag' => $safetyFlag ?? null, 'compact' => true])
                @include('partials.case-status', ['caseStatus' => $caseStatus, 'compact' => true])

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
                        <span class="text-gray-500 font-medium flex items-center gap-2.5"><i class="ti ti-tag text-gray-400 group-hover:text-blue-500 transition text-base"></i> Concern Type</span>
                        <span class="font-bold text-gray-900">{{ $concernLabel }}</span>
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

        <!-- Manual review / override -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-6">
                <h4 class="font-bold text-gray-900 flex items-center gap-2 mb-1">
                    <i class="ti ti-adjustments text-blue-600"></i> Review &amp; Override
                </h4>
                <p class="text-xs text-gray-500 mb-4 leading-relaxed">
                    Disagree with the AI score? Set the level yourself. Your name and reason are saved in the assessment history, the earlier assessments are kept, and the automatic re-check leaves it alone for {{ \App\Services\RiskAssessmentService::OVERRIDE_SHIELD_DAYS }} days (a new incident is still assessed normally).
                </p>
                <form action="{{ route('admin.risk.override', $student->id) }}" method="POST" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Risk level</label>
                        <select name="risk_level" required class="w-full rounded-xl border border-gray-300 text-sm px-3 py-2 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200">
                            @foreach(['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High'] as $value => $label)
                                <option value="{{ $value }}" {{ old('risk_level', $latestAssessment->risk_level) === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1.5">Reason <span class="text-red-500">*</span></label>
                        <textarea name="note" rows="3" required minlength="10" maxlength="1000" placeholder="Why is this the right level? (e.g. met with the student and parent — incident was a misunderstanding)"
                                  class="w-full rounded-xl border border-gray-300 text-sm px-3 py-2 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200">{{ old('note') }}</textarea>
                        @error('note') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        @error('risk_level') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="w-full py-2 bg-gray-900 text-white text-sm font-medium rounded-xl hover:bg-gray-800 transition">Save review</button>
                </form>
            </div>
        </div>

        <!-- Risk Factors -->
        @php
            $factors = is_array($latestAssessment->risk_factors) ? $latestAssessment->risk_factors : [];
            $seminarTag = $factors['recommended_seminar_tag'] ?? null;
            $tagLabel = $seminarTag ? ucwords(str_replace('_', ' ', $seminarTag)) : null;
            $reason = $factors['reason'] ?? null;
        @endphp
        @php $heldBy = $factors['held_by_referral_id'] ?? null; @endphp
        @if($heldBy)
        <div class="bg-amber-50 border border-amber-100 rounded-2xl p-5 text-sm text-amber-900">
            <p class="font-bold flex items-center gap-2 mb-1"><i class="ti ti-lock"></i> Score held by an open case</p>
            <p class="text-xs leading-relaxed">
                The latest incident alone scored <strong>{{ number_format($factors['ml_risk_score'] ?? 0, 1) }}</strong>
                ({{ ucfirst($factors['ml_risk_level'] ?? '') }}), but referral <strong>#{{ $heldBy }}</strong> is still unresolved,
                so this student's risk is kept at the level it had when that case was filed. It updates once the referral is resolved or cancelled.
            </p>
        </div>
        @endif

        {{-- Who made a manual review and why lives in the Assessment Log below (and the one-time confirmation after saving); a permanent banner here just repeated it. --}}

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
                        <span class="block text-[10px] text-red-400 font-semibold uppercase tracking-wider mb-1.5">Recommended Seminar</span>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white text-red-800 border border-red-200 text-sm font-semibold">
                            <i class="ti ti-target-arrow"></i> {{ $tagLabel }}
                        </span>
                        @if($seminarTag === 'values_formation')
                            <p class="text-[11px] text-red-400 mt-1.5">Default suggestion — no attendance, academic or bullying concern was detected, so the general character-building seminar is recommended.</p>
                        @endif
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

        <!-- Case summary: the risk profile is for deciding; the full record lives on the Student page -->
        @php
            $allReferrals = $student->referrals->sortByDesc('id')->values();
            $openReferralCount = $allReferrals->whereIn('status', ['pending', 'in_progress'])->count();
            $recentReferrals = $allReferrals->take(3);
            $latestIncident = $student->behavioralReports->sortByDesc('incident_date')->first();
            $lastIntervention = $interventions->first();
        @endphp
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden" data-case-summary>
            <div class="p-6 pb-4 border-b border-gray-100 flex items-center gap-3">
                <h4 class="font-bold text-gray-900 flex items-center gap-2">
                    <i class="ti ti-folder text-amber-500"></i> Case Summary
                </h4>
                <a href="{{ route('admin.students.show', $student->id) }}" class="ml-auto text-xs font-semibold text-blue-600 hover:text-blue-800 whitespace-nowrap">
                    Open full student record &rarr;
                </a>
            </div>

            <div class="grid grid-cols-3 divide-x divide-gray-100 border-b border-gray-100 text-center">
                <div class="py-4">
                    <div class="text-2xl font-bold text-gray-900">{{ $allReferrals->count() }}</div>
                    <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mt-0.5">Referrals</div>
                    <div class="text-[11px] mt-1 {{ $openReferralCount ? 'text-amber-600 font-medium' : 'text-gray-400' }}">{{ $openReferralCount }} open</div>
                </div>
                <div class="py-4">
                    <div class="text-2xl font-bold text-gray-900">{{ $student->behavioralReports->count() }}</div>
                    <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mt-0.5">Incidents</div>
                </div>
                <div class="py-4">
                    <div class="text-2xl font-bold text-gray-900">{{ $interventionCount }}</div>
                    <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest mt-0.5">Interventions</div>
                </div>
            </div>

            <div class="p-6 space-y-5">
                <div>
                    <h5 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Recent referrals</h5>
                    @forelse($recentReferrals as $referral)
                        @php
                            $stClass = match($referral->status) {
                                'pending'     => 'bg-amber-50 text-amber-700 border-amber-100',
                                'in_progress' => 'bg-blue-50 text-blue-700 border-blue-100',
                                'resolved'    => 'bg-green-50 text-green-700 border-green-100',
                                default       => 'bg-gray-50 text-gray-500 border-gray-200',
                            };
                        @endphp
                        <div class="flex items-center gap-3 py-1.5 text-sm">
                            <a href="{{ route('admin.referrals.show', $referral->id) }}" class="font-medium text-gray-900 hover:text-blue-600 whitespace-nowrap">{{ $referral->created_at->format('M d, Y') }}</a>
                            <span class="text-gray-500 truncate flex-1" title="{{ $referral->display_reason }}">{{ $referral->referral_type_label }}</span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md border text-[10px] font-bold uppercase tracking-wider whitespace-nowrap {{ $stClass }}">{{ str_replace('_', ' ', $referral->status) }}</span>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No referrals recorded.</p>
                    @endforelse
                    @if($allReferrals->count() > $recentReferrals->count())
                        <p class="text-xs text-gray-400 mt-1">+ {{ $allReferrals->count() - $recentReferrals->count() }} older in the full record</p>
                    @endif
                </div>

                <div>
                    <h5 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Last intervention</h5>
                    @if($lastIntervention)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="font-medium text-gray-900 whitespace-nowrap">{{ $lastIntervention->intervention_date->format('M d, Y') }}</span>
                            <span class="text-gray-600 truncate flex-1">{{ $lastIntervention->intervention_type }}</span>
                            <span class="text-xs {{ $lastIntervention->outcome ? 'font-semibold uppercase tracking-wider text-gray-700' : 'text-gray-400' }}">{{ $lastIntervention->outcome ? str_replace('_', ' ', $lastIntervention->outcome) : 'Not yet evaluated' }}</span>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">No interventions recorded yet.</p>
                    @endif
                </div>

                <div>
                    <h5 class="text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-2">Latest incident</h5>
                    @if($latestIncident)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="font-medium text-gray-900 whitespace-nowrap">{{ $latestIncident->incident_date->format('M d, Y') }}</span>
                            <span class="text-gray-600 truncate flex-1">{{ $latestIncident->incident_type }}</span>
                            <span class="text-xs font-semibold uppercase tracking-wider text-gray-700">{{ $latestIncident->severity }}</span>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">No behavioral incidents.</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Assessment audit trail -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="p-6 pb-4 border-b border-gray-100">
                <h4 class="font-bold text-gray-900 flex items-center gap-2">
                    <i class="ti ti-history text-gray-500"></i> Assessment Log
                    <span class="ml-auto text-xs font-normal text-gray-400">how the score got here</span>
                </h4>
            </div>
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/50">
                        <th class="px-6 py-3 font-semibold text-gray-500 text-[10px] uppercase tracking-wider">When</th>
                        <th class="px-6 py-3 font-semibold text-gray-500 text-[10px] uppercase tracking-wider text-center">Level</th>
                        <th class="px-6 py-3 font-semibold text-gray-500 text-[10px] uppercase tracking-wider text-center">Score</th>
                        <th class="px-6 py-3 font-semibold text-gray-500 text-[10px] uppercase tracking-wider">Source</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 text-sm">
                    @foreach($assessmentHistory as $entry)
                        @php
                            $ef = is_array($entry->risk_factors) ? $entry->risk_factors : [];
                            $source = match($ef['source'] ?? null) {
                                'override' => 'Manual review by ' . ($ef['override']['by_name'] ?? 'a counselor'),
                                'recheck'  => 'Automatic re-check',
                                'report'   => 'Behavioral report',
                                'referral' => 'New referral',
                                default    => 'Assessment',
                            };
                        @endphp
                        <tr>
                            <td class="px-6 py-3 text-gray-900 font-medium whitespace-nowrap">{{ $entry->assessed_at->format('M d, Y h:i A') }}</td>
                            <td class="px-6 py-3 text-center text-xs font-bold uppercase tracking-wider {{ $entry->risk_level === 'high' ? 'text-red-600' : ($entry->risk_level === 'moderate' ? 'text-amber-600' : 'text-green-600') }}">{{ $entry->risk_level }}</td>
                            <td class="px-6 py-3 text-center font-mono text-gray-700">{{ number_format($entry->risk_score, 1) }}</td>
                            <td class="px-6 py-3 text-xs text-gray-500">
                                {{ $source }}
                                @if(!empty($ef['held_by_referral_id']))<span class="text-amber-600"> &middot; held by referral #{{ $ef['held_by_referral_id'] }}</span>@endif
                                @if(($ef['source'] ?? null) === 'override' && !empty($ef['override']['note']))<div class="text-gray-400 mt-0.5">&ldquo;{{ $ef['override']['note'] }}&rdquo;</div>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Risk Assessment History Graph (Placeholder for Analytics) -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm p-6">
            <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="ti ti-chart-line text-blue-600"></i> Risk Assessment History
            </h4>
            
            <div class="relative h-48 w-full flex items-end gap-2 pb-6 border-b border-l border-gray-200 pl-4">
                @php
                    // The 10 MOST RECENT assessments, oldest first — sortBy()->take(10) kept the oldest 10, freezing the chart once a student passed 10 assessments.
                    $history = $student->riskAssessments->sortByDesc('id')->take(10)->sortBy('id');
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
                        {{-- Tells the server to refuse a second open referral unless the counselor explicitly confirms it. --}}
                        <input type="hidden" name="guard_duplicate" value="1">
                        @php $openReferrals = $student->referrals->whereIn('status', ['pending', 'in_progress']); @endphp
                        <!-- Modal Content -->
                        <div class="space-y-5">
                            @if($openReferrals->isNotEmpty())
                                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-left">
                                    <p class="text-sm font-semibold text-amber-900 flex items-center gap-2"><i class="ti ti-alert-triangle"></i> This student already has {{ $openReferrals->count() }} open {{ Str::plural('referral', $openReferrals->count()) }}</p>
                                    <ul class="mt-2 space-y-1 text-xs text-amber-800">
                                        @foreach($openReferrals as $open)
                                            <li>
                                                <a href="{{ route('admin.referrals.show', $open->id) }}" class="underline font-medium">#{{ $open->id }}</a>
                                                &middot; {{ $open->referral_type_label }} &middot; {{ ucwords(str_replace('_', ' ', $open->status)) }}
                                                &middot; {{ $open->counselor?->name ?? 'Unassigned' }}
                                            </li>
                                        @endforeach
                                    </ul>
                                    <label class="mt-3 flex items-start gap-2 text-xs text-amber-900 cursor-pointer">
                                        <input type="checkbox" name="confirm_duplicate" value="1" required class="mt-0.5 rounded border-amber-300 text-blue-600">
                                        <span>File a separate referral anyway. To hand the existing case to a counselor instead, use <strong>Assign Counselor</strong> on the At-Risk list.</span>
                                    </label>
                                </div>
                            @endif
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
                            </div>
                            <p class="text-[11px] text-gray-400 text-left -mt-2">Priority is set automatically from the AI risk assessment of the reason below.</p>
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
