{{--
    The counselor dashboard's actual content, split out of index.blade.php so
    CounselorDashboardController@refresh (polled every 20s by the script at
    the bottom of index.blade.php) can return just this and have it swapped
    into #dashboard-body without a full page reload — the same data, the
    same Blade template, so this can never drift out of sync with what a
    fresh page load would show.
--}}

{{-- ── Stat Cards ──────────────────────────────────────────── --}}
<div class="grid grid-cols-4 gap-4 mb-6">

    {{-- Total Students --}}
    <a href="{{ route('admin.students.index') }}" class="bg-white border border-gray-100 rounded-xl p-4 flex flex-col gap-2 shadow-premium transition-all duration-300 hover:-translate-y-1 hover:shadow-hover focus:outline-none cursor-pointer group">
        <div class="w-9 h-9 rounded-lg bg-blue-500/10 flex items-center justify-center transition-colors group-hover:bg-blue-500/20">
            <i class="ti ti-users text-blue-600 text-lg"></i>
        </div>
        <div>
            <div class="text-2xl font-bold text-gray-900 tracking-tight">{{ number_format($totalStudents) }}</div>
            <div class="text-[13px] font-medium text-gray-500 mt-0.5">Total Students</div>
        </div>
        <div class="text-[11px] font-semibold text-emerald-600 flex items-center gap-1.5 bg-emerald-50 w-max px-2 py-1 rounded-md mt-1">
            <i class="ti ti-trending-up"></i> {{ $newStudentsThisWeek }} enrolled this week
        </div>
    </a>

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
        <div class="w-9 h-9 rounded-lg bg-amber-500/10 flex items-center justify-center transition-colors group-hover:bg-amber-500/20">
            <i class="ti ti-heart-handshake text-amber-600 text-lg"></i>
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
            <div class="text-[11px] font-semibold text-amber-600 flex items-center gap-1.5 bg-amber-50 w-max px-2 py-1 rounded-md mt-1">
                <i class="ti ti-calendar-event"></i> {{ $interventionsDueThisWeek }} due this week
            </div>
        @endif
    </a>

    {{-- Behavioral Reports Today --}}
    <a href="{{ route('counselor.behavioral-reports.index') }}" class="bg-white border border-gray-100 rounded-xl p-4 flex flex-col gap-2 shadow-premium transition-all duration-300 hover:-translate-y-1 hover:shadow-hover focus:outline-none cursor-pointer group">
        <div class="w-9 h-9 rounded-lg bg-emerald-500/10 flex items-center justify-center transition-colors group-hover:bg-emerald-500/20">
            <i class="ti ti-message-report text-emerald-600 text-lg"></i>
        </div>
        <div>
            <div class="text-2xl font-bold text-gray-900 tracking-tight">{{ number_format($behavioralReportsToday) }}</div>
            <div class="text-[13px] font-medium text-gray-500 mt-0.5">Reports Today</div>
        </div>
        <div class="text-[11px] font-semibold text-emerald-600 flex items-center gap-1.5 bg-emerald-50 w-max px-2 py-1 rounded-md mt-1">
            <i class="ti ti-chart-dots"></i> {{ $behavioralReportsThisWeek }} this week
        </div>
    </a>

</div>

@include('partials.attention-tiles', ['attentionTiles' => $attentionTiles ?? []])

