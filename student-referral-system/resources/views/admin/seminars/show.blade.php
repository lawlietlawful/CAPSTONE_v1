@extends('layouts.admin')

@section('title', 'Manage Seminar')
@section('page-title', 'Manage Seminar')
@section('page-sub', 'Assign students and track attendance for this intervention')

@section('content')

@php
    // Assigning sends "you are required to attend" SMS only for a required
    // seminar that hasn't happened yet (see BaseSeminarController::assignStudents).
    $sendsSms = $seminar->is_required && $seminar->status !== 'completed' && ! $seminar->date->isBefore(today());
    $checkinUrl = route('student.seminars.checkin', $seminar->id);
    $checkinHost = parse_url($checkinUrl, PHP_URL_HOST);
    $localOnlyQr = in_array($checkinHost, ['localhost', '127.0.0.1', '::1'], true);

    $statusStyles = [
        'upcoming'  => ['label' => 'Upcoming',  'icon' => 'ti-calendar-clock', 'pill' => 'bg-blue-50 text-blue-700 border-blue-100',          'tile' => 'bg-blue-600',    'bar' => 'bg-blue-500'],
        'ongoing'   => ['label' => 'Ongoing',   'icon' => 'ti-player-play',    'pill' => 'bg-amber-50 text-amber-700 border-amber-100',       'tile' => 'bg-amber-500',   'bar' => 'bg-amber-400'],
        'completed' => ['label' => 'Completed', 'icon' => 'ti-circle-check',   'pill' => 'bg-emerald-50 text-emerald-700 border-emerald-100', 'tile' => 'bg-emerald-600', 'bar' => 'bg-emerald-500'],
        'cancelled' => ['label' => 'Cancelled', 'icon' => 'ti-circle-x',       'pill' => 'bg-red-50 text-red-700 border-red-100',             'tile' => 'bg-red-400',     'bar' => 'bg-red-400'],
    ];
    $style = $statusStyles[$seminar->status] ?? $statusStyles['upcoming'];
    $time = \Carbon\Carbon::parse($seminar->time);
    $daysAway = (int) today()->diffInDays($seminar->date, false);
    $when = in_array($seminar->status, ['upcoming', 'ongoing'], true)
        ? match (true) { $daysAway <= 0 => 'Today', $daysAway === 1 => 'Tomorrow', default => "In {$daysAway} days" }
        : null;

    // Attendance across the whole roster (not just this page of it).
    $rosterTotal = $seminar->students->count();
    $counts = $seminar->students->countBy(fn ($s) => $s->pivot->status);
    $attendance = [
        'enrolled' => ['label' => 'Pending',  'count' => $counts['enrolled'] ?? 0, 'text' => 'text-gray-700',    'bg' => 'bg-gray-50 border-gray-100',       'bar' => 'bg-gray-300'],
        'attended' => ['label' => 'Attended', 'count' => $counts['attended'] ?? 0, 'text' => 'text-emerald-700', 'bg' => 'bg-emerald-50 border-emerald-100', 'bar' => 'bg-emerald-500'],
        'missed'   => ['label' => 'Missed',   'count' => $counts['missed'] ?? 0,   'text' => 'text-red-600',     'bg' => 'bg-red-50 border-red-100',         'bar' => 'bg-red-400'],
        'excused'  => ['label' => 'Excused',  'count' => $counts['excused'] ?? 0,  'text' => 'text-amber-700',   'bg' => 'bg-amber-50 border-amber-100',     'bar' => 'bg-amber-400'],
    ];
    $taken = $attendance['attended']['count'] + $attendance['missed']['count'];
    $attendanceRate = $taken > 0 ? (int) round($attendance['attended']['count'] / $taken * 100) : null;
    $slotsLeft = $seminar->max_participants ? max(0, $seminar->max_participants - $rosterTotal) : null;
    $fillPct = $seminar->max_participants ? min(100, (int) round($rosterTotal / $seminar->max_participants * 100)) : null;

    $audience = $seminar->target_course
        ? $seminar->target_course . ($seminar->target_grade_level ? ' · ' . $seminar->target_grade_level : '')
        : ($seminar->target_grade_level ? 'All courses · ' . $seminar->target_grade_level : 'All students');

    // Segmented attendance control: value => [label, classes when selected].
    $attendanceOptions = [
        'enrolled' => ['Pending',  'peer-checked:bg-white peer-checked:text-gray-800 peer-checked:shadow-sm'],
        'attended' => ['Attended', 'peer-checked:bg-emerald-600 peer-checked:text-white peer-checked:shadow-sm'],
        'missed'   => ['Missed',   'peer-checked:bg-red-500 peer-checked:text-white peer-checked:shadow-sm'],
        'excused'  => ['Excused',  'peer-checked:bg-amber-500 peer-checked:text-white peer-checked:shadow-sm'],
    ];
    $avatarColors = ['bg-blue-50 text-blue-600', 'bg-violet-50 text-violet-600', 'bg-emerald-50 text-emerald-600', 'bg-amber-50 text-amber-600', 'bg-rose-50 text-rose-600', 'bg-cyan-50 text-cyan-600'];
    $initials = fn ($s) => mb_strtoupper(mb_substr($s->first_name, 0, 1) . mb_substr($s->last_name, 0, 1));
    $placement = fn ($s) => collect([
        $s->course ? \App\Models\Course::abbreviate($s->course) : null,
        $s->grade_level,
        $s->section,
    ])->filter()->implode(' · ');
