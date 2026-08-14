<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Analytics Report</title>
<style>
    @page {
        margin: 32px 36px;
    }
    body {
        font-family: "DejaVu Sans", sans-serif;
        color: #1f2937;
        font-size: 12px;
        margin: 0;
    }
    h1, h2, h3, p {
        margin: 0;
        padding: 0;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }

    /* ── Header ─────────────────────────────────────── */
    .header-table td {
        vertical-align: top;
        padding: 0;
    }
    .header-table .school-name {
        font-size: 17px;
        font-weight: bold;
        color: #0F1B2D;
    }
    .header-table .school-sub {
        font-size: 11px;
        color: #6b7280;
        margin-top: 2px;
    }
    .header-table .report-title {
        font-size: 14px;
        font-weight: bold;
        color: #2563eb;
    }
    .header-table .report-meta {
        font-size: 10px;
        color: #6b7280;
        margin-top: 2px;
    }
    .header-divider {
        border: none;
        border-top: 2px solid #0F1B2D;
        margin: 10px 0 16px 0;
    }

    /* ── KPI summary ────────────────────────────────── */
    .kpi-table td {
        width: 20%;
        border: 1px solid #e5e7eb;
        padding: 10px 12px;
    }
    .kpi-value {
        font-size: 20px;
        font-weight: bold;
        color: #111827;
    }
    .kpi-label {
        font-size: 9px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #9ca3af;
        margin-top: 3px;
    }

    /* ── Section headings ───────────────────────────── */
    .section-title {
        font-size: 12.5px;
        font-weight: bold;
        color: #0F1B2D;
        margin-top: 22px;
        margin-bottom: 8px;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 5px;
    }

    /* ── Data tables ─────────────────────────────────── */
    .data-table th {
        background-color: #f9fafb;
        text-align: left;
        font-size: 9.5px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #6b7280;
        padding: 6px 10px;
        border-bottom: 1px solid #e5e7eb;
    }
    .data-table td {
        padding: 6px 10px;
        font-size: 11px;
        border-bottom: 1px solid #f3f4f6;
    }
    .data-table .num {
        text-align: center;
        font-weight: bold;
        color: #374151;
    }
    .empty-note {
        font-size: 10px;
        color: #9ca3af;
        font-style: italic;
        padding: 8px 10px;
    }

    /* ── Two-column section layout ──────────────────── */
    .two-col-table td {
        vertical-align: top;
        width: 50%;
        padding-right: 14px;
    }
    .two-col-table td.right-col {
        padding-right: 0;
        padding-left: 14px;
    }

    .footer {
        margin-top: 26px;
        border-top: 1px solid #e5e7eb;
        padding-top: 8px;
        font-size: 9px;
        color: #9ca3af;
        text-align: center;
    }