{{-- ── Main Row: Left + Right Columns ────────────────────────── --}}
<div class="grid grid-cols-3 gap-6 mb-6">

    {{-- ── Left Column (2/3): Action Items ────────────────────── --}}
    <div class="col-span-2 space-y-6">

        <!-- Overdue Follow-ups (Urgent Alert) -->
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

        <!-- Pending Referrals -->
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
                                {{ strtoupper(substr($referral->student->first_name ?? '?', 0, 1)) }}
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('counselor.referrals.show', $referral->id) }}" class="font-semibold text-gray-900 text-sm hover:text-blue-600 transition">
                                        {{ $referral->student->first_name ?? 'Unknown' }} {{ $referral->student->last_name ?? 'Student' }}
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

        <!-- Today's Itinerary -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100/60 bg-gray-50/50 flex justify-between items-center">
                <h3 class="text-[15px] font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-calendar-due text-gray-400"></i> Today's Itinerary
                </h3>
                <a href="{{ route('counselor.interventions.index') }}" class="text-xs font-medium text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition-colors">View Schedule</a>
            </div>
            <div class="p-4 relative">
                @forelse($todaysInterventions as $intervention)
                    <div class="flex gap-4 relative pb-6 last:pb-0">
                        <div class="absolute left-[19px] top-8 bottom-0 w-px bg-gray-200 last:hidden"></div>
                        <div class="w-10 h-10 rounded-full bg-blue-50 border-4 border-white flex items-center justify-center text-blue-600 font-bold flex-shrink-0 z-10 shadow-sm">
                            <i class="ti ti-clock text-lg"></i>
                        </div>
                        <div class="pt-2">
                            <h4 class="font-semibold text-gray-900 text-sm">
                                {{ $intervention->referral->student->first_name ?? 'Unknown' }} {{ $intervention->referral->student->last_name ?? 'Student' }}
                            </h4>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $intervention->intervention_type }}</p>
                            <a href="{{ route('counselor.interventions.show', $intervention->id) }}" class="inline-block mt-2 text-xs font-medium text-blue-600 hover:text-blue-700">
                                Open Case &rarr;
                            </a>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center">
                        <div class="w-12 h-12 bg-gray-50 rounded-full flex items-center justify-center text-gray-300 mx-auto mb-3">
                            <i class="ti ti-calendar-off text-xl"></i>
                        </div>
                        <p class="text-sm font-medium text-gray-900">Schedule is clear!</p>
                        <p class="text-xs text-gray-500">You have no follow-ups scheduled for today.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Upcoming Follow-ups (after today) -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100/60 bg-gray-50/50 flex justify-between items-center">
                <h3 class="text-[15px] font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-calendar-time text-gray-400"></i> Upcoming Follow-ups
                </h3>
                <a href="{{ route('counselor.interventions.index') }}" class="text-xs font-medium text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition-colors">View All</a>
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
                                <p class="text-xs text-gray-500 mt-0.5">{{ $intervention->intervention_type }}</p>
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
                        <p class="text-sm font-medium text-gray-900">Nothing else scheduled</p>
                        <p class="text-xs text-gray-500">No upcoming follow-ups beyond today.</p>
                    </div>
                @endforelse
            </div>
        </div>

    </div>

    {{-- ── Right Column (1/3): Widgets ────────────────────────── --}}
    <div class="col-span-1 space-y-6">

        <!-- High-Risk Watchlist -->
        <div class="bg-white border border-red-100 rounded-2xl shadow-premium overflow-hidden">
            <div class="px-6 py-4 border-b border-red-100 bg-red-50/50 flex justify-between items-center">
                <h3 class="text-[14px] font-semibold text-red-800 flex items-center gap-2">
                    <i class="ti ti-radar text-red-500"></i> High-Risk Watchlist
                </h3>
                <a href="{{ route('admin.risk.index', ['scope' => 'mine']) }}" class="text-xs font-medium text-red-600 hover:text-red-700">View All</a>
            </div>
            <div class="divide-y divide-gray-100">
                @forelse($watchlistAssessments as $assessment)
                    @continue(!$assessment->student)
                    <div class="p-4 hover:bg-gray-50 transition flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h4 class="font-semibold text-gray-900 text-sm truncate">{{ $assessment->student->first_name }} {{ $assessment->student->last_name }}</h4>
                                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-800 flex-shrink-0">
                                    <i class="ti ti-brain text-[10px]"></i> {{ number_format($assessment->risk_score, 0) }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $assessment->student->course }} - {{ $assessment->student->grade_level }}</p>
                            @if(!empty($assessment->risk_factors['reason']))
                                <p class="text-[11px] text-gray-600 mt-1 truncate" title="{{ $assessment->risk_factors['reason'] }}">
                                    {{ $assessment->risk_factors['reason'] }}
                                </p>
                            @endif
                            <p class="text-[10px] text-gray-400 mt-1">Flagged {{ $assessment->assessed_at->diffForHumans() }}</p>
                        </div>
                        <a href="{{ route('admin.students.show', $assessment->student->id) }}" class="text-gray-400 hover:text-red-600 transition p-1 flex-shrink-0" title="View Profile">
                            <i class="ti ti-eye"></i>
                        </a>
                    </div>
                @empty
                    <div class="p-4 text-center">
                        <p class="text-xs text-gray-500">No high-risk students flagged.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Recent Activity Stream -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                <h3 class="text-[14px] font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-activity text-gray-400"></i> Recent Activity
                </h3>
            </div>
            <div class="p-4 space-y-4">
                @forelse($recentActivity as $activity)
                    <div class="flex gap-3">
                        <div class="mt-1">
                            @if($activity->type === 'referral')
                                <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center text-blue-600">
                                    <i class="ti ti-file-text text-[11px]"></i>
                                </div>
                            @else
                                <div class="w-6 h-6 rounded-full bg-amber-100 flex items-center justify-center text-amber-600">
                                    <i class="ti ti-message-report text-[11px]"></i>
                                </div>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4 class="font-semibold text-gray-900 text-xs">{{ $activity->title }}</h4>
                            <p class="text-[11px] text-gray-500 mt-0.5 truncate">{{ $activity->description }}</p>
                            <a href="{{ $activity->url }}" class="text-[10px] text-gray-400 hover:text-blue-600 mt-1 inline-block">{{ $activity->date->diffForHumans() }} &rarr;</a>
                        </div>
                    </div>
                @empty
                    <div class="text-center">
                        <p class="text-xs text-gray-500">No recent activity.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Risk Distribution -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6">
            <h2 class="text-[15px] font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="ti ti-chart-pie text-gray-400"></i> Risk Distribution
            </h2>
            <div class="flex h-3 rounded-full overflow-hidden gap-1 mb-5 bg-gray-100">
                <div class="risk-bar-low h-full rounded-full transition-all duration-1000 ease-out"
                     style="width: {{ $riskDistribution['low_pct'] }}%" data-width="{{ $riskDistribution['low_pct'] }}%"></div>
                <div class="risk-bar-mod h-full rounded-full transition-all duration-1000 ease-out"
                     style="width: {{ $riskDistribution['moderate_pct'] }}%" data-width="{{ $riskDistribution['moderate_pct'] }}%"></div>
                <div class="risk-bar-high h-full rounded-full transition-all duration-1000 ease-out"
                     style="width: {{ $riskDistribution['high_pct'] }}%" data-width="{{ $riskDistribution['high_pct'] }}%"></div>
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
