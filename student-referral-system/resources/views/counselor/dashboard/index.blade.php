@extends('layouts.counselor')

@section('title', 'Counselor Dashboard')
@section('page-title', 'Dashboard Overview')
@section('page-sub', 'Guidance & Counseling Office')

@section('content')

{{-- ── Summary Cards ─────────────────────────────────────────── --}}
<div class="grid grid-cols-4 gap-4 mb-6">

    {{-- Pending Referrals --}}
    <a href="{{ route('counselor.referrals.index') }}" class="bg-white border border-gray-100 rounded-xl p-4 flex flex-col gap-2 shadow-premium transition-all duration-300 hover:-translate-y-1 hover:shadow-hover focus:outline-none cursor-pointer group">
        <div class="w-9 h-9 rounded-lg bg-red-500/10 flex items-center justify-center transition-colors group-hover:bg-red-500/20">
            <i class="ti ti-alert-triangle text-red-600 text-lg"></i>
        </div>
        <div>
            <div class="text-2xl font-bold text-gray-900 tracking-tight">{{ number_format($pendingReferralsCount) }}</div>
            <div class="text-[13px] font-medium text-gray-500 mt-0.5">Pending Referrals</div>
        </div>
        <div class="text-[11px] font-semibold text-red-600 flex items-center gap-1.5 bg-red-50 w-max px-2 py-1 rounded-md mt-1">
            <i class="ti ti-clock"></i> {{ $newPendingToday }} new today
        </div>
    </a>

    {{-- Upcoming Interventions --}}
    <a href="{{ route('counselor.interventions.index') }}" class="bg-white border border-gray-100 rounded-xl p-4 flex flex-col gap-2 shadow-premium transition-all duration-300 hover:-translate-y-1 hover:shadow-hover focus:outline-none cursor-pointer group">
        <div class="w-9 h-9 rounded-lg bg-blue-500/10 flex items-center justify-center transition-colors group-hover:bg-blue-500/20">
            <i class="ti ti-heart-handshake text-blue-600 text-lg"></i>
        </div>
        <div>
            <div class="text-2xl font-bold text-gray-900 tracking-tight">{{ number_format($upcomingInterventionsCount) }}</div>
            <div class="text-[13px] font-medium text-gray-500 mt-0.5">Upcoming Interventions</div>
        </div>
        @if($overdueInterventionsCount > 0)
            <div class="text-[11px] font-semibold text-red-600 flex items-center gap-1.5 bg-red-50 w-max px-2 py-1 rounded-md mt-1">
                <i class="ti ti-alert-circle"></i> {{ $overdueInterventionsCount }} overdue
            </div>
        @else
            <div class="text-[11px] font-semibold text-blue-600 flex items-center gap-1.5 bg-blue-50 w-max px-2 py-1 rounded-md mt-1">
                <i class="ti ti-calendar-event"></i> {{ $interventionsDueThisWeek }} due this week
            </div>
        @endif
    </a>

    {{-- Active Seminars --}}
    <a href="{{ route('counselor.seminars.index') }}" class="bg-white border border-gray-100 rounded-xl p-4 flex flex-col gap-2 shadow-premium transition-all duration-300 hover:-translate-y-1 hover:shadow-hover focus:outline-none cursor-pointer group">
        <div class="w-9 h-9 rounded-lg bg-purple-500/10 flex items-center justify-center transition-colors group-hover:bg-purple-500/20">
            <i class="ti ti-school text-purple-600 text-lg"></i>
        </div>
        <div>
            <div class="text-2xl font-bold text-gray-900 tracking-tight">{{ number_format($activeSeminarsCount) }}</div>
            <div class="text-[13px] font-medium text-gray-500 mt-0.5">Active Seminars</div>
        </div>
        <div class="text-[11px] font-semibold text-purple-600 flex items-center gap-1.5 bg-purple-50 w-max px-2 py-1 rounded-md mt-1">
            <i class="ti ti-broadcast"></i> {{ $ongoingSeminarsCount }} ongoing now
        </div>
    </a>

    {{-- Completed this Month --}}
    @php
        $completedDelta = $completedInterventionsThisMonth - $completedLastMonth;
        $completedDeltaLabel = $completedDelta > 0
            ? '+' . $completedDelta . ' vs last month'
            : ($completedDelta < 0 ? $completedDelta . ' vs last month' : 'same as last month');
    @endphp
    <a href="{{ route('counselor.interventions.index') }}" class="bg-white border border-gray-100 rounded-xl p-4 flex flex-col gap-2 shadow-premium transition-all duration-300 hover:-translate-y-1 hover:shadow-hover focus:outline-none cursor-pointer group">
        <div class="w-9 h-9 rounded-lg bg-emerald-500/10 flex items-center justify-center transition-colors group-hover:bg-emerald-500/20">
            <i class="ti ti-circle-check text-emerald-600 text-lg"></i>
        </div>
        <div>
            <div class="text-2xl font-bold text-gray-900 tracking-tight">{{ number_format($completedInterventionsThisMonth) }}</div>
            <div class="text-[13px] font-medium text-gray-500 mt-0.5">Completed this Month</div>
        </div>
        <div class="text-[11px] font-semibold text-emerald-600 flex items-center gap-1.5 bg-emerald-50 w-max px-2 py-1 rounded-md mt-1">
            <i class="ti ti-trending-up"></i> {{ $completedDeltaLabel }}
        </div>
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ── Left Column: Action Items ────────────────────────────── --}}
    <div class="lg:col-span-2 space-y-6">

        <!-- Overdue Follow-ups -->
        @if($overdueInterventionsCount > 0)
        <div class="bg-white border border-red-100 rounded-2xl shadow-premium overflow-hidden">
            <div class="px-6 py-5 border-b border-red-100 bg-red-50/50 flex justify-between items-center">
                <h3 class="text-[15px] font-semibold text-red-700 flex items-center gap-2">
                    <i class="ti ti-alert-triangle text-red-500"></i> Overdue Follow-ups
                </h3>
                <a href="{{ route('counselor.interventions.index') }}" class="text-xs font-medium text-red-600 hover:text-red-700 bg-red-100 hover:bg-red-200 px-3 py-1.5 rounded-lg transition-colors">View All</a>
            </div>

            <div class="divide-y divide-gray-100">
                @foreach($overdueInterventions as $intervention)
                    @php
                        $daysOverdue = \Carbon\Carbon::parse($intervention->follow_up_date)->diffInDays(\Carbon\Carbon::today());
                    @endphp
                    <div class="p-4 hover:bg-gray-50 transition flex items-start justify-between group">
                        <div class="flex gap-4">
                            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center text-red-700 font-bold flex-shrink-0">
                                {{ strtoupper(substr($intervention->referral->student->first_name ?? '?', 0, 1)) }}
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-900 text-sm">
                                    {{ $intervention->referral->student->first_name ?? 'Unknown' }} {{ $intervention->referral->student->last_name ?? 'Student' }}
                                </h4>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Follow-up was due {{ \Carbon\Carbon::parse($intervention->follow_up_date)->format('M d, Y') }}
                                    · <span class="font-medium text-red-600">{{ $daysOverdue }} day{{ $daysOverdue == 1 ? '' : 's' }} overdue</span>
                                </p>
                                <p class="text-xs text-gray-600 mt-1.5 line-clamp-1 border-l-2 border-red-200 pl-2">
                                    {{ $intervention->intervention_type }}
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('counselor.interventions.show', $intervention->id) }}"
                           class="px-3 py-1.5 text-xs font-medium text-red-600 bg-red-50 rounded hover:bg-red-100 transition opacity-0 group-hover:opacity-100">
                            Follow Up
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
        @endif

        <!-- Pending Referrals List -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100/60 bg-gray-50/50 flex justify-between items-center">
                <h3 class="text-[15px] font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-alert-circle text-gray-400"></i> Action Required: Pending Referrals
                </h3>
                <a href="{{ route('counselor.referrals.index') }}" class="text-xs font-medium text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition-colors">View All</a>
            </div>
            
            <div class="divide-y divide-gray-100">
                @forelse($recentPendingReferrals as $referral)
                    <div class="p-4 hover:bg-gray-50 transition flex items-start justify-between group">
                        <div class="flex gap-4">
                            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center text-red-700 font-bold flex-shrink-0">
                                {{ strtoupper(substr($referral->student->first_name, 0, 1)) }}
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('counselor.referrals.show', $referral->id) }}" class="font-semibold text-gray-900 text-sm hover:text-blue-600 transition">
                                        {{ $referral->student->first_name }} {{ $referral->student->last_name }}
                                    </a>
                                    @if($referral->riskAssessment)
                                        @php
                                            $riskBadgeClass = match($referral->riskAssessment->risk_level) {
                                                'high' => 'bg-red-100 text-red-800',
                                                'moderate' => 'bg-yellow-100 text-yellow-800',
                                                default => 'bg-green-100 text-green-800',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold {{ $riskBadgeClass }}">
                                            <i class="ti ti-brain text-[10px]"></i> {{ ucfirst($referral->riskAssessment->risk_level) }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    Referred by <span class="font-medium text-gray-700">{{ $referral->referredBy->name ?? 'System/Analytics' }}</span> 
                                    · {{ $referral->created_at->diffForHumans() }}
                                </p>
                                <p class="text-xs text-gray-600 mt-1.5 line-clamp-1 border-l-2 border-red-200 pl-2">
                                    "{{ $referral->display_reason }}"
                                </p>
                            </div>
                        </div>
                        <a href="{{ route('counselor.referrals.show', $referral->id) }}" 
                           class="px-3 py-1.5 text-xs font-medium text-blue-600 bg-blue-50 rounded hover:bg-blue-100 transition opacity-0 group-hover:opacity-100">
                            Review
                        </a>
                    </div>
                @empty
                    <div class="p-8 text-center">
                        <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center text-gray-300 mx-auto mb-3">
                            <i class="ti ti-mood-check text-xl"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-900">All caught up!</p>
                        <p class="text-xs text-gray-500">There are no pending referrals to review right now.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Upcoming Interventions (Counseling Sessions) -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100/60 bg-gray-50/50 flex justify-between items-center">
                <h3 class="text-[15px] font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-heart-handshake text-gray-400"></i> Upcoming Counseling Sessions
                </h3>
                <a href="{{ route('counselor.interventions.index') }}" class="text-xs font-medium text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition-colors">View Schedule</a>
            </div>
            
            <div class="divide-y divide-gray-100">
                @forelse($upcomingInterventions as $intervention)
                    <div class="p-4 hover:bg-gray-50 transition flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="bg-blue-50 border border-blue-100 rounded-lg p-2 text-center min-w-[3.5rem]">
                                <span class="block text-[10px] uppercase font-bold text-blue-600 tracking-wider">
                                    {{ \Carbon\Carbon::parse($intervention->follow_up_date)->format('M') }}
                                </span>
                                <span class="block text-lg font-black text-blue-900 leading-none">
                                    {{ \Carbon\Carbon::parse($intervention->follow_up_date)->format('d') }}
                                </span>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-900 text-sm">
                                    {{ $intervention->referral->student->first_name ?? 'Unknown' }} {{ $intervention->referral->student->last_name ?? 'Student' }}
                                </h4>
                                <div class="flex items-center gap-3 text-xs text-gray-500 mt-1">
                                    <span class="flex items-center gap-1"><i class="ti ti-map-pin"></i> 
                                        Follow-up (Guidance)
                                    </span>
                                </div>
                            </div>
                        </div>
                        <a href="{{ route('counselor.interventions.show', $intervention->id) }}" 
                           class="text-xs font-medium text-gray-500 hover:text-blue-600 transition">
                            Details <i class="ti ti-chevron-right align-[-2px]"></i>
                        </a>
                    </div>
                @empty
                    <div class="p-8 text-center">
                        <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center text-gray-300 mx-auto mb-3">
                            <i class="ti ti-calendar-off text-xl"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-900">No sessions scheduled</p>
                        <p class="text-xs text-gray-500">There are no follow-ups scheduled for the upcoming days.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ── Right Column: Seminars & Quick Links ────────────────── --}}
    <div class="lg:col-span-1 space-y-6">
        
        <!-- Active Seminars Mini-Widget -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100/60 bg-gray-50/50 flex justify-between items-center">
                <h3 class="text-[15px] font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-school text-gray-400"></i> Active Seminars
                </h3>
            </div>
            <div class="p-4 space-y-4">
                @forelse($upcomingSeminars as $seminar)
                    <div class="border border-gray-100 rounded-xl p-3 hover:border-purple-200 hover:shadow-sm transition group">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-semibold text-sm text-gray-900 leading-tight group-hover:text-purple-700 transition">
                                {{ $seminar->title }}
                            </h4>
                            @if($seminar->status == 'ongoing')
                                <span class="relative flex h-2.5 w-2.5">
                                  <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-purple-400 opacity-75"></span>
                                  <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-purple-500"></span>
                                </span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-500 flex flex-col gap-1.5">
                            <span class="flex items-center gap-1.5"><i class="ti ti-calendar-event text-gray-400"></i> {{ \Carbon\Carbon::parse($seminar->date)->format('M d, Y') }}</span>
                            <span class="flex items-center gap-1.5"><i class="ti ti-target text-gray-400"></i> {{ $seminar->target_course ?? 'All Courses' }}</span>
                        </div>
                        <div class="mt-3 pt-3 border-t border-gray-50 flex justify-end">
                            <a href="{{ route('counselor.seminars.show', $seminar->id) }}" class="text-xs font-medium text-purple-600 hover:text-purple-800">
                                Manage Attendance →
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-4">
                        <p class="text-xs text-gray-500">No active or upcoming seminars.</p>
                        <a href="{{ route('counselor.seminars.create') }}" class="mt-2 inline-block text-xs font-medium text-purple-600">Create one now</a>
                    </div>
                @endforelse
            </div>
            @if($upcomingSeminars->count() > 0)
                <div class="bg-gray-50 px-5 py-3 border-t border-gray-100 text-center">
                    <a href="{{ route('counselor.seminars.index') }}" class="text-xs font-medium text-gray-600 hover:text-purple-600 transition">View All Seminars</a>
                </div>
            @endif
        </div>

        <!-- Quick Actions -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100/60 bg-gray-50/50">
                <h3 class="text-[15px] font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-bolt text-gray-400"></i> Quick Actions
                </h3>
            </div>
            <div class="p-5 pb-[42px] grid grid-cols-3 gap-3">
                <a href="{{ route('counselor.referrals.create') }}" class="flex flex-col items-center gap-2 p-4 rounded-xl bg-red-50 hover:bg-red-100 transition text-center group">
                    <div class="w-9 h-9 rounded-lg bg-red-500/10 flex items-center justify-center transition-colors group-hover:bg-red-500/20">
                        <i class="ti ti-file-plus text-red-600 text-lg"></i>
                    </div>
                    <span class="text-xs font-medium text-gray-700 leading-tight">New Referral</span>
                </a>
                <a href="{{ route('counselor.seminars.create') }}" class="flex flex-col items-center gap-2 p-4 rounded-xl bg-purple-50 hover:bg-purple-100 transition text-center group">
                    <div class="w-9 h-9 rounded-lg bg-purple-500/10 flex items-center justify-center transition-colors group-hover:bg-purple-500/20">
                        <i class="ti ti-calendar-plus text-purple-600 text-lg"></i>
                    </div>
                    <span class="text-xs font-medium text-gray-700 leading-tight">Schedule Seminar</span>
                </a>
                <a href="{{ route('counselor.interventions.create') }}" class="flex flex-col items-center gap-2 p-4 rounded-xl bg-blue-50 hover:bg-blue-100 transition text-center group">
                    <div class="w-9 h-9 rounded-lg bg-blue-500/10 flex items-center justify-center transition-colors group-hover:bg-blue-500/20">
                        <i class="ti ti-heart-handshake text-blue-600 text-lg"></i>
                    </div>
                    <span class="text-xs font-medium text-gray-700 leading-tight">Log Intervention</span>
                </a>
            </div>
        </div>

        <!-- Risk Distribution (scoped to this counselor's students) -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6 pb-[10.5px]">
            <h2 class="text-[15px] font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="ti ti-chart-pie text-gray-400"></i> Risk Distribution
            </h2>

            <div class="flex h-3 rounded-full overflow-hidden gap-1 mb-5 bg-gray-100">
                <div class="risk-bar-low h-full rounded-full transition-all duration-1000 ease-out"
                     style="width: 0%" data-width="{{ $riskDistribution['low_pct'] }}%"></div>
                <div class="risk-bar-mod h-full rounded-full transition-all duration-1000 ease-out delay-150"
                     style="width: 0%" data-width="{{ $riskDistribution['moderate_pct'] }}%"></div>
                <div class="risk-bar-high h-full rounded-full transition-all duration-1000 ease-out delay-300"
                     style="width: 0%" data-width="{{ $riskDistribution['high_pct'] }}%"></div>
            </div>

            <div class="flex justify-between items-center px-1">
                <div class="flex flex-col items-center gap-1">
                    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium uppercase tracking-wider">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> LOW
                    </div>
                    <span class="text-xl font-bold text-gray-900">{{ $riskDistribution['low'] }}</span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium uppercase tracking-wider">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span> MOD
                    </div>
                    <span class="text-xl font-bold text-gray-900">{{ $riskDistribution['moderate'] }}</span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium uppercase tracking-wider">
                        <span class="w-2 h-2 rounded-full bg-red-500"></span> HIGH
                    </div>
                    <span class="text-xl font-bold text-gray-900">{{ $riskDistribution['high'] }}</span>
                </div>
            </div>
        </div>

    </div>
</div>

@endsection

@push('styles')
<style>
    .risk-bar-low  { background: #16A34A; }
    .risk-bar-mod  { background: #D97706; }
    .risk-bar-high { background: #DC2626; }
</style>
@endpush

@push('scripts')
<script>
    // Trigger Risk Bar Animation on Load
    setTimeout(() => {
        document.querySelectorAll('.risk-bar-low, .risk-bar-mod, .risk-bar-high').forEach(bar => {
            bar.style.width = bar.getAttribute('data-width');
        });
    }, 100);
</script>
@endpush
