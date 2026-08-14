@extends('layouts.counselor')

@section('title', 'Manage Seminar')
@section('page-title', 'Manage Seminar')
@section('page-sub', 'Assign students and track attendance for this intervention')

@section('content')

<div class="mb-6 flex items-center justify-between" x-data="{ showQrModal: false }">
    <a href="{{ route('counselor.seminars.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 flex items-center gap-1">
        <i class="ti ti-arrow-left"></i> Back to Seminars
    </a>
    <div class="flex gap-3">
        <a href="{{ route('counselor.seminars.print', $seminar->id) }}" target="_blank" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition shadow-sm gap-2">
            <i class="ti ti-printer"></i> Print Roster
        </a>
        <a href="{{ route('counselor.seminars.export', $seminar->id) }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition shadow-sm gap-2">
            <i class="ti ti-file-spreadsheet"></i> Export CSV
        </a>
        <button type="button" @click="showQrModal = true; setTimeout(() => generateQR(), 100);" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition shadow-sm gap-2">
            <i class="ti ti-qrcode"></i> Show Check-in QR
        </button>
        <a href="{{ route('counselor.seminars.edit', $seminar->id) }}" class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm gap-2">
            <i class="ti ti-pencil"></i> Edit Details
        </a>
    </div>

    <!-- QR Code Modal -->
    <div x-show="showQrModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showQrModal" x-transition.opacity class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" aria-hidden="true" @click="showQrModal = false"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="showQrModal" x-transition.scale.origin.bottom class="inline-block w-full max-w-sm p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl sm:p-8 text-center">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-bold text-gray-900">Seminar Check-in QR</h3>
                    <button type="button" @click="showQrModal = false" class="text-gray-400 hover:text-gray-600 transition bg-gray-50 hover:bg-gray-100 rounded-full p-2">
                        <i class="ti ti-x text-xl"></i>
                    </button>
                </div>
                <p class="text-sm text-gray-500 mb-6">Students can scan this QR code with their mobile device to instantly mark their attendance.</p>
                <div id="qrcode" class="flex justify-center p-4 bg-white border-2 border-dashed border-gray-200 rounded-xl mb-4"></div>
                <p class="text-xs text-gray-400">URL: {{ route('student.seminars.checkin', $seminar->id) }}</p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column: Seminar Details -->
    <div class="lg:col-span-1 flex flex-col gap-6">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6 transition-all duration-300 hover:shadow-hover relative overflow-hidden flex flex-col flex-grow">
            @if($seminar->is_required)
                <div class="absolute top-0 right-0 bg-red-500 text-white text-[10px] font-bold px-3 py-1.5 rounded-bl-lg uppercase tracking-wider">Required</div>
            @endif

            <h3 class="font-bold text-gray-900 text-xl pr-16">{{ $seminar->title }}</h3>
            <p class="text-gray-500 text-sm mt-2 mb-4">{{ $seminar->description }}</p>

            <div class="space-y-4 pt-4 border-t border-gray-100 text-sm">
                <div>
                    <span class="block text-xs text-gray-400 uppercase tracking-wider mb-0.5">Schedule</span>
                    <span class="font-medium text-gray-900 flex items-center gap-2">
                        <i class="ti ti-calendar text-blue-500"></i> {{ \Carbon\Carbon::parse($seminar->date)->format('l, F j, Y') }}
                    </span>
                    <span class="font-medium text-gray-900 flex items-center gap-2 mt-1">
                        <i class="ti ti-clock text-blue-500"></i> {{ \Carbon\Carbon::parse($seminar->time)->format('h:i A') }}
                    </span>
                </div>

                <div>
                    <span class="block text-xs text-gray-400 uppercase tracking-wider mb-0.5">Location</span>
                    <span class="font-medium text-gray-900 flex items-center gap-2">
                        <i class="ti ti-map-pin text-amber-500"></i> {{ $seminar->venue }}
                    </span>
                </div>

                <div>
                    <span class="block text-xs text-gray-400 uppercase tracking-wider mb-0.5">Speaker</span>
                    <span class="font-medium text-gray-900">{{ $seminar->speaker ?: 'TBA' }}</span>
                </div>

                <div>
                    <span class="block text-xs text-gray-400 uppercase tracking-wider mb-0.5">Target Audience</span>
                    <span class="font-medium text-gray-900">
                        {{ $seminar->target_course ?: 'All Courses' }}
                        @if($seminar->target_grade_level)
                            - {{ $seminar->target_grade_level }}
                        @endif
                    </span>
                </div>

                <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
                    <span class="text-xs text-gray-500 uppercase tracking-wider">Capacity</span>
                    <span class="font-bold text-gray-900 text-lg">
                        {{ $seminar->students->count() }} <span class="text-sm font-medium text-gray-500">/ {{ $seminar->max_participants ?: '∞' }}</span>
                    </span>
                </div>
            </div>
        </div>

        @php
            $trackedStudents = $seminar->students->filter(fn($s) => $s->pivot->effectiveness !== null);
            $totalTracked = $trackedStudents->count();
            if ($totalTracked > 0) {
                $improved = $trackedStudents->where('pivot.effectiveness', 'improved')->count();
                $worse = $trackedStudents->where('pivot.effectiveness', 'worse')->count();
                $noChange = $trackedStudents->where('pivot.effectiveness', 'no_change')->count();
            }
        @endphp

        @if($totalTracked > 0)
        <!-- Effectiveness Stats Card -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6 transition-all duration-300 hover:shadow-hover">
            <h3 class="font-bold text-gray-900 text-lg mb-4 flex items-center gap-2">
                <i class="ti ti-chart-line text-emerald-500"></i> Effectiveness Stats
            </h3>
            <p class="text-xs text-gray-500 mb-4">Risk score changes 30 days post-seminar.</p>
            
            <div class="grid grid-cols-3 gap-2 text-center mb-2">
                <div class="bg-emerald-50 rounded-lg p-2 border border-emerald-100">
                    <div class="text-xl font-bold text-emerald-600">{{ $improved }}</div>
                    <div class="text-[10px] font-medium text-gray-500 uppercase">Improved</div>
                </div>
                <div class="bg-gray-50 rounded-lg p-2 border border-gray-200">
                    <div class="text-xl font-bold text-gray-700">{{ $noChange }}</div>
                    <div class="text-[10px] font-medium text-gray-500 uppercase">No Change</div>
                </div>
                <div class="bg-red-50 rounded-lg p-2 border border-red-100">
                    <div class="text-xl font-bold text-red-600">{{ $worse }}</div>
                    <div class="text-[10px] font-medium text-gray-500 uppercase">Worse</div>
                </div>
            </div>
            <div class="text-center text-sm font-medium text-gray-700 mt-3 border-t border-gray-100 pt-3">
                Success Rate: <span class="text-emerald-600 font-bold">{{ round(($improved / $totalTracked) * 100) }}%</span>
            </div>
        </div>
        @endif
    </div>

    <div class="lg:col-span-2 flex flex-col gap-6">
        <!-- Assign Students Form -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden transition-all duration-300 hover:shadow-hover flex flex-col flex-grow">
            <div class="bg-gray-50/50 px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                <i class="ti ti-user-plus text-green-600"></i>
                <h4 class="font-semibold text-gray-800">Assign Students</h4>
            </div>
            <div class="p-5 flex flex-col flex-grow">
                <form action="{{ route('counselor.seminars.assign', $seminar->id) }}" method="POST" class="flex flex-col flex-grow">
                    @csrf
                    <div class="mb-4 flex flex-col flex-grow">
                        <div class="flex justify-between items-center mb-2">
                            <label class="block text-sm font-medium text-gray-700">Select students to assign</label>
                            <button type="button" onclick="Array.from(document.getElementById('student_assign_select').options).forEach(o => o.selected = true)" class="text-xs font-semibold text-blue-600 hover:text-blue-800 transition">Select All</button>
                        </div>
                        <select id="student_assign_select" name="student_ids[]" multiple required class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm flex-grow min-h-[12rem]" size="6">
                            @foreach($availableStudents as $student)
                                <option value="{{ $student->id }}">{{ $student->last_name }}, {{ $student->first_name }} ({{ $student->course ?? $student->grade_level }})</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-500 mt-2">Hold CTRL or CMD to select multiple students.</p>
                    </div>

                    @if($seminar->is_required)
                        <div class="mb-4 p-3 bg-red-50 rounded-lg text-sm text-red-700 flex gap-2 border border-red-100">
                            <i class="ti ti-alert-triangle mt-0.5"></i>
                            <p>Assigning students will automatically send an SMS to them and their parents.</p>
                        </div>
                    @endif

                    <button type="submit" class="w-full px-4 py-2 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 transition shadow-sm flex items-center justify-center gap-2">
                        <i class="ti ti-plus"></i> Add to Roster
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Full Width Bottom Section: Participant List & Attendance -->
    <div class="lg:col-span-3 space-y-6">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden flex flex-col h-full">
            <div class="bg-gray-50/50 px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row items-center justify-between gap-3">
                <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-users text-blue-600"></i> Participant Roster
                </h4>
                <form method="GET" action="{{ route('counselor.seminars.show', $seminar->id) }}" class="relative w-full sm:w-64">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="ti ti-search text-gray-400"></i>
                    </div>
                    <input type="text" name="search_roster" value="{{ request('search_roster') }}" placeholder="Search roster..." class="block w-full pl-10 pr-3 py-1.5 border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition" onchange="this.form.submit()">
                </form>
            </div>

            <div class="flex-grow">
                @if($participants->count() > 0)
                    <form action="{{ route('counselor.seminars.attendance', $seminar->id) }}" method="POST">
                        @csrf
                        @method('PATCH')

                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-white border-b border-gray-100">
                                        <th class="px-6 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Student</th>
                                        <th class="px-6 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider text-center">Attendance Status</th>
                                        <th class="px-6 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
                                    @foreach($participants as $student)
                                        <tr class="hover:bg-gray-50/50 transition">
                                            <td class="px-6 py-3">
                                                <div class="flex items-center gap-2">
                                                    <div>
                                                        <p class="font-medium text-gray-900 flex items-center gap-2">
                                                            <span>{{ $student->last_name }}, {{ $student->first_name }}</span>
                                                            @if($student->student_id_number)
                                                                <span class="text-[10px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded uppercase tracking-wider">{{ $student->student_id_number }}</span>
                                                            @endif
                                                        </p>
                                                        <p class="text-xs text-gray-500 mt-0.5">{{ $student->course ?? $student->grade_level }} - {{ $student->section }}</p>
                                                    </div>
                                                    @if($student->pivot->effectiveness === 'improved')
                                                        <i class="ti ti-trending-down text-emerald-500" title="Risk Score Dropped!"></i>
                                                    @elseif($student->pivot->effectiveness === 'worse')
                                                        <i class="ti ti-trending-up text-red-500" title="Risk Score Increased"></i>
                                                    @elseif($student->pivot->effectiveness === 'no_change')
                                                        <i class="ti ti-minus text-gray-400" title="No Risk Change"></i>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-6 py-3">
                                                <div class="flex items-center justify-center gap-3">
                                                    <label class="inline-flex items-center cursor-pointer" title="Enrolled">
                                                        <input type="radio" name="attendance[{{ $student->id }}][status]" value="enrolled" class="text-gray-400 focus:ring-gray-400" {{ $student->pivot->status == 'enrolled' ? 'checked' : '' }}>
                                                        <span class="ml-1 text-gray-500 font-medium text-xs">PEND</span>
                                                    </label>
                                                    <label class="inline-flex items-center cursor-pointer" title="Attended">
                                                        <input type="radio" name="attendance[{{ $student->id }}][status]" value="attended" class="text-green-600 focus:ring-green-500" {{ $student->pivot->status == 'attended' ? 'checked' : '' }}>
                                                        <span class="ml-1 text-green-700 font-medium text-xs">ATT</span>
                                                    </label>
                                                    <label class="inline-flex items-center cursor-pointer" title="Missed">
                                                        <input type="radio" name="attendance[{{ $student->id }}][status]" value="missed" class="text-red-600 focus:ring-red-500" {{ $student->pivot->status == 'missed' ? 'checked' : '' }}>
                                                        <span class="ml-1 text-red-700 font-medium text-xs">MISS</span>
                                                    </label>
                                                </div>
                                            </td>
                                            <td class="px-6 py-3">
                                                <input type="text" name="attendance[{{ $student->id }}][remarks]" value="{{ $student->pivot->remarks }}" placeholder="Optional notes"
                                                    class="w-full text-xs rounded border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 shadow-sm py-1.5 px-2">
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex flex-col md:flex-row items-center justify-between gap-4">
                            <div class="w-full md:w-auto overflow-x-auto">
                                @if($participants->hasPages())
                                    {{ $participants->links() }}
                                @endif
                            </div>
                            <button type="submit" class="px-5 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-2 whitespace-nowrap">
                                <i class="ti ti-check"></i> Save Attendance
                            </button>
                        </div>
                    </form>
                @else
                    <div class="py-16 flex flex-col items-center justify-center text-center">
                        <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center text-blue-300 mb-3">
                            <i class="ti ti-users-minus text-2xl"></i>
                        </div>
                        <h3 class="text-base font-medium text-gray-900 mb-1">No Participants Yet</h3>
                        <p class="text-sm text-gray-500 max-w-sm">Use the assignment form on the left to add students to this seminar's roster.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    let qrGenerated = false;
    function generateQR() {
        if(qrGenerated) return;
        new QRCode(document.getElementById("qrcode"), {
            text: "{{ route('student.seminars.checkin', $seminar->id) }}",
            width: 200,
            height: 200,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });
        qrGenerated = true;
    }
</script>
@endpush