@endphp

<div class="mb-6 flex flex-wrap items-center justify-between gap-3" x-data="{ showQrModal: false, showEditModal: {{ $errors->any() && old('id') ? 'true' : 'false' }} }">
    <a href="{{ route('admin.seminars.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 flex items-center gap-1">
        <i class="ti ti-arrow-left"></i> Back to Seminars
    </a>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.seminars.print', $seminar->id) }}" target="_blank" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition shadow-sm gap-2">
            <i class="ti ti-printer"></i> Print Roster
        </a>
        <a href="{{ route('admin.seminars.export', $seminar->id) }}" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition shadow-sm gap-2">
            <i class="ti ti-file-spreadsheet"></i> Export CSV
        </a>
        <button type="button" @click="showQrModal = true; setTimeout(() => generateQR(), 100);" class="inline-flex items-center justify-center px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition shadow-sm gap-2">
            <i class="ti ti-qrcode"></i> Check-in QR
        </button>
        <button type="button" @click="showEditModal = true" class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm gap-2">
            <i class="ti ti-pencil"></i> Edit Details
        </button>
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
                <p class="text-xs text-gray-400 break-all">URL: {{ $checkinUrl }}</p>
                @if($localOnlyQr)
                    <div class="mt-4 p-3 rounded-lg bg-amber-50 border border-amber-200 text-left text-xs text-amber-800 flex gap-2" data-qr-local-warning>
                        <i class="ti ti-alert-triangle mt-0.5 shrink-0"></i>
                        <p>This link points to <strong>{{ $checkinHost }}</strong>, which only works on this computer — students' phones can't open it. Open the system using this computer's network address (e.g. <strong>http://192.168.x.x:8000</strong>) and show the QR from there.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Edit Seminar Modal -->
    <div x-show="showEditModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showEditModal" x-transition.opacity class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" aria-hidden="true" @click="showEditModal = false"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="showEditModal" x-transition.scale.origin.bottom class="inline-block w-full max-w-4xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl sm:p-8">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">Edit Seminar</h3>
                        <p class="text-sm text-gray-500 mt-1">Update details for '{{ $seminar->title }}'.</p>
                    </div>
                    <button type="button" @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 transition bg-gray-50 hover:bg-gray-100 rounded-full p-2">
                        <i class="ti ti-x text-xl"></i>
                    </button>
                </div>

                <form action="{{ route('admin.seminars.update', $seminar->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="id" value="{{ $seminar->id }}">
                    @include('partials.seminar-form-fields', ['seminar' => $seminar])

                    <div class="mt-6 flex items-center justify-end gap-3 pt-5 border-t border-gray-100 bg-gray-50 -mx-8 -mb-8 px-8 py-4 rounded-b-2xl">
                        <button type="button" @click="showEditModal = false" class="px-5 py-2.5 text-sm font-semibold text-gray-600 hover:text-gray-900 transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                            <i class="ti ti-device-floppy"></i> Update Seminar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@if($errors->any() && ! old('id'))
    <div class="mb-6 p-3 rounded-xl bg-red-50 border border-red-100 text-sm text-red-700" data-roster-errors>
        <p class="font-semibold flex items-center gap-1.5"><i class="ti ti-alert-circle"></i> That didn't save:</p>
        <ul class="mt-1 ml-6 list-disc space-y-0.5">
            @foreach($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column: Seminar Details -->
    <div class="lg:col-span-1 flex flex-col gap-6">

        {{-- ── Seminar card ─────────────────────────────────────── --}}
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden" data-seminar-hero>
            <div class="h-1.5 {{ $style['bar'] }}"></div>
            <div class="p-6">
                <div class="flex gap-4">
                    <div class="w-16 shrink-0 rounded-xl overflow-hidden border border-gray-100 text-center bg-white shadow-sm self-start">
                        <div class="{{ $style['tile'] }} text-white text-[10px] font-bold uppercase tracking-wider py-1">{{ $seminar->date->format('M Y') }}</div>
                        <div class="text-2xl font-bold text-gray-900 leading-none pt-2">{{ $seminar->date->format('d') }}</div>
                        <div class="text-[10px] font-medium text-gray-400 uppercase pt-1 pb-2">{{ $seminar->date->format('D') }}</div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5 mb-2">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium border {{ $style['pill'] }}">
                                <i class="ti {{ $style['icon'] }}"></i> {{ $style['label'] }}
                            </span>
                            @if($seminar->is_required)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium border bg-rose-50 text-rose-700 border-rose-100"><i class="ti ti-alert-circle"></i> Required</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium border bg-gray-50 text-gray-500 border-gray-200">Optional</span>
                            @endif
                        </div>
                        <h3 class="font-bold text-gray-900 text-lg leading-snug">{{ $seminar->title }}</h3>
                        <p class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                            <i class="ti ti-clock text-gray-400"></i> {{ $time->format('h:i A') }}
                            @if($when)
                                <span class="text-gray-300">·</span> <span class="font-semibold text-blue-600">{{ $when }}</span>
                            @endif
                        </p>
                    </div>
                </div>

                @if($seminar->description)
                    <p class="text-sm text-gray-500 leading-relaxed mt-4">{{ $seminar->description }}</p>
                @endif

                <dl class="mt-5 pt-5 border-t border-gray-100 space-y-3.5 text-sm">
                    @foreach([
                        ['ti-calendar', 'text-blue-500 bg-blue-50', 'Schedule', $seminar->date->format('l, F j, Y') . ' · ' . $time->format('h:i A')],
                        ['ti-map-pin', 'text-amber-500 bg-amber-50', 'Venue', $seminar->venue],
                        ['ti-microphone', 'text-violet-500 bg-violet-50', 'Speaker', $seminar->speaker ?: 'TBA'],
                        ['ti-target-arrow', 'text-emerald-500 bg-emerald-50', 'Target Audience', $audience],
                        ['ti-sparkles', 'text-fuchsia-500 bg-fuchsia-50', 'ML Trigger', \App\Models\Seminar::TRIGGER_REASONS[$seminar->trigger_reason] ?? 'General Guidance'],
                    ] as [$icon, $iconStyle, $label, $value])
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-lg {{ $iconStyle }} flex items-center justify-center shrink-0"><i class="ti {{ $icon }}"></i></div>
                            <div class="min-w-0">
                                <dt class="text-[11px] font-medium uppercase tracking-wider text-gray-400">{{ $label }}</dt>
                                <dd class="font-medium text-gray-900 break-words">{{ $value }}</dd>
                            </div>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>

        {{-- ── Attendance card ─────────────────────────────────── --}}
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6" data-attendance-card>
            <div class="flex items-center justify-between mb-4">
                <h4 class="font-semibold text-gray-800 flex items-center gap-2"><i class="ti ti-chart-donut text-blue-600"></i> Attendance</h4>
                @if($attendanceRate !== null)
                    <span class="text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-100 rounded-md px-2 py-0.5">{{ $attendanceRate }}% attended</span>
                @endif
            </div>

            <div class="grid grid-cols-4 gap-2 text-center">
                @foreach($attendance as $status => $item)
                    <div class="rounded-xl border {{ $item['bg'] }} py-2.5" data-attendance-count="{{ $status }}">
                        <div class="text-lg font-bold leading-none {{ $item['text'] }}">{{ $item['count'] }}</div>
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 mt-1">{{ $item['label'] }}</div>
                    </div>
                @endforeach
            </div>

            @if($rosterTotal > 0)
                <div class="flex w-full h-2 rounded-full overflow-hidden bg-gray-100 mt-4">
                    @foreach($attendance as $item)
                        @if($item['count'] > 0)
                            <div class="{{ $item['bar'] }} h-2" style="width: {{ $item['count'] / $rosterTotal * 100 }}%" title="{{ $item['label'] }}: {{ $item['count'] }}"></div>
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="mt-5 pt-4 border-t border-gray-100">
                <div class="flex items-center justify-between text-sm mb-1.5">
                    <span class="text-gray-500 flex items-center gap-1.5"><i class="ti ti-users text-gray-400"></i> Roster</span>
                    <span class="font-bold text-gray-900">{{ $rosterTotal }} <span class="text-sm font-medium text-gray-400">/ {{ $seminar->max_participants ?: '∞' }}</span></span>
                </div>
                @if($fillPct !== null)
                    <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                        <div class="{{ $fillPct >= 100 ? 'bg-red-500' : ($fillPct > 80 ? 'bg-amber-500' : 'bg-blue-500') }} h-1.5 rounded-full" style="width: {{ $fillPct }}%"></div>
                    </div>
                    <p class="text-xs text-gray-400 mt-1.5">{{ $slotsLeft === 0 ? 'Full — no slots left' : $slotsLeft . ' slot' . ($slotsLeft === 1 ? '' : 's') . ' left' }}</p>
                @else
                    <p class="text-xs text-gray-400">No capacity limit</p>
                @endif
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
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6">
            <h4 class="font-semibold text-gray-800 flex items-center gap-2"><i class="ti ti-chart-line text-emerald-500"></i> Effectiveness</h4>
            <p class="text-xs text-gray-400 mt-0.5 mb-4">Risk score change 30 days after attending.</p>

            <div class="grid grid-cols-3 gap-2 text-center">
                <div class="bg-emerald-50 rounded-xl py-2.5 border border-emerald-100">
                    <div class="text-lg font-bold text-emerald-600 leading-none">{{ $improved }}</div>
                    <div class="text-[10px] font-semibold text-gray-500 uppercase mt-1">Improved</div>
                </div>
                <div class="bg-gray-50 rounded-xl py-2.5 border border-gray-100">
                    <div class="text-lg font-bold text-gray-700 leading-none">{{ $noChange }}</div>
                    <div class="text-[10px] font-semibold text-gray-500 uppercase mt-1">No Change</div>
                </div>
                <div class="bg-red-50 rounded-xl py-2.5 border border-red-100">
                    <div class="text-lg font-bold text-red-600 leading-none">{{ $worse }}</div>
                    <div class="text-[10px] font-semibold text-gray-500 uppercase mt-1">Worse</div>
                </div>
            </div>
            <div class="text-center text-sm font-medium text-gray-700 mt-4 pt-3 border-t border-gray-100">
                Success rate: <span class="text-emerald-600 font-bold">{{ round(($improved / $totalTracked) * 100) }}%</span>
            </div>
        </div>
        @endif
    </div>

    <div class="lg:col-span-2 flex flex-col gap-6">
        {{-- ── Assign Students ─────────────────────────────────── --}}
        @php
            $assignable = $availableStudents->map(fn ($s) => [
                'id' => (string) $s->id,
                's' => mb_strtolower("{$s->last_name} {$s->first_name} {$s->first_name} {$s->last_name} {$s->student_id_number} {$s->course} " . \App\Models\Course::abbreviate($s->course) . " {$s->grade_level} {$s->section}"),
            ])->values();
        @endphp
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden flex flex-col flex-grow"
             x-data="{
                q: '',
                selected: [],
                students: @js($assignable),
                slotsLeft: @js($slotsLeft),
                matches(text) { const n = this.q.trim().toLowerCase(); return ! n || text.includes(n); },
                shown() { return this.students.filter(s => this.matches(s.s)); },
                selectShown() { this.shown().forEach(s => { if (! this.selected.includes(s.id)) this.selected.push(s.id); }); },
                overCapacity() { return this.slotsLeft !== null && this.selected.length > this.slotsLeft; }
             }">
            <div class="bg-gray-50/50 px-6 py-4 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h4 class="font-semibold text-gray-800 flex items-center gap-2"><i class="ti ti-user-plus text-emerald-600"></i> Assign Students</h4>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ $availableStudents->count() }} eligible {{ $availableStudents->count() === 1 ? 'student' : 'students' }}
                        @if($seminar->target_course || $seminar->target_grade_level) · limited to {{ $audience }} @endif
                    </p>
                </div>
                @if($slotsLeft !== null && $seminar->status !== 'cancelled')
                    <span class="text-xs font-semibold rounded-md px-2 py-0.5 border {{ $slotsLeft === 0 ? 'bg-red-50 text-red-700 border-red-100' : 'bg-blue-50 text-blue-700 border-blue-100' }}">
                        {{ $slotsLeft === 0 ? 'Seminar is full' : $slotsLeft . ' slot' . ($slotsLeft === 1 ? '' : 's') . ' left' }}
                    </span>
                @endif
            </div>
            <div class="p-5 flex flex-col flex-grow">
                @if($seminar->status === 'cancelled')
                    <div class="flex-grow flex flex-col items-center justify-center text-center py-10" data-assign-closed>
                        <div class="w-12 h-12 rounded-full bg-red-50 flex items-center justify-center text-red-400 mb-3"><i class="ti ti-calendar-off text-xl"></i></div>
                        <p class="text-sm font-medium text-gray-900">This seminar is cancelled</p>
                        <p class="text-xs text-gray-500 mt-1">Students can't be assigned to it. Change its status in Edit Details to reopen it.</p>
                    </div>
                @elseif($availableStudents->isEmpty())
                    <div class="flex-grow flex flex-col items-center justify-center text-center py-10" data-assign-empty>
                        <div class="w-12 h-12 rounded-full bg-gray-50 flex items-center justify-center text-gray-400 mb-3"><i class="ti ti-users-group text-xl"></i></div>
                        <p class="text-sm font-medium text-gray-900">No one left to assign</p>
                        <p class="text-xs text-gray-500 mt-1 max-w-sm">
                            Every active student{{ $seminar->target_course || $seminar->target_grade_level ? ' in this seminar\'s target audience' : '' }} is already on the roster.
                        </p>
                    </div>
                @else
                <form action="{{ route('admin.seminars.assign', $seminar->id) }}" method="POST" class="flex flex-col flex-grow">
                    @csrf
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <div class="relative flex-1 min-w-[200px]">
                            <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm"></i>
                            <input type="search" x-model="q" placeholder="Search name, ID, course or section..." aria-label="Search students to assign"
                                class="block w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition">
                        </div>
                        <button type="button" @click="selectShown()" class="px-3 py-2 text-xs font-semibold text-blue-600 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition">
                            Select <span x-text="q.trim() ? 'shown' : 'all'"></span> (<span x-text="shown().length"></span>)
                        </button>
                        <button type="button" x-show="selected.length" x-cloak @click="selected = []" class="px-3 py-2 text-xs font-semibold text-gray-500 hover:text-gray-700 hover:bg-gray-50 rounded-lg transition">Clear</button>
                    </div>

                    <div class="border border-gray-100 rounded-xl divide-y divide-gray-50 overflow-y-auto max-h-72 flex-grow" data-assign-list>
                        @foreach($availableStudents as $i => $student)
                            <label class="flex items-center gap-3 px-3.5 py-2.5 cursor-pointer hover:bg-gray-50/80 transition"
                                   x-show="matches(students[{{ $i }}].s)"
                                   :class="selected.includes('{{ $student->id }}') ? 'bg-blue-50/60 hover:bg-blue-50' : ''">
                                <input type="checkbox" name="student_ids[]" value="{{ $student->id }}" x-model="selected" data-assignable
                                       class="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="w-8 h-8 rounded-full {{ $avatarColors[$student->id % count($avatarColors)] }} flex items-center justify-center text-[11px] font-bold shrink-0">{{ $initials($student) }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-gray-900 truncate">{{ $student->last_name }}, {{ $student->first_name }}</span>
                                    <span class="block text-xs text-gray-500 truncate" title="{{ $student->course }}">{{ $placement($student) ?: 'No placement on file' }}</span>
                                </span>
                                @if($student->student_id_number)
                                    <span class="text-[10px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded uppercase tracking-wider shrink-0">{{ $student->student_id_number }}</span>
                                @endif
                            </label>
                        @endforeach
                        <p x-show="shown().length === 0" x-cloak class="px-4 py-8 text-center text-sm text-gray-400">No students match "<span x-text="q.trim()"></span>".</p>
                    </div>

                    @if($sendsSms)
                        <div class="mt-4 p-3 bg-red-50 rounded-lg text-sm text-red-700 flex gap-2 border border-red-100">
                            <i class="ti ti-alert-triangle mt-0.5"></i>
                            <p>Assigning students will automatically send an SMS to them and their parents.</p>
                        </div>
                    @elseif($seminar->is_required)
                        <div class="mt-4 p-3 bg-gray-50 rounded-lg text-sm text-gray-600 flex gap-2 border border-gray-100">
                            <i class="ti ti-info-circle mt-0.5"></i>
                            <p>This seminar has already taken place, so no SMS will be sent — use this to record who was there.</p>
                        </div>
                    @endif

                    <p x-show="overCapacity()" x-cloak class="mt-3 text-xs font-medium text-red-600 flex items-center gap-1">
                        <i class="ti ti-alert-circle"></i> Only <span x-text="slotsLeft"></span> slot(s) left — deselect some students.
                    </p>

                    <button type="submit" :disabled="selected.length === 0 || overCapacity()"
                            class="mt-4 w-full px-4 py-2.5 bg-emerald-600 text-white text-sm font-semibold rounded-lg hover:bg-emerald-700 transition shadow-sm flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="ti ti-plus"></i> Add to Roster
                        <span x-show="selected.length" x-cloak class="bg-white/20 rounded-md px-1.5 text-xs" x-text="selected.length"></span>
                    </button>
                </form>
                @endif
            </div>
        </div>
    </div>

    <!-- Full Width Bottom Section: Participant List & Attendance
         (mb-16: room to scroll Save Attendance clear of the floating action button) -->
    <div class="lg:col-span-3 mb-16">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden flex flex-col" x-data="{ dirty: false }">
            <div class="bg-gray-50/50 px-6 py-4 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-users text-blue-600"></i> Participant Roster
                    <span class="text-xs font-semibold text-gray-500 bg-gray-100 rounded-md px-1.5 py-0.5">{{ $participants->total() }}</span>
                </h4>
                <div class="flex flex-wrap items-center gap-2">
                    @if($participants->count() > 0)
                        <button type="button" data-mark-all-attended
                                @click="$root.querySelectorAll('input[type=radio][value=attended]').forEach(r => r.checked = true); dirty = true"
                                class="px-3 py-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-100 rounded-lg hover:bg-emerald-100 transition flex items-center gap-1">
                            <i class="ti ti-checks"></i> Mark all attended
                        </button>
                    @endif
                    <form method="GET" action="{{ route('admin.seminars.show', $seminar->id) }}" class="relative w-full sm:w-64">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="ti ti-search text-gray-400"></i>
                        </div>
                        <input type="text" name="search_roster" value="{{ request('search_roster') }}" placeholder="Search roster..." class="block w-full pl-10 pr-3 py-1.5 border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition" onchange="this.form.submit()">
                    </form>
                </div>
            </div>

            <div class="flex-grow">
                @if($participants->count() > 0)
                    <form action="{{ route('admin.seminars.attendance', $seminar->id) }}" method="POST" @change="dirty = true" @input="dirty = true">
                        @csrf
                        @method('PATCH')

                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-white border-b border-gray-100">
                                        <th class="px-6 py-3 font-semibold text-gray-500 text-[11px] uppercase tracking-wider">Student</th>
                                        <th class="px-6 py-3 font-semibold text-gray-500 text-[11px] uppercase tracking-wider">Attendance</th>
                                        <th class="px-6 py-3 font-semibold text-gray-500 text-[11px] uppercase tracking-wider">Remarks</th>
                                        <th class="px-3 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50 text-sm text-gray-700">
                                    @foreach($participants as $student)
                                        <tr class="hover:bg-gray-50/50 transition">
                                            <td class="px-6 py-3.5">
                                                <div class="flex items-center gap-3">
                                                    <span class="w-9 h-9 rounded-full {{ $avatarColors[$student->id % count($avatarColors)] }} flex items-center justify-center text-xs font-bold shrink-0">{{ $initials($student) }}</span>
                                                    <div class="min-w-0">
                                                        <p class="font-medium text-gray-900 flex flex-wrap items-center gap-1.5">
                                                            <span>{{ $student->last_name }}, {{ $student->first_name }}</span>
                                                            @if($student->student_id_number)
                                                                <span class="text-[10px] font-bold text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded uppercase tracking-wider">{{ $student->student_id_number }}</span>
                                                            @endif
                                                            @if($student->pivot->assigned_by === 'ml_system')
                                                                <span class="text-[10px] font-semibold text-violet-700 bg-violet-50 border border-violet-100 px-1.5 py-0.5 rounded" title="Auto-assigned by the risk model">Auto</span>
                                                            @endif
                                                            @if($student->pivot->effectiveness === 'improved')
                                                                <i class="ti ti-trending-down text-emerald-500" title="Risk score dropped after attending"></i>
                                                            @elseif($student->pivot->effectiveness === 'worse')
                                                                <i class="ti ti-trending-up text-red-500" title="Risk score rose after attending"></i>
                                                            @elseif($student->pivot->effectiveness === 'no_change')
                                                                <i class="ti ti-minus text-gray-400" title="No risk change after attending"></i>
                                                            @endif
                                                        </p>
                                                        <p class="text-xs text-gray-500 mt-0.5 truncate" title="{{ $student->course }}">{{ $placement($student) ?: 'No placement on file' }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-3.5">
                                                <div class="inline-flex rounded-lg border border-gray-200 bg-gray-100/70 p-0.5" role="radiogroup" aria-label="Attendance for {{ $student->first_name }} {{ $student->last_name }}">
                                                    @foreach($attendanceOptions as $value => [$label, $checkedClasses])
                                                        <label class="cursor-pointer">
                                                            <input type="radio" name="attendance[{{ $student->id }}][status]" value="{{ $value }}" class="sr-only peer" @checked($student->pivot->status === $value)>
                                                            <span class="block px-2.5 py-1 text-[11px] font-semibold rounded-md text-gray-500 hover:text-gray-800 transition peer-focus-visible:ring-2 peer-focus-visible:ring-blue-300 {{ $checkedClasses }}">{{ $label }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                                @if($student->pivot->status === 'attended' && $student->pivot->attended_at)
                                                    <p class="text-[11px] text-gray-400 mt-1 flex items-center gap-1"><i class="ti ti-clock-check"></i> Checked in {{ $student->pivot->attended_at->format('M d, h:i A') }}</p>
                                                @endif
                                            </td>
                                            <td class="px-6 py-3.5">
                                                <input type="text" name="attendance[{{ $student->id }}][remarks]" value="{{ $student->pivot->remarks }}" placeholder="Optional notes" maxlength="255"
                                                    class="w-full min-w-[160px] text-xs rounded-lg border border-gray-200 focus:border-blue-500 focus:ring focus:ring-blue-100 shadow-sm py-1.5 px-2.5">
                                            </td>
                                            <td class="px-3 py-3.5 text-right">
                                                @if($student->pivot->status === 'attended')
                                                    <span class="p-1.5 text-gray-200 inline-block cursor-not-allowed" title="Marked Attended — change their attendance first to remove them"><i class="ti ti-user-minus"></i></span>
                                                @else
                                                    <button type="button" data-remove-student="{{ $student->id }}" x-data @click="$dispatch('open-confirm-modal', {
                                                            formId: 'remove-student-{{ $student->id }}',
                                                            title: 'Remove from Roster',
                                                            message: @js('Remove ' . $student->first_name . ' ' . $student->last_name . ' from this seminar\'s roster?'),
                                                            confirmText: 'Yes, Remove'
                                                        })" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Remove from roster">
                                                        <i class="ti ti-user-minus"></i>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="px-6 py-4 bg-gray-50/70 border-t border-gray-100 flex flex-col md:flex-row items-center justify-between gap-4">
                            <div class="w-full md:w-auto overflow-x-auto">
                                @if($participants->hasPages())
                                    {{ $participants->links() }}
                                @endif
                            </div>
                            <div class="flex items-center gap-3">
                                <span x-show="dirty" x-cloak class="text-xs font-medium text-amber-600 flex items-center gap-1" data-unsaved-hint><i class="ti ti-point-filled"></i> Unsaved changes</span>
                                <button type="submit" class="px-5 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-2 whitespace-nowrap">
                                    <i class="ti ti-check"></i> Save Attendance
                                </button>
                            </div>
                        </div>
                    </form>
                    @foreach($participants as $student)
                        @continue($student->pivot->status === 'attended')
                        <form id="remove-student-{{ $student->id }}" action="{{ route('admin.seminars.students.remove', [$seminar->id, $student->id]) }}" method="POST" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                    @endforeach
                @else
                    <div class="py-16 flex flex-col items-center justify-center text-center">
                        <div class="w-14 h-14 bg-blue-50 rounded-full flex items-center justify-center text-blue-300 mb-3">
                            <i class="ti ti-users-minus text-2xl"></i>
                        </div>
                        @if(request()->filled('search_roster'))
                            <h3 class="text-sm font-semibold text-gray-900 mb-1">No one on the roster matches "{{ request('search_roster') }}"</h3>
                            <a href="{{ route('admin.seminars.show', $seminar->id) }}" class="text-sm font-medium text-blue-600 hover:text-blue-700">Clear search</a>
                        @else
                            <h3 class="text-sm font-semibold text-gray-900 mb-1">No participants yet</h3>
                            <p class="text-sm text-gray-500 max-w-sm">Use Assign Students above to add students to this seminar's roster.</p>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('vendor/qrcode.min.js') }}"></script>
<script>
    let qrGenerated = false;
    function generateQR() {
        if(qrGenerated) return;
        new QRCode(document.getElementById("qrcode"), {
            text: @js($checkinUrl),
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
