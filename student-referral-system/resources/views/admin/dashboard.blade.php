@extends('layouts.admin')

@section('title', 'Admin Overview')
@section('page-title', 'Admin Overview')
@section('page-sub', 'S.Y. 2025–2026 · Second Semester')

@section('content')

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

    {{-- At-Risk Students --}}
    <a href="{{ route('admin.risk.index') }}" class="bg-white border border-gray-100 rounded-xl p-4 flex flex-col gap-2 shadow-premium transition-all duration-300 hover:-translate-y-1 hover:shadow-hover focus:outline-none cursor-pointer group">
        <div class="w-9 h-9 rounded-lg bg-red-500/10 flex items-center justify-center transition-colors group-hover:bg-red-500/20">
            <i class="ti ti-alert-triangle text-red-600 text-lg"></i>
        </div>
        <div>
            <div class="text-2xl font-bold text-gray-900 tracking-tight">{{ number_format($atRiskCount) }}</div>
            <div class="text-[13px] font-medium text-gray-500 mt-0.5">At-Risk Students</div>
        </div>
        <div class="text-[11px] font-semibold text-red-600 flex items-center gap-1.5 bg-red-50 w-max px-2 py-1 rounded-md mt-1">
            <i class="ti ti-trending-up"></i> {{ $newFlagsToday }} new flags today
        </div>
    </a>

    {{-- Pending Referrals --}}
    <a href="{{ route('admin.referrals.index') }}" class="bg-white border border-gray-100 rounded-xl p-4 flex flex-col gap-2 shadow-premium transition-all duration-300 hover:-translate-y-1 hover:shadow-hover focus:outline-none cursor-pointer group">
        <div class="w-9 h-9 rounded-lg bg-amber-500/10 flex items-center justify-center transition-colors group-hover:bg-amber-500/20">
            <i class="ti ti-file-text text-amber-600 text-lg"></i>
        </div>
        <div>
            <div class="text-2xl font-bold text-gray-900 tracking-tight">{{ number_format($pendingReferrals) }}</div>
            <div class="text-[13px] font-medium text-gray-500 mt-0.5">Pending Referrals</div>
        </div>
        <div class="text-[11px] font-semibold text-amber-600 flex items-center gap-1.5 bg-amber-50 w-max px-2 py-1 rounded-md mt-1">
            <i class="ti ti-clock"></i> {{ $awaitingAction }} awaiting action
        </div>
    </a>

    {{-- SMS Sent --}}
    <a href="{{ route('admin.sms-logs.index') }}" class="bg-white border border-gray-100 rounded-xl p-4 flex flex-col gap-2 shadow-premium transition-all duration-300 hover:-translate-y-1 hover:shadow-hover focus:outline-none cursor-pointer group">
        <div class="w-9 h-9 rounded-lg bg-emerald-500/10 flex items-center justify-center transition-colors group-hover:bg-emerald-500/20">
            <i class="ti ti-message-2 text-emerald-600 text-lg"></i>
        </div>
        <div>
            <div class="text-2xl font-bold text-gray-900 tracking-tight">{{ number_format($smsSentThisMonth) }}</div>
            <div class="text-[13px] font-medium text-gray-500 mt-0.5">SMS Sent This Month</div>
        </div>
        <div class="text-[11px] font-semibold text-emerald-600 flex items-center gap-1.5 bg-emerald-50 w-max px-2 py-1 rounded-md mt-1">
            <i class="ti ti-chart-dots"></i> {{ $smsDeliveryRate }}% delivery rate
        </div>
    </a>

</div>

