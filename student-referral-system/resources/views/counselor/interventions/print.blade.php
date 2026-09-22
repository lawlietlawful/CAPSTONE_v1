<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Intervention Session Report - #{{ $intervention->id }}</title>
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

        .notes-box { background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 4px; margin-bottom: 20px; white-space: pre-wrap; }

        .outcome-badge { display: inline-block; padding: 5px 10px; font-weight: bold; font-size: 11pt; border-radius: 4px; margin-bottom: 10px; }
        .outcome-improving { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .outcome-worsening { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .outcome-resolved { background: #e0e7ff; color: #3730a3; border: 1px solid #a5b4fc; }
        .outcome-no_change { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .outcome-none { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

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
        <a href="{{ route('counselor.interventions.show', $intervention->id) }}" style="margin-left: 10px; color: #64748b; text-decoration: none;">Back</a>
    </div>

    <div class="header">
        <h1>Intervention Session Report</h1>
        <p>Guidance & Counseling Office • Session #{{ str_pad($intervention->id, 6, '0', STR_PAD_LEFT) }} • Printed: {{ now()->format('M d, Y h:i A') }}</p>
    </div>

    <div class="section-title">Student Information</div>
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Student Name</span>
            <span class="info-value">{{ $intervention->referral->student->last_name ?? 'Unknown' }}, {{ $intervention->referral->student->first_name ?? '' }}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Student ID</span>
            <span class="info-value">{{ $intervention->referral->student->student_id_number ?? 'N/A' }}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Course & Year</span>
            <span class="info-value">{{ $intervention->referral->student->course ?? 'N/A' }} — {{ $intervention->referral->student->grade_level ?? 'N/A' }}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Linked Referral</span>
            <span class="info-value">#{{ str_pad($intervention->referral_id, 6, '0', STR_PAD_LEFT) }} ({{ $intervention->referral->referral_type_label }})</span>
        </div>
    </div>

    <div class="section-title">Session Details</div>
    <div class="info-grid" style="margin-bottom: 10px;">
        <div class="info-item">
            <span class="info-label">Date of Intervention</span>
            <span class="info-value">{{ $intervention->intervention_date->format('F j, Y') }}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Intervention Type</span>
            <span class="info-value">{{ $intervention->intervention_type }}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Conducted By</span>
            <span class="info-value">{{ $intervention->counselor->name ?? '—' }}</span>
        </div>
        <div class="info-item">
            <span class="info-label">Follow-up Date</span>
            <span class="info-value">{{ $intervention->follow_up_date?->format('F j, Y') ?? 'None scheduled' }}</span>
        </div>
    </div>

    <span class="info-label">Session Notes / Description:</span>
    <div class="notes-box">{{ $intervention->description }}</div>

    <div class="section-title">Outcome</div>
    <div class="outcome-badge outcome-{{ $intervention->outcome ?? 'none' }}">
        {{ $intervention->outcome ? ucfirst(str_replace('_', ' ', $intervention->outcome)) : 'Not Yet Evaluated' }}
    </div>

    @if($intervention->follow_up_notes)
        <div>
            <span class="info-label">Follow-up Requirements / Goals:</span>
            <div class="notes-box">{{ $intervention->follow_up_notes }}</div>
        </div>
    @endif

    <div class="signature-section">
        <div>
            <div class="signature-line"></div>
            <span class="signature-name">{{ $intervention->counselor->name ?? '_______________________' }}</span>
            <span class="signature-title">Guidance Counselor</span>
        </div>
        <div>
            <div class="signature-line"></div>
            <span class="signature-name">{{ $intervention->referral->student->first_name ?? '' }} {{ $intervention->referral->student->last_name ?? '' }}</span>
            <span class="signature-title">Student (or Parent/Guardian)</span>
        </div>
    </div>
</body>
</html>