</style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td style="width: 60%;">
                <p class="school-name">Student Referral System</p>
                <p class="school-sub">Misamis University</p>
            </td>
            <td style="width: 40%; text-align: right;">
                <p class="report-title">Analytics Report</p>
                <p class="report-meta">Period: {{ $rangeLabel }}</p>
                <p class="report-meta">Generated: {{ $generatedAt }}</p>
            </td>
        </tr>
    </table>
    <hr class="header-divider">

    {{-- ── KPI Summary ─────────────────────────────── --}}
    <table class="kpi-table">
        <tr>
            <td>
                <div class="kpi-value">{{ number_format($totalReferrals) }}</div>
                <div class="kpi-label">Total Referrals</div>
            </td>
            <td>
                <div class="kpi-value">{{ number_format($highRiskStudents) }}</div>
                <div class="kpi-label">High Risk Identified</div>
            </td>
            <td>
                <div class="kpi-value">{{ number_format($totalResolved) }}</div>
                <div class="kpi-label">Resolved Cases</div>
            </td>
            <td>
                <div class="kpi-value" style="font-size: 14px;">{{ $topConcernType }}</div>
                <div class="kpi-label">Top Concern</div>
            </td>
            <td>
                <div class="kpi-value" style="font-size: 14px;">{{ $avgResolutionDays !== null ? $avgResolutionDays . ' days' : 'N/A' }}</div>
                <div class="kpi-label">Avg. Resolution Time</div>
            </td>
        </tr>
    </table>

    {{-- ── Seminar Intervention Effectiveness ─────────── --}}
    <p class="section-title">Seminar Intervention Effectiveness</p>
    @if($seminarEffectivenessTotal > 0)
        <p style="font-size: 11px; color: #374151; margin-bottom: 8px;">
            Of students tracked 30 days after attending an assigned seminar,
            <strong>{{ $seminarEffectivenessPct }}%</strong> showed an improved risk score.
        </p>
        <table class="data-table">
            <thead>
                <tr><th>Outcome</th><th class="num" style="text-align: center;">Students</th></tr>
            </thead>
            <tbody>
                <tr><td>Improved</td><td class="num">{{ $seminarEffectiveness['improved'] }}</td></tr>
                <tr><td>No Change</td><td class="num">{{ $seminarEffectiveness['no_change'] }}</td></tr>
                <tr><td>Worse</td><td class="num">{{ $seminarEffectiveness['worse'] }}</td></tr>
            </tbody>
        </table>
    @else
        <table class="data-table">
            <tbody>
                <tr><td class="empty-note">No seminar effectiveness data tracked for this period.</td></tr>
            </tbody>
        </table>
    @endif

    {{-- ── Risk Level Distribution / Concern Types ───── --}}
    <table class="two-col-table">
        <tr>
            <td>
                <p class="section-title">Risk Level Distribution</p>
                <table class="data-table">
                    <thead>
                        <tr><th>Risk Level</th><th class="num" style="text-align: center;">Count</th></tr>
                    </thead>
                    <tbody>
                        @forelse($riskChartData['labels'] as $i => $label)
                            <tr>
                                <td>{{ ucfirst($label) }}</td>
                                <td class="num">{{ $riskChartData['data'][$i] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="empty-note">No risk assessments in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
            <td class="right-col">
                <p class="section-title">Concern Types</p>
                <table class="data-table">
                    <thead>
                        <tr><th>Concern Type</th><th class="num" style="text-align: center;">Count</th></tr>
                    </thead>
                    <tbody>
                        @forelse($concernChartData['labels'] as $i => $label)
                            <tr>
                                <td>{{ $label }}</td>
                                <td class="num">{{ $concernChartData['data'][$i] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="empty-note">No referrals in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </td>
        </tr>
    </table>

    {{-- ── Behavioral Report Severity ─────────────────── --}}
    <p class="section-title">Behavioral Report Severity</p>
    <table class="data-table">
        <thead>
            <tr><th>Severity</th><th class="num" style="text-align: center;">Count</th></tr>
        </thead>
        <tbody>
            @forelse($severityChartData['labels'] as $i => $label)
                <tr>
                    <td>{{ $label }}</td>
                    <td class="num">{{ $severityChartData['data'][$i] }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="empty-note">No behavioral reports in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── Concern Type x Risk Level Breakdown ────────── --}}
    <p class="section-title">Concern Type &times; Risk Level Breakdown</p>
    <table class="data-table">
        <thead>
            <tr>
                <th>Concern Type</th>
                <th class="num" style="text-align: center;">Low</th>
                <th class="num" style="text-align: center;">Moderate</th>
                <th class="num" style="text-align: center;">High</th>
                <th class="num" style="text-align: center;">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($concernRiskMatrix as $concern => $levels)
                <tr>
                    <td>{{ ucfirst(str_replace('_', ' ', $concern)) }}</td>
                    <td class="num">{{ $levels['low'] ?? 0 }}</td>
                    <td class="num">{{ $levels['moderate'] ?? 0 }}</td>
                    <td class="num">{{ $levels['high'] ?? 0 }}</td>
                    <td class="num">{{ array_sum($levels) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="empty-note">No referrals with an associated risk assessment in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── Most Active Teachers ───────────────────────── --}}
    <p class="section-title">Most Active Teachers</p>
    <table class="data-table">
        <thead>
            <tr><th>Teacher Name</th><th class="num" style="text-align: center;">Referrals Filed</th></tr>
        </thead>
        <tbody>
            @forelse($topTeachers as $teacher)
                <tr>
                    <td>{{ $teacher->name }}</td>
                    <td class="num">{{ $teacher->referrals_referred_count }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="empty-note">No teacher referral activity in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── Top Courses by Referrals ───────────────────── --}}
    <p class="section-title">Top Courses by Referrals</p>
    <table class="data-table">
        <thead>
            <tr><th>Course &amp; Section</th><th class="num" style="text-align: center;">Referrals Count</th></tr>
        </thead>
        <tbody>
            @forelse($topCourses as $course)
                <tr>
                    <td>{{ $course->course_name ?? 'Unassigned' }}</td>
                    <td class="num">{{ $course->referral_count }}</td>
                </tr>
            @empty
                <tr><td colspan="2" class="empty-note">No referral counts by course in this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">Student Referral System &middot; Misamis University &middot; Confidential — for internal guidance office use only</p>

</body>
</html>
