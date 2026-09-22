<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Behavioral Report - {{ $behavioral_report->student->last_name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { font-size: 12pt; color: black; background: white; }
            .no-print { display: none !important; }
            .print-border { border-color: black !important; }
            .page-break { page-break-after: always; }
            @page { margin: 1in; }
        }
        body { background: white; color: black; }
    </style>
</head>
<body class="bg-white text-black p-8 max-w-4xl mx-auto font-sans">

    <!-- Print Controls (Hidden on actual print) -->
    <div class="mb-8 flex justify-end no-print">
        <button onclick="window.print()" class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg shadow-sm hover:bg-blue-700 transition flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M17 17h2a2 2 0 0 0 2 -2v-4a2 2 0 0 0 -2 -2h-14a2 2 0 0 0 -2 2v4a2 2 0 0 0 2 2h2" /><path d="M17 9v-4a2 2 0 0 0 -2 -2h-6a2 2 0 0 0 -2 2v4" /><path d="M7 13m0 2a2 2 0 0 1 2 -2h6a2 2 0 0 1 2 2v4a2 2 0 0 1 -2 2h-6a2 2 0 0 1 -2 -2z" /></svg>
            Print Document
        </button>
    </div>

    <!-- Header -->
    <div class="text-center mb-10 border-b-2 border-black pb-6 print-border">
        <h1 class="text-2xl font-bold uppercase tracking-wider mb-2">Student Referral System</h1>
        <h2 class="text-xl font-semibold text-gray-800">Official Behavioral Incident Report</h2>
        <p class="text-sm text-gray-600 mt-2">Report Reference: #{{ str_pad($behavioral_report->id, 6, '0', STR_PAD_LEFT) }}</p>
    </div>

    <!-- Student Information -->
    <div class="mb-8">
        <h3 class="text-lg font-bold mb-4 uppercase border-b border-gray-300 pb-1 print-border">Student Information</h3>
        <table class="w-full text-sm">
            <tr>
                <td class="font-bold py-2 w-1/4">Name:</td>
                <td class="py-2 w-3/4">{{ $behavioral_report->student->last_name }}, {{ $behavioral_report->student->first_name }} {{ $behavioral_report->student->middle_name }}</td>
            </tr>
            <tr>
                <td class="font-bold py-2">Student ID:</td>
                <td class="py-2">{{ $behavioral_report->student->student_id_number }}</td>
            </tr>
            <tr>
                <td class="font-bold py-2">Course/Section:</td>
                <td class="py-2">{{ $behavioral_report->student->course ?? $behavioral_report->student->grade_level }} — {{ $behavioral_report->student->section }}</td>
            </tr>
            <tr>
                <td class="font-bold py-2">Parent/Guardian:</td>
                <td class="py-2">{{ $behavioral_report->student->parent_name }} ({{ $behavioral_report->student->parent_contact }})</td>
            </tr>
        </table>
    </div>

    <!-- Incident Details -->
    <div class="mb-8">
        <h3 class="text-lg font-bold mb-4 uppercase border-b border-gray-300 pb-1 print-border">Incident Details</h3>
        <table class="w-full text-sm">
            <tr>
                <td class="font-bold py-2 w-1/4">Date of Incident:</td>
                <td class="py-2 w-3/4">{{ $behavioral_report->incident_date->format('F j, Y') }}</td>
            </tr>
            <tr>
                <td class="font-bold py-2">Location:</td>
                <td class="py-2">{{ $behavioral_report->location ?? 'Not specified' }}</td>
            </tr>
            <tr>
                <td class="font-bold py-2">Incident Type:</td>
                <td class="py-2 font-semibold">{{ $behavioral_report->incident_type }}</td>
            </tr>
            <tr>
                <td class="font-bold py-2">Severity Level:</td>
                <td class="py-2 uppercase font-bold">{{ $behavioral_report->severity }}</td>
            </tr>
            <tr>
                <td class="font-bold py-2">Reported By:</td>
                <td class="py-2">{{ $behavioral_report->reportedBy->name ?? 'Unknown Teacher' }}</td>
            </tr>
            <tr>
                <td class="font-bold py-2">Date Filed:</td>
                <td class="py-2">{{ $behavioral_report->created_at->format('F j, Y — h:i A') }}</td>
            </tr>
            @if($behavioral_report->escalatedReferral)
                <tr>
                    <td class="font-bold py-2">Escalated To:</td>
                    <td class="py-2">Guidance Referral #{{ str_pad($behavioral_report->escalatedReferral->id, 6, '0', STR_PAD_LEFT) }}</td>
                </tr>
            @endif
        </table>
    </div>

    <!-- Incident Description -->
    <div class="mb-12">
        <h3 class="text-lg font-bold mb-4 uppercase border-b border-gray-300 pb-1 print-border">Incident Description</h3>
        <div class="text-sm text-justify leading-relaxed p-4 border border-gray-300 print-border min-h-[150px]">
            {{ $behavioral_report->description }}
        </div>
    </div>

    <!-- Signatures -->
    <div class="mt-20 pt-10">
        <div class="grid grid-cols-2 gap-16">
            <div class="text-center">
                <div class="border-b border-black print-border mb-2 mx-8"></div>
                <p class="font-bold text-sm">{{ $behavioral_report->reportedBy->name ?? '_____________________' }}</p>
                <p class="text-xs text-gray-600">Reporting Teacher Signature over Printed Name</p>
                <p class="text-xs text-gray-600 mt-1">Date: _________________</p>
            </div>
            
            <div class="text-center">
                <div class="border-b border-black print-border mb-2 mx-8"></div>
                <p class="font-bold text-sm">_________________________________</p>
                <p class="text-xs text-gray-600">Guidance Counselor Signature over Printed Name</p>
                <p class="text-xs text-gray-600 mt-1">Date: _________________</p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="mt-16 text-center text-[10px] text-gray-500">
        <p>This is a system-generated official document. Any unauthorized alterations are strictly prohibited.</p>
        <p>Generated on {{ now()->format('F j, Y h:i A') }}</p>
    </div>

    <script>
        // Auto-trigger print dialog when page loads (optional, but good UX for print views)
        window.onload = function() {
            setTimeout(() => {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
