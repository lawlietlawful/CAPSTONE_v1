<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Roster - {{ $seminar->title }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; background-color: white; }
            .no-print { display: none !important; }
            @page { margin: 1.5cm; }
        }
    </style>
</head>
<body class="bg-gray-100 text-black">
    <div class="max-w-4xl mx-auto my-8 bg-white p-8 shadow-sm print:shadow-none print:m-0 print:p-0">
        <!-- Print Header Controls -->
        <div class="no-print flex justify-between items-center mb-8 pb-4 border-b border-gray-200">
            <a href="{{ route('admin.seminars.show', $seminar->id) }}" class="text-blue-600 font-medium hover:underline">&larr; Back to Seminar</a>
            <button onclick="window.print()" class="px-6 py-2 bg-blue-600 text-white rounded shadow-sm hover:bg-blue-700 font-medium">Print Roster</button>
        </div>

        <!-- Roster Header -->
        <div class="text-center mb-8 border-b-2 border-black pb-6">
            <h1 class="text-2xl font-bold uppercase tracking-wider mb-2">Seminar Attendance Roster</h1>
            <h2 class="text-xl font-semibold">{{ $seminar->title }}</h2>
            <div class="flex justify-center gap-6 mt-3 text-sm">
                <span><strong>Date:</strong> {{ \Carbon\Carbon::parse($seminar->date)->format('F j, Y') }}</span>
                <span><strong>Time:</strong> {{ \Carbon\Carbon::parse($seminar->time)->format('h:i A') }}</span>
                <span><strong>Venue:</strong> {{ $seminar->venue }}</span>
            </div>
            <div class="flex justify-center gap-6 mt-1 text-sm">
                <span><strong>Target:</strong> {{ $seminar->target_course ?: 'All' }} {{ $seminar->target_grade_level ? '- '.$seminar->target_grade_level : '' }}</span>
                <span><strong>Speaker:</strong> {{ $seminar->speaker ?: 'TBA' }}</span>
            </div>
        </div>

        <!-- Table -->
        <table class="w-full text-left border-collapse mb-8">
            <thead>
                <tr>
                    <th class="border border-gray-400 bg-gray-100 px-4 py-2 font-bold w-12 text-center">#</th>
                    <th class="border border-gray-400 bg-gray-100 px-4 py-2 font-bold">Student Name</th>
                    <th class="border border-gray-400 bg-gray-100 px-4 py-2 font-bold w-40">Course/Year</th>
                    <th class="border border-gray-400 bg-gray-100 px-4 py-2 font-bold w-48 text-center">Signature</th>
                </tr>
            </thead>
            <tbody>
                @forelse($seminar->students as $index => $student)
                <tr>
                    <td class="border border-gray-400 px-4 py-3 text-center">{{ $index + 1 }}</td>
                    <td class="border border-gray-400 px-4 py-3">
                        <span class="font-bold">{{ strtoupper($student->last_name) }}</span>, {{ $student->first_name }}
                    </td>
                    <td class="border border-gray-400 px-4 py-3">{{ $student->course ?? $student->grade_level }}</td>
                    <td class="border border-gray-400 px-4 py-3 text-transparent text-xs">.</td>
                </tr>
                @empty
                <tr>
                    <td colspan="4" class="border border-gray-400 px-4 py-8 text-center text-gray-500 italic">No students assigned to this seminar yet.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Footer -->
        <div class="flex justify-between mt-16 pt-4 text-sm">
            <div class="text-center w-64">
                <div class="border-b border-black mb-2"></div>
                <p>Signature over Printed Name</p>
                <p class="text-xs text-gray-500">(Facilitator / Speaker)</p>
            </div>
            <div class="text-right text-gray-500 text-xs">
                Generated on {{ now()->format('M d, Y h:i A') }}<br>
                {{ config('app.name') }}
            </div>
        </div>
    </div>
</body>
</html>
