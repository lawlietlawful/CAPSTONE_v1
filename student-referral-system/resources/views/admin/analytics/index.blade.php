@extends('layouts.admin')

@section('title', 'Analytics Dashboard')
@section('page-title', 'Analytics Dashboard')
@section('page-sub', 'System-wide behavioral insights and trends.')

@section('content')

{{-- ── KPI Cards ──────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-3 mb-5">

    <a href="{{ route('admin.referrals.index') }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-blue-200 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
            <i class="ti ti-file-text text-blue-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-blue-700 leading-none">{{ number_format($totalReferrals) }}</div>
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">Total Referrals</div>
        </div>
    </a>

    <a href="{{ route('admin.risk.index', ['risk_level' => 'high']) }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-red-200 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center shrink-0">
            <i class="ti ti-alert-triangle text-red-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-red-700 leading-none">{{ number_format($highRiskStudents) }}</div>
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">High Risk Identified</div>
        </div>
    </a>

    <a href="{{ route('admin.referrals.index', ['status' => 'resolved']) }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-emerald-200 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
            <i class="ti ti-circle-check text-emerald-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-emerald-700 leading-none">{{ number_format($totalResolved) }}</div>
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">Resolved Cases</div>
        </div>
    </a>

    <div class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">
            <i class="ti ti-flag text-amber-600 text-lg"></i>
        </div>
        <div class="min-w-0">
            <div class="text-lg font-bold text-amber-700 leading-none truncate" title="{{ $topConcernType }}">{{ $topConcernType }}</div>
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">Top Concern</div>
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-indigo-50 flex items-center justify-center shrink-0">
            <i class="ti ti-clock-hour-4 text-indigo-600 text-lg"></i>
        </div>
        <div class="min-w-0">
            <div class="text-lg font-bold text-indigo-700 leading-none">
                {{ $avgResolutionDays !== null ? $avgResolutionDays . ' days' : 'N/A' }}
            </div>
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">Avg. Resolution Time</div>
        </div>
    </div>

</div>

{{-- ── Date Range Filter ─────────────────────────────────── --}}
<div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-4 mb-5">
    <form method="GET" action="{{ route('admin.analytics.index') }}" class="flex flex-wrap gap-3 items-end" x-data="{ range: '{{ $dateRange }}' }">
        <div class="w-full lg:w-56">
            <label class="block text-xs font-medium text-gray-500 mb-1">Date Range</label>
            <select name="date_range" x-model="range" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                <option value="all_time" {{ $dateRange == 'all_time' ? 'selected' : '' }}>All Time</option>
                <option value="today" {{ $dateRange == 'today' ? 'selected' : '' }}>Today</option>
                <option value="this_week" {{ $dateRange == 'this_week' ? 'selected' : '' }}>This Week</option>
                <option value="this_month" {{ $dateRange == 'this_month' ? 'selected' : '' }}>This Month</option>
                <option value="last_month" {{ $dateRange == 'last_month' ? 'selected' : '' }}>Last Month</option>
                <option value="this_semester" {{ $dateRange == 'this_semester' ? 'selected' : '' }}>This Semester</option>
                <option value="custom" {{ $dateRange == 'custom' ? 'selected' : '' }}>Custom Range</option>
            </select>
        </div>

        <div class="w-full lg:w-44" x-show="range === 'custom'" x-cloak>
            <label class="block text-xs font-medium text-gray-500 mb-1">Start Date</label>
            <input type="date" name="start_date" value="{{ $customStartDate }}"
                class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
        </div>
        <div class="w-full lg:w-44" x-show="range === 'custom'" x-cloak>
            <label class="block text-xs font-medium text-gray-500 mb-1">End Date</label>
            <input type="date" name="end_date" value="{{ $customEndDate }}"
                class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
        </div>

        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-1.5 h-[38px]">
                <i class="ti ti-filter"></i> Apply
            </button>
            @if($dateRange !== 'all_time')
            <a href="{{ route('admin.analytics.index') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5 h-[38px]">
                <i class="ti ti-x"></i> Clear
            </a>
            @endif
        </div>

        <a href="{{ route('admin.analytics.export-pdf', request()->query()) }}"
           class="ml-auto px-4 py-2 bg-gray-900 text-white text-sm font-medium rounded-lg hover:bg-gray-800 transition shadow-sm flex items-center gap-1.5 h-[38px]">
            <i class="ti ti-file-type-pdf"></i> Download Report
        </a>

        @if($dateRange === 'this_semester' && !$semesterConfigured)
        <div class="w-full">
            <p class="text-xs text-amber-600 flex items-center gap-1.5 mt-1">
                <i class="ti ti-alert-triangle"></i> Semester dates aren't configured yet — showing All Time instead.
                <a href="{{ route('admin.settings.index') }}#general" class="underline font-medium">Set them in Settings</a>
            </p>
        </div>
        @endif
    </form>
</div>

{{-- ── Seminar Intervention Effectiveness ─────────────────── --}}
<div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden mb-6">
    <div class="px-6 py-5 border-b border-gray-100/60 bg-gray-50/50">
        <h2 class="text-[15px] font-semibold text-gray-800 flex items-center gap-2">
            <i class="ti ti-brain text-gray-400"></i> Seminar Intervention Effectiveness
        </h2>
    </div>
    <div class="p-6">
        @if($seminarEffectivenessTotal > 0)
            <p class="text-sm text-gray-600 mb-4">
                Of students tracked 30 days after attending an assigned seminar,
                <strong class="text-emerald-600">{{ $seminarEffectivenessPct }}%</strong> showed an improved risk score.
            </p>

            <div class="flex h-3 rounded-full overflow-hidden gap-1 mb-5 bg-gray-100">
                <div class="bg-emerald-500 h-full" style="width: {{ $seminarEffectiveness['improved'] / $seminarEffectivenessTotal * 100 }}%"></div>
                <div class="bg-gray-400 h-full" style="width: {{ $seminarEffectiveness['no_change'] / $seminarEffectivenessTotal * 100 }}%"></div>
                <div class="bg-red-500 h-full" style="width: {{ $seminarEffectiveness['worse'] / $seminarEffectivenessTotal * 100 }}%"></div>
            </div>

            <div class="flex justify-between items-center px-1">
                <div class="flex flex-col items-center gap-1">
                    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium uppercase tracking-wider">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Improved
                    </div>
                    <span class="text-xl font-bold text-gray-900">{{ $seminarEffectiveness['improved'] }}</span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium uppercase tracking-wider">
                        <span class="w-2 h-2 rounded-full bg-gray-400"></span> No Change
                    </div>
                    <span class="text-xl font-bold text-gray-900">{{ $seminarEffectiveness['no_change'] }}</span>
                </div>
                <div class="flex flex-col items-center gap-1">
                    <div class="flex items-center gap-1.5 text-xs text-gray-500 font-medium uppercase tracking-wider">
                        <span class="w-2 h-2 rounded-full bg-red-500"></span> Worse
                    </div>
                    <span class="text-xl font-bold text-gray-900">{{ $seminarEffectiveness['worse'] }}</span>
                </div>
            </div>
        @else
            <div class="flex flex-col items-center justify-center py-6">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-50 mb-3">
                    <i class="ti ti-brain text-xl text-gray-400"></i>
                </div>
                <p class="text-sm font-medium text-gray-900 mb-1">No data available</p>
                <p class="text-xs text-gray-500 mt-1 text-center">
                    Effectiveness is measured 30 days after a student attends an assigned seminar.<br>
                    Results will appear here once tracked records exist for this period.
                </p>
            </div>
        @endif
    </div>
</div>

@php
    $hasTrendData = array_sum($trendChartData['data']) > 0;
    $hasRiskData = array_sum($riskChartData['data']) > 0;
    $hasConcernData = array_sum($concernChartData['data']) > 0;
    $hasSeverityData = array_sum($severityChartData['data']) > 0;
@endphp

{{-- ── Charts Row 1 ───────────────────────────────────────── --}}
<div class="grid grid-cols-3 gap-6 mb-6">

    <div class="col-span-2 bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100/60 bg-gray-50/50">
            <h2 class="text-[15px] font-semibold text-gray-800 flex items-center gap-2">
                <i class="ti ti-chart-line text-gray-400"></i> Referral Volume (6 Months)
            </h2>
        </div>
        <div class="p-5 h-[220px]">
            @if($hasTrendData)
                <canvas id="trendChart"></canvas>
            @else
                <div class="h-full flex flex-col items-center justify-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-50 mb-3">
                        <i class="ti ti-chart-line text-xl text-gray-400"></i>
                    </div>
                    <p class="text-sm font-medium text-gray-900 mb-1">No data available</p>
                    <p class="text-xs text-gray-500 mt-1">Referral volume will appear here once filed.</p>
                </div>
            @endif
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100/60 bg-gray-50/50">
            <h2 class="text-[15px] font-semibold text-gray-800 flex items-center gap-2">
                <i class="ti ti-chart-pie text-gray-400"></i> Risk Level Distribution
            </h2>
        </div>
        <div class="p-5 h-[220px] flex items-center justify-center">
            @if($hasRiskData)
                <div style="width: 100%; max-width: 200px; height: 100%;">
                    <canvas id="riskChart"></canvas>
                </div>
            @else
                <div class="flex flex-col items-center justify-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-50 mb-3">
                        <i class="ti ti-chart-pie text-xl text-gray-400"></i>
                    </div>
                    <p class="text-sm font-medium text-gray-900 mb-1">No data available</p>
                    <p class="text-xs text-gray-500 mt-1 text-center">Risk assessments will appear here.</p>
                </div>
            @endif
        </div>
    </div>

</div>

{{-- ── Charts Row 2 ───────────────────────────────────────── --}}
<div class="grid grid-cols-2 gap-6 mb-6">

    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100/60 bg-gray-50/50">
            <h2 class="text-[15px] font-semibold text-gray-800 flex items-center gap-2">
                <i class="ti ti-category text-gray-400"></i> Concern Types
            </h2>
        </div>
        <div class="p-5 h-[220px] flex items-center justify-center">
            @if($hasConcernData)
                <div style="width: 100%; max-width: 220px; height: 100%;">
                    <canvas id="concernChart"></canvas>
                </div>
            @else
                <div class="flex flex-col items-center justify-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-50 mb-3">
                        <i class="ti ti-category text-xl text-gray-400"></i>
                    </div>
                    <p class="text-sm font-medium text-gray-900 mb-1">No data available</p>
                    <p class="text-xs text-gray-500 mt-1 text-center">Concern type breakdown will appear here.</p>
                </div>
            @endif
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100/60 bg-gray-50/50">
            <h2 class="text-[15px] font-semibold text-gray-800 flex items-center gap-2">
                <i class="ti ti-message-report text-gray-400"></i> Behavioral Report Severity
            </h2>
        </div>
        <div class="p-5 h-[220px] flex items-center justify-center">
            @if($hasSeverityData)
                <div style="width: 100%; max-width: 220px; height: 100%;">
                    <canvas id="severityChart"></canvas>
                </div>
            @else
                <div class="flex flex-col items-center justify-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-50 mb-3">
                        <i class="ti ti-message-report text-xl text-gray-400"></i>
                    </div>
                    <p class="text-sm font-medium text-gray-900 mb-1">No data available</p>
                    <p class="text-xs text-gray-500 mt-1 text-center">Behavioral report severity will appear here.</p>
                </div>
            @endif
        </div>
    </div>

</div>

{{-- ── Concern Type × Risk Level Breakdown ────────────────── --}}
<div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden mb-6">
    <div class="px-6 py-5 border-b border-gray-100/60 bg-gray-50/50">
        <h2 class="text-[15px] font-semibold text-gray-800 flex items-center gap-2">
            <i class="ti ti-table text-gray-400"></i> Concern Type &times; Risk Level Breakdown
        </h2>
    </div>
    @if(count($concernRiskMatrix) > 0)
        <table class="w-full text-left border-collapse">
            <thead class="bg-gray-50/30">
                <tr>
                    <th class="text-left text-[11px] text-gray-400 font-semibold uppercase tracking-wider py-3 px-6 border-b border-gray-100">Concern Type</th>
                    <th class="text-center text-[11px] text-gray-400 font-semibold uppercase tracking-wider py-3 px-6 border-b border-gray-100">Low</th>
                    <th class="text-center text-[11px] text-gray-400 font-semibold uppercase tracking-wider py-3 px-6 border-b border-gray-100">Moderate</th>
                    <th class="text-center text-[11px] text-gray-400 font-semibold uppercase tracking-wider py-3 px-6 border-b border-gray-100">High</th>
                    <th class="text-center text-[11px] text-gray-400 font-semibold uppercase tracking-wider py-3 px-6 border-b border-gray-100">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($concernRiskMatrix as $concern => $levels)
                <tr class="hover:bg-blue-50/30 transition-colors duration-150">
                    <td class="py-3 px-6 text-sm font-medium text-gray-800">{{ ucfirst(str_replace('_', ' ', $concern)) }}</td>
                    <td class="py-3 px-6 text-center">
                        <span class="inline-flex items-center justify-center min-w-[28px] px-2 py-0.5 rounded-md text-xs font-bold {{ ($levels['low'] ?? 0) > 0 ? 'bg-emerald-50 text-emerald-700' : 'text-gray-300' }}">{{ $levels['low'] ?? 0 }}</span>
                    </td>
                    <td class="py-3 px-6 text-center">
                        <span class="inline-flex items-center justify-center min-w-[28px] px-2 py-0.5 rounded-md text-xs font-bold {{ ($levels['moderate'] ?? 0) > 0 ? 'bg-amber-50 text-amber-700' : 'text-gray-300' }}">{{ $levels['moderate'] ?? 0 }}</span>
                    </td>
                    <td class="py-3 px-6 text-center">
                        <span class="inline-flex items-center justify-center min-w-[28px] px-2 py-0.5 rounded-md text-xs font-bold {{ ($levels['high'] ?? 0) > 0 ? 'bg-red-50 text-red-700' : 'text-gray-300' }}">{{ $levels['high'] ?? 0 }}</span>
                    </td>
                    <td class="py-3 px-6 text-center text-sm font-bold text-gray-700">{{ array_sum($levels) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="flex flex-col items-center justify-center py-10">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-50 mb-3">
                <i class="ti ti-table text-xl text-gray-400"></i>
            </div>
            <p class="text-sm font-medium text-gray-900 mb-1">No data available</p>
            <p class="text-xs text-gray-500 mt-1 text-center">This appears once referrals have an associated risk assessment.</p>
        </div>
    @endif
</div>

{{-- ── Data Tables Row ────────────────────────────────────── --}}
<div class="grid grid-cols-2 gap-6">

    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100/60 bg-gray-50/50">
            <h2 class="text-[15px] font-semibold text-gray-800 flex items-center gap-2">
                <i class="ti ti-user-check text-gray-400"></i> Most Active Teachers
            </h2>
        </div>
        <div class="overflow-x-auto overflow-y-auto custom-scrollbar" style="max-height: 260px;">
            <table class="w-full text-left border-collapse">
                <thead class="sticky top-0 bg-white z-10 shadow-sm">
                    <tr>
                        <th class="text-left text-[11px] text-gray-400 font-semibold uppercase tracking-wider py-3 px-6 border-b border-gray-100 bg-white">Teacher Name</th>
                        <th class="text-center text-[11px] text-gray-400 font-semibold uppercase tracking-wider py-3 px-6 border-b border-gray-100 bg-white">Referrals Filed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($topTeachers as $teacher)
                    <tr class="hover:bg-blue-50/30 transition-colors duration-150">
                        <td class="py-3 px-6 text-sm">
                            <a href="{{ route('admin.teachers.show', $teacher->id) }}" class="font-medium text-blue-600 hover:underline">
                                {{ $teacher->name }}
                            </a>
                        </td>
                        <td class="py-3 px-6 text-sm text-center font-bold text-gray-700">
                            {{ $teacher->referrals_referred_count }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" class="py-12 text-center">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-50 mb-3">
                                <i class="ti ti-user-check text-xl text-gray-400"></i>
                            </div>
                            <p class="text-sm font-medium text-gray-900 mb-1">No data available</p>
                            <p class="text-xs text-gray-500 mt-1">Teacher referral activity will appear here.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-100/60 bg-gray-50/50">
            <h2 class="text-[15px] font-semibold text-gray-800 flex items-center gap-2">
                <i class="ti ti-school text-gray-400"></i> Top Courses by Referrals
            </h2>
        </div>
        <div class="overflow-x-auto overflow-y-auto custom-scrollbar" style="max-height: 260px;">
            <table class="w-full text-left border-collapse">
                <thead class="sticky top-0 bg-white z-10 shadow-sm">
                    <tr>
                        <th class="text-left text-[11px] text-gray-400 font-semibold uppercase tracking-wider py-3 px-6 border-b border-gray-100 bg-white">Course &amp; Section</th>
                        <th class="text-center text-[11px] text-gray-400 font-semibold uppercase tracking-wider py-3 px-6 border-b border-gray-100 bg-white">Referrals Count</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($topCourses as $course)
                    <tr class="hover:bg-blue-50/30 transition-colors duration-150">
                        <td class="py-3 px-6 text-sm font-medium text-gray-800">
                            {{ $course->course_name ?? 'Unassigned' }}
                        </td>
                        <td class="py-3 px-6 text-sm text-center font-bold text-gray-700">
                            {{ $course->referral_count }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="2" class="py-12 text-center">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-50 mb-3">
                                <i class="ti ti-school text-xl text-gray-400"></i>
                            </div>
                            <p class="text-sm font-medium text-gray-900 mb-1">No data available</p>
                            <p class="text-xs text-gray-500 mt-1">Referral counts by course will appear here.</p>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Shared Chart Options
        const sharedOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } }
            }
        };

        // 1. Monthly Trend Chart
        const trendEl = document.getElementById('trendChart');
        if (trendEl) {
            new Chart(trendEl, {
                type: 'line',
                data: {
                    labels: @json($trendChartData['labels']),
                    datasets: [{
                        label: 'Referrals',
                        data: @json($trendChartData['data']),
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#2563eb'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { borderDash: [4, 4] } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        // 2. Risk Distribution Chart
        const riskEl = document.getElementById('riskChart');
        if (riskEl) {
            new Chart(riskEl, {
                type: 'doughnut',
                data: {
                    labels: @json($riskChartData['labels']),
                    datasets: [{
                        data: @json($riskChartData['data']),
                        backgroundColor: ['#ef4444', '#f59e0b', '#10b981', '#6b7280'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: sharedOptions
            });
        }

        // 3. Concern Types Chart
        const concernEl = document.getElementById('concernChart');
        if (concernEl) {
            new Chart(concernEl, {
                type: 'pie',
                data: {
                    labels: @json($concernChartData['labels']),
                    datasets: [{
                        data: @json($concernChartData['data']),
                        backgroundColor: ['#3b82f6', '#8b5cf6', '#ec4899', '#f43f5e', '#f97316', '#eab308'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: sharedOptions
            });
        }

        // 4. Severity Chart
        const severityEl = document.getElementById('severityChart');
        if (severityEl) {
            new Chart(severityEl, {
                type: 'doughnut',
                data: {
                    labels: @json($severityChartData['labels']),
                    datasets: [{
                        data: @json($severityChartData['data']),
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#6b7280'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    ...sharedOptions,
                    cutout: '70%'
                }
            });
        }
    });
</script>
@endpush