{{-- ── Main Row: Referrals Table + Right Column ────────────── --}}
<div class="grid grid-cols-3 gap-6 mb-6">

    {{-- Recent Referrals Table --}}
    <div class="col-span-2 bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden h-full flex flex-col">
        <div class="px-6 py-5 border-b border-gray-100/60 bg-gray-50/50 flex items-center justify-between">
            <h2 class="text-[15px] font-semibold text-gray-800 flex items-center gap-2">
                <i class="ti ti-history text-gray-400"></i> Recent Referrals
            </h2>
            <a href="{{ route('admin.referrals.index') }}"
               class="text-xs font-medium text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-lg transition-colors">View All</a>
        </div>

        <div class="overflow-x-auto flex-1">
            <table class="w-full text-sm table-fixed">
                <thead class="bg-gray-50/30">
                    <tr>
                        <th class="text-left text-[11px] text-gray-400 font-semibold uppercase tracking-wider py-3 px-6 border-b border-gray-100 w-[28%]">Student</th>
                        <th class="text-left text-[11px] text-gray-400 font-semibold uppercase tracking-wider py-3 px-6 border-b border-gray-100 w-[22%]">Year & Section</th>
                        <th class="text-left text-[11px] text-gray-400 font-semibold uppercase tracking-wider py-3 px-6 border-b border-gray-100 w-[22%]">Reason</th>
                        <th class="text-left text-[11px] text-gray-400 font-semibold uppercase tracking-wider py-3 px-6 border-b border-gray-100 w-[14%]">Risk</th>
                        <th class="text-left text-[11px] text-gray-400 font-semibold uppercase tracking-wider py-3 px-6 border-b border-gray-100 w-[14%]">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($recentReferrals as $referral)
                    <tr class="hover:bg-blue-50/30 transition-colors duration-150">
                        <td class="py-3 px-6 text-sm font-medium text-gray-800 truncate">
                            {{ $referral->student->full_name }}
                        </td>
                        <td class="py-3 px-6 text-xs text-gray-500 truncate">
                            {{ $referral->student->grade_level }} — {{ $referral->student->section }}
                        </td>
                        <td class="py-3 px-6 text-xs text-gray-500 truncate" title="{{ $referral->referral_type_label }}">
                            {{ $referral->referral_type_label }}
                        </td>
                        <td class="py-3 px-6">
                            @php
                                $riskClass = match($referral->priority) {
                                    'high'     => 'bg-red-50 text-red-700 border-red-100',
                                    'moderate' => 'bg-amber-50 text-amber-700 border-amber-100',
                                    'low'      => 'bg-emerald-50 text-emerald-700 border-emerald-100',
                                    default    => 'bg-gray-50 text-gray-700 border-gray-100',
                                };
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium border {{ $riskClass }}">
                                {{ ucfirst($referral->priority) }}
                            </span>
                        </td>
                        <td class="py-3 px-6">
                            @php
                                $statusClass = match($referral->status) {
                                    'pending'     => 'bg-blue-50 text-blue-600',
                                    'in_progress' => 'bg-amber-50 text-amber-600',
                                    'resolved'    => 'bg-emerald-50 text-emerald-600',
                                    'cancelled'   => 'bg-gray-50 text-gray-600',
                                    default       => 'bg-gray-50 text-gray-600',
                                };
                                $statusLabel = match($referral->status) {
                                    'pending'     => 'Pending',
                                    'in_progress' => 'In Progress',
                                    'resolved'    => 'Resolved',
                                    'cancelled'   => 'Cancelled',
                                    default       => ucfirst($referral->status),
                                };
                            @endphp
                            <div class="flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full {{ str_replace('bg-', 'bg-', str_replace('text-', 'bg-', $statusClass)) }} opacity-75"></span>
                                <span class="text-xs font-medium text-gray-700">
                                    {{ $statusLabel }}
                                </span>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="py-12 text-center">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-50 mb-3">
                                <i class="ti ti-inbox text-xl text-gray-400"></i>
                            </div>
                            <p class="text-sm font-medium text-gray-900 mb-1">No referrals yet</p>
                            <p class="text-xs text-gray-500 mt-1">When teachers submit referrals, they will appear here.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Right Column --}}
    <div class="flex flex-col gap-6 h-full">

        {{-- Risk Distribution --}}
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6">
            <h2 class="text-[15px] font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="ti ti-chart-pie text-gray-400"></i> Risk Distribution
            </h2>

            {{-- Segmented bar with animation --}}
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

        @include('partials.attention-card', ['attentionTiles' => $attentionTiles ?? []])

        {{-- Upcoming Seminars --}}
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6 flex-1 flex flex-col">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-[15px] font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-calendar-event text-gray-400"></i> Upcoming Seminars
                </h2>
                <a href="{{ route('admin.seminars.index') }}"
                   class="text-xs font-medium text-blue-600 hover:text-blue-700 bg-blue-50 hover:bg-blue-100 px-2 py-1 rounded transition-colors">Manage</a>
            </div>

            <div class="flex-1">
                @forelse($upcomingSeminars as $seminar)
                <div class="flex items-center gap-3 py-3
                            {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center
                                justify-center flex-shrink-0">
                        <i class="ti ti-school text-blue-600 text-lg"></i>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $seminar->title }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $seminar->date->format('M d') }} · {{ \Carbon\Carbon::parse($seminar->time)->format('g:i A') }}
                        </p>
                    </div>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold tracking-wider uppercase
                        {{ $seminar->is_required ? 'bg-red-50 text-red-600' : 'bg-emerald-50 text-emerald-600' }}">
                        {{ $seminar->is_required ? 'Required' : 'Optional' }}
                    </span>
                </div>
                @empty
                <div class="text-center py-6">
                    <p class="text-xs font-medium text-gray-500">No upcoming seminars.</p>
                </div>
                @endforelse
            </div>
        </div>

    </div>
