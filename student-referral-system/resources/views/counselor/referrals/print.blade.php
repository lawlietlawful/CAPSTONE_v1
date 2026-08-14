<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Official Case Report - Referral #{{ $referral->id }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; line-height: 1.5; margin: 0; padding: 20px; font-size: 12pt; }
        .header { text-align: center; border-bottom: 2px solid #2563eb; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 24pt; color: #1e3a8a; text-transform: uppercase; letter-spacing: 1px; }
        .header p { margin: 5px 0 0; color: #64748b; font-size: 10pt; }
        
        .section-title { font-size: 14pt; font-weight: bold; color: #1e40af; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-top: 30px; margin-bottom: 15px; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; }
        .info-item { margin-bottom: 5px; }
        .info-label { font-size: 9pt; text-transform: uppercase; color: #64748b; font-weight: bold; display: block; }
        .info-value { font-size: 12pt; font-weight: 500; }
        
        .reason-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 4px; font-style: italic; margin-bottom: 20px; }
        
        .ai-badge { display: inline-block; padding: 5px 10px; font-weight: bold; font-size: 11pt; border-radius: 4px; margin-bottom: 10px; }
        .ai-high { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .ai-moderate { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .ai-low { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        
        table { border-collapse: collapse; margin-top: 10px; width: 100%; }
        th, td { border: 1px solid #cbd5e1; padding: 8px 12px; text-align: left; }
        th { background: #f1f5f9; font-size: 10pt; text-transform: uppercase; color: #475569; }
        
        .signature-section { margin-top: 60px; display: grid; grid-template-columns: 1fr 1fr; gap: 50px; }
        .signature-line { border-top: 1px solid #333; text-align: center; margin-top: 50px; }
        .signature-name { font-weight: bold; font-size: 12pt; margin-top: 10px; display: block; }
        .signature-title { font-size: 10pt; color: #64748b; }
        
        @media print {
            body { padding: 0; }
            .no-print { display: none !important; }
        }
        
        .btn-print { background: #2563eb; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 14px; font-weight: bold; margin-bottom: 20px; display: inline-block; text-decoration: none; }
        .btn-print:hover { background: #1d4ed8; }
    </style>
</head>
<body onload="window.print()">
    
    <div class="no-print" style="text-align: right;">
        <button class="btn-print" onclick="window.print()">Print PDF</button>
        <a href="{{ route('counselor.referrals.show', $referral->id) }}" style="margin-left: 10px; color: #64748b; text-decoration: none;">Back</a>
    </div>

    <div class="header">
        <h1>Official Case Report</h1>
        <p>Guidance & Counseling Office • Referral ID: #{{ str_pad($referral->id, 6, '0', STR_PAD_LEFT) }} • Printed: {{ now()->format('M d, Y h:i A') }}</p>
    </div>

    <div class="section-title">Student Information</div>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Student Name</span>
            <span class="info-value">{{ $referral->student->last_name }}, {{ $referral->student->first_name }} {{ $referral->student->middle_name }}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Student ID</span>
            <span class="info-value">{{ $referral->student->student_id_number }}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Total Referrals</span>
            <span class="info-value">{{ $referral->student->referrals()->count() }} referrals</span>
        </div>
        <div class="info-item">
            <span class="info-label">Behavioral Reports</span>
            <span class="info-value">{{ $referral->student->behavioralReports()->count() }} reports</span>
        </div>
    </div>

    <div class="section-title">Referral Details</div>
    <div class="info-grid" style="margin-bottom: 10px;">
        <div class="info-item">
            <span class="info-label">Date Filed</span>
            <span class="info-value">{{ $referral->created_at->format('F j, Y') }}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Referred By</span>
            <span class="info-value">{{ $referral->referredBy->name ?? '—' }}</span>
        </div>
        @if($referral->behavioralReport)
            <div class="info-item">
                <span class="info-label">Escalated From</span>
                <span class="info-value">Behavioral Report #{{ str_pad($referral->behavioralReport->id, 6, '0', STR_PAD_LEFT) }} ({{ $referral->behavioralReport->incident_type }})</span>
            </div>
        @endif
    </div>

    <span class="info-label">Stated Reason:</span>
    <div class="reason-box">
        "{{ $referral->display_reason }}"
    </div>

    <div class="section-title">AI Risk Assessment</div>
    @if($referral->riskAssessment)
        @php
            $aiClass = match($referral->riskAssessment->risk_level) {
                'high' => 'ai-high',
                'moderate' => 'ai-moderate',
                default => 'ai-low',
            };
        @endphp
        <div class="ai-badge {{ $aiClass }}">
            {{ ucfirst($referral->riskAssessment->risk_level) }} Risk Profile ({{ $referral->riskAssessment->risk_score }}%)
        </div>
        <p style="margin-top: 0; font-size: 10pt; color: #64748b;">Assessed mathematically by ML Engine on {{ $referral->riskAssessment->assessed_at->format('M d, Y h:i A') }}</p>
    @else
        <p>No AI assessment was generated for this case.</p>
    @endif

    <div class="section-title">Intervention Log</div>
    @if($referral->interventions && $referral->interventions->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Intervention Type</th>
                    <th>Outcome</th>
                </tr>
            </thead>
            <tbody>
                @foreach($referral->interventions as $intervention)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($intervention->intervention_date)->format('M d, Y') }}</td>
                    <td>
                        <strong>{{ $intervention->intervention_type }}</strong><br>
                        <span style="font-size: 9pt; color: #64748b;">{{ $intervention->description }}</span>
                    </td>
                    <td>{{ ucfirst($intervention->outcome ?? 'Pending') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p style="font-style: italic; color: #94a3b8;">No interventions have been logged for this case.</p>
    @endif

    <div class="signature-section">
        <div>
            <div class="signature-line"></div>
            <span class="signature-name">{{ $referral->counselor->name ?? '_______________________' }}</span>
            <span class="signature-title">Guidance Counselor</span>
        </div>
        <div>
            <div class="signature-line"></div>
            <span class="signature-name">{{ $referral->student->first_name }} {{ $referral->student->last_name }}</span>
            <span class="signature-title">Student (or Parent/Guardian)</span>
        </div>
    </div>
</body>
</html>