</div>

{{-- ── Bottom Row: Chart + Activity Feed ──────────────────── --}}
<div class="grid grid-cols-2 gap-6">

    {{-- Monthly Referral Trend Chart --}}
    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6 h-full flex flex-col">
        <h2 class="text-[15px] font-semibold text-gray-800 mb-5 flex items-center gap-2">
            <i class="ti ti-chart-line text-gray-400"></i> Monthly Referral Trend
        </h2>

        <div class="flex gap-4 mb-4">
            <span class="flex items-center gap-2 text-xs font-medium text-gray-600 uppercase tracking-wider">
                <span class="w-3 h-3 rounded-full bg-blue-600 shadow-[0_0_6px_rgba(37,99,235,0.4)]"></span>
                Referrals
            </span>
            <span class="flex items-center gap-2 text-xs font-medium text-gray-600 uppercase tracking-wider">
                <span class="w-3 h-3 rounded-full bg-emerald-500 shadow-[0_0_6px_rgba(16,185,129,0.4)]"></span>
                Resolved
            </span>
        </div>

        <div class="relative w-full flex-1 min-h-[220px]">
            <canvas id="referralChart"
                    role="img"
                    aria-label="Bar chart showing monthly referrals vs resolved from January to June">
                Monthly referrals and resolved counts per month.
            </canvas>
        </div>
    </div>

    {{-- Recent Activity Feed --}}
    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6">
        <h2 class="text-[15px] font-semibold text-gray-800 mb-5 flex items-center gap-2">
            <i class="ti ti-activity text-gray-400"></i> System Activity Log
        </h2>

        <div class="overflow-y-auto pr-2 custom-scrollbar max-h-[240px]">
            <div class="flex flex-col relative before:absolute before:inset-y-0 before:left-2 before:w-[2px] before:bg-gray-100">
                @forelse($recentActivities as $activity)
                <div class="flex gap-4 py-3 relative">
                @php
                    $dotColor = match($activity['type']) {
                        'risk'         => 'bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.5)]',
                        'sms'          => 'bg-blue-500 shadow-[0_0_8px_rgba(59,130,246,0.5)]',
                        'resolved'     => 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]',
                        'behavioral'   => 'bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.5)]',
                        'seminar'      => 'bg-indigo-500 shadow-[0_0_8px_rgba(99,102,241,0.5)]',
                        default        => 'bg-gray-400',
                    };
                @endphp
                <div class="w-4 h-4 rounded-full mt-0.5 flex-shrink-0 z-10 border-[3px] border-white {{ $dotColor }}"></div>
                <div class="bg-gray-50/50 hover:bg-gray-50 p-3 rounded-xl flex-1 transition-colors border border-gray-100/50">
                    <p class="text-xs text-gray-700 leading-snug font-medium">{!! $activity['message'] !!}</p>
                    <p class="text-[10px] text-gray-400 mt-1 uppercase tracking-wider font-semibold flex items-center gap-1">
                        <i class="ti ti-clock text-gray-400"></i> {{ $activity['time'] }}
                    </p>
                </div>
            </div>
            @empty
            <p class="text-xs text-gray-400 text-center py-6">No recent activity.</p>
            @endforelse
        </div>
        </div>
    </div>

</div>

{{-- ── Floating AI Insights Widget ───────────────────────────── --}}
<div class="fixed bottom-6 right-6 z-50 flex flex-col items-end">
    
    {{-- Hidden Pop-up Card --}}
    <div id="ai-insights-popup" class="mb-4 w-[340px] bg-[#303f9f] rounded-2xl shadow-2xl p-5 relative overflow-hidden transition-all duration-300 origin-bottom-right scale-0 opacity-0 border border-indigo-400/30">
        
        <button onclick="toggleAiInsights()" class="absolute top-4 right-4 text-indigo-200 hover:text-white transition-colors">
            <i class="ti ti-x text-lg"></i>
        </button>

        <h3 class="font-bold text-lg mb-3 flex items-center gap-2 text-white">
            <i class="ti ti-brain text-2xl text-indigo-300"></i> AI System Insights
        </h3>
        
        <div class="mb-4 inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold bg-indigo-500/50 text-indigo-100 uppercase tracking-widest border border-indigo-400/50">
            Live Monitoring
        </div>
        
        @if(!empty($aiPredictiveInsights))
            @php
                $topFactor = array_key_first($aiPredictiveInsights);
                $topPct = $aiPredictiveInsights[$topFactor];
            @endphp
            <p class="text-[15px] leading-relaxed text-indigo-50 mb-5">
                Based on historical patterns, the engine detects a <strong class="font-bold text-white">{{ $topPct }}% increase in high risk behaviors</strong> associated with <strong class="font-bold text-white">{{ strtolower($topFactor) }}</strong>.
            </p>
            <div class="bg-indigo-900/40 rounded-xl p-4 border border-indigo-500/30">
                <p class="text-sm text-indigo-100">
                    <i class="ti ti-bulb text-yellow-400 mr-1"></i>
                    <span class="font-semibold text-white">Recommendation:</span> Schedule an "{{ $topFactor == 'Excessive Absences' ? 'Attendance & Time Management' : 'Academic Resilience' }}" seminar.
                </p>
            </div>
        @else
            <p class="text-[15px] leading-relaxed text-indigo-50">
                Not enough risk data collected yet to generate a school-wide pattern.
            </p>
        @endif
    </div>

    {{-- Floating Toggle Button --}}
    <button onclick="toggleAiInsights()" class="w-14 h-14 bg-[#2563eb] rounded-full shadow-[0_8px_20px_rgba(37,99,235,0.4)] flex items-center justify-center text-white hover:bg-blue-700 transition-all transform hover:scale-105 hover:-translate-y-1 relative group focus:outline-none focus:ring-4 focus:ring-blue-500/30">
        <i class="ti ti-brain text-3xl"></i>
        
        {{-- Notification Dot --}}
        <span class="absolute top-0 right-0 w-3.5 h-3.5 bg-red-500 border-2 border-white rounded-full animate-pulse"></span>
    </button>
</div>

@endsection

@push('scripts')
<script>
    // Trigger Risk Bar Animation on Load
    setTimeout(() => {
        document.querySelectorAll('.risk-bar-low, .risk-bar-mod, .risk-bar-high').forEach(bar => {
            bar.style.width = bar.getAttribute('data-width');
        });
    }, 100);

    // Toggle AI Insights Pop-up
    function toggleAiInsights() {
        const popup = document.getElementById('ai-insights-popup');
        if (popup.classList.contains('scale-0')) {
            popup.classList.remove('scale-0', 'opacity-0');
            popup.classList.add('scale-100', 'opacity-100');
        } else {
            popup.classList.add('scale-0', 'opacity-0');
            popup.classList.remove('scale-100', 'opacity-100');
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('referralChart');
        if (!ctx) return;
        
        const referralData  = @json($monthlyReferrals);
        const resolvedData  = @json($monthlyResolved);
        const monthLabels   = @json($monthLabels);

        new Chart(ctx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [
                    {
                        label: 'Referrals',
                        data: referralData,
                        backgroundColor: '#2563EB',
                        borderRadius: 4,
                        barPercentage: 0.6,
                    },
                    {
                        label: 'Resolved',
                        data: resolvedData,
                        backgroundColor: '#16A34A',
                        borderRadius: 4,
                        barPercentage: 0.6,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { bodyFont: { size: 12 } }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 11 }, color: '#9CA3AF' },
                        border: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.04)' },
                        ticks: { font: { size: 11 }, color: '#9CA3AF', stepSize: 5 },
                        border: { display: false }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index',
                },
            }
        });
    });
</script>
@endpush
