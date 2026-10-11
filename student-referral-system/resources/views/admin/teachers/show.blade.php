@extends('layouts.admin')

@section('title', 'Teacher Profile')
@section('page-title', 'Teacher Profile')
@section('page-sub', 'View teacher activity and contribution details')

@section('content')

<div class="mb-6 flex justify-between items-center">
    <a href="{{ route('admin.teachers.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 flex items-center gap-1">
        <i class="ti ti-arrow-left"></i> Back to Teachers
    </a>
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.teachers.print', $teacher->id) }}" target="_blank" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition shadow-sm flex items-center gap-1.5">
            <i class="ti ti-printer text-blue-600"></i> Print / Export PDF
        </a>
        @if($teacher->isDeactivated())
            {{-- Message::canInitiate refuses a deactivated recipient — say so
                 here rather than letting Compose fail. --}}
            <span class="px-4 py-2 bg-gray-100 border border-gray-200 text-gray-400 text-sm font-medium rounded-lg flex items-center gap-1.5 cursor-not-allowed" title="This account is deactivated and can't receive notices" data-send-notice-disabled>
                <i class="ti ti-send"></i> Send Notice
            </span>
        @else
            <button type="button" x-data="{}" @click="$dispatch('open-messages', { to: {{ $teacher->id }} })" class="px-4 py-2 bg-indigo-600 border border-transparent text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition shadow-sm flex items-center gap-1.5">
                <i class="ti ti-send text-white"></i> Send Notice
            </button>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Left Column: Profile + Stats -->
    <div class="lg:col-span-1 flex flex-col gap-6">

        <!-- Profile Card -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-5 transition-all duration-300 hover:shadow-hover">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-full bg-gradient-to-tr from-blue-100 to-indigo-50 text-blue-700 flex items-center justify-center text-lg font-bold shrink-0 ring-4 ring-white shadow-sm">
                    {{ strtoupper(substr($teacher->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <h3 class="font-bold text-gray-900 truncate">{{ $teacher->name }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $teacher->email }}</p>
                    @if($teacher->username)
                        <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-gray-700 text-[11px] font-mono mt-1">
                            @<span>{{ $teacher->username }}</span>
                        </span>
                    @endif
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-100 flex items-center justify-between">
                @if($teacher->engagement_status === 'highly_active')
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                        <i class="ti ti-bolt mr-1"></i> Highly Active
                    </span>
                @elseif($teacher->engagement_status === 'active')
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200">
                        <i class="ti ti-activity mr-1"></i> Active
                    </span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-50 text-red-700 border border-red-200">
                        <i class="ti ti-moon mr-1"></i> Inactive
                    </span>
                @endif
                <p class="text-xs text-gray-400">Joined {{ $teacher->created_at->format('M j, Y') }}</p>
            </div>

            @if($teacher->isDeactivated())
                <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600 flex items-start gap-1.5" data-deactivated-banner>
                    <i class="ti ti-user-off mt-0.5"></i>
                    <span>Deactivated {{ $teacher->deactivated_at->format('M j, Y') }} — can't sign in. Their records below are kept. Reactivate from User Management.</span>
                </div>
            @endif
        </div>

        <!-- Activity Stats -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6 transition-all duration-300 hover:shadow-hover">
            <h4 class="font-semibold text-gray-800 mb-4 flex items-center gap-2">
                <i class="ti ti-chart-bar text-blue-600"></i> Activity Summary
            </h4>
            <div class="space-y-4">

                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600 flex items-center gap-2">
                        <i class="ti ti-message-report text-amber-500"></i> Behavioral Reports Filed
                    </span>
                    <span class="text-lg font-bold text-gray-900">{{ number_format($totalReports) }}</span>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600 flex items-center gap-2">
                        <i class="ti ti-file-export text-purple-500"></i> Referrals Filed
                    </span>
                    <span class="text-lg font-bold text-gray-900">{{ number_format($totalReferrals) }}</span>
                </div>

            </div>
        </div>

        <!-- Course Assignments -->
        <div x-data='{
                showAssignmentsModal: {{ $errors->any() ? "true" : "false" }},
                assignments: {{ old("assignments") ? json_encode(old("assignments")) : $teacher->teacherAssignments->map(fn($a) => ["course" => $a->course, "grade_level" => $a->grade_level, "section" => $a->section])->values()->toJson() }},
                combos: @json($courseCombos),
                addAssignment() { this.assignments.push({ course: "", grade_level: "", section: "" }); },
                removeAssignment(i) { this.assignments.splice(i, 1); },
                // For a teacher who advises several sections of the same course/year —
                // copies the row right below it so only the Section needs changing.
                duplicateAssignment(i) { this.assignments.splice(i + 1, 0, { ...this.assignments[i] }); },
                coursesFor() { return [...new Set(this.combos.map(c => c.course))]; },
                gradesFor(course) { return [...new Set(this.combos.filter(c => c.course === course).map(c => c.grade_level))]; },
                sectionsFor(course, grade) { return [...new Set(this.combos.filter(c => c.course === course && (!grade || c.grade_level === grade)).map(c => c.section))]; }
            }' class="flex-1 flex flex-col">

            <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6 transition-all duration-300 hover:shadow-hover flex-1 flex flex-col">
                <div class="flex items-center justify-between mb-4">
                    <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                        <i class="ti ti-clipboard-list text-blue-600"></i> Course Assignments
                    </h4>
                    <button type="button" @click="showAssignmentsModal = true" class="text-xs font-medium text-blue-600 hover:text-blue-700 flex items-center gap-1">
                        <i class="ti ti-edit"></i> Edit
                    </button>
                </div>

                @if($teacher->teacherAssignments->isEmpty())
                    <p class="text-sm text-amber-600 flex items-start gap-1.5">
                        <i class="ti ti-alert-triangle mt-0.5"></i>
                        <span>Not assigned to any course yet — this teacher cannot see any students.</span>
                    </p>
                @else
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($teacher->teacherAssignments as $a)
                            <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700">
                                {{ $a->course }}{{ $a->grade_level ? ' · ' . $a->grade_level : '' }}{{ $a->section ? ' · ' . $a->section : '' }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Edit Assignments Modal -->
            <div x-show="showAssignmentsModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
                <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="showAssignmentsModal" x-transition.opacity class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" aria-hidden="true" @click="showAssignmentsModal = false"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                    <div x-show="showAssignmentsModal" x-transition.scale.origin.bottom class="inline-block w-full max-w-3xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl sm:p-8">
                        <div class="flex items-center justify-between mb-5">
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">Course Assignments</h3>
                                <p class="text-sm text-gray-500 mt-1">Set the courses/sections {{ $teacher->name }} advises.</p>
                            </div>
                            <button type="button" @click="showAssignmentsModal = false" class="text-gray-400 hover:text-gray-600 transition bg-gray-50 hover:bg-gray-100 rounded-full p-2">
                                <i class="ti ti-x text-xl"></i>
                            </button>
                        </div>

                        <form action="{{ route('admin.teachers.assignments.update', $teacher->id) }}" method="POST">
                            @csrf
                            @method('PUT')

                            <p class="text-xs text-gray-500 mb-3">
                                Leave Year Level or Section blank to cover the whole course or whole year level. A teacher with no assignments will not see any students.
                            </p>

                            @include('partials.course-assignment-rows')

                            <button type="button" @click="addAssignment()"
                                class="text-sm text-blue-600 hover:text-blue-700 flex items-center gap-1.5 mt-2">
                                <i class="ti ti-plus"></i> Add a course assignment
                            </button>

                            <p x-show="assignments.length === 0" class="text-sm text-amber-600 mt-2">
                                No assignments — this teacher will not see any students.
                            </p>

                            <div class="mt-6 flex items-center justify-end gap-3 pt-5 border-t border-gray-100">
                                <button type="button" @click="showAssignmentsModal = false" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                    Cancel
                                </button>
                                <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                                    <i class="ti ti-device-floppy"></i> Save Assignments
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>


    </div>

    <!-- Right Column: Recent Activity Tables -->
    <div class="lg:col-span-2 flex flex-col gap-6">

        <!-- Recent Behavioral Reports -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden transition-all duration-300 hover:shadow-hover">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-message-report text-amber-600"></i> Recent Behavioral Reports
                </h4>
            </div>
            <div class="overflow-x-auto overflow-y-auto custom-scrollbar" style="max-height: 320px;">
                <table class="w-full text-left border-collapse relative">
                    <thead class="sticky top-0 bg-white z-10 shadow-sm">
                        <tr class="bg-white border-b border-gray-100">
                            <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Student</th>
                            <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Incident</th>
                            <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider text-center">Severity</th>
                            <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider text-center">Status</th>
                            <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @forelse($recentReports as $report)
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-5 py-2.5">
                                    <p class="font-medium text-gray-900">{{ $report->student->last_name }}, {{ $report->student->first_name }}</p>
                                </td>
                                <td class="px-5 py-2.5 text-gray-600">{{ $report->incident_type }}</td>
                                <td class="px-5 py-2.5 text-center">
                                    @php
                                        $sevClass = match($report->severity) {
                                            'severe', 'Critical', 'High' => 'bg-red-50 text-red-700',
                                            'moderate', 'Medium'         => 'bg-amber-50 text-amber-700',
                                            default                      => 'bg-green-50 text-green-700',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $sevClass }}">
                                        {{ ucfirst($report->severity) }}
                                    </span>
                                </td>
                                <td class="px-5 py-2.5 text-center">
                                    @php
                                        $stClass = match($report->status) {
                                            'pending'  => 'bg-blue-50 text-blue-700',
                                            'reviewed' => 'bg-amber-50 text-amber-700',
                                            'resolved' => 'bg-green-50 text-green-700',
                                            default    => 'bg-gray-100 text-gray-500',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $stClass }}">
                                        {{ ucfirst($report->status) }}
                                    </span>
                                </td>
                                <td class="px-5 py-2.5 text-xs text-gray-500">{{ $report->incident_date->format('M d, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-sm text-gray-400">No behavioral reports filed.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Referrals -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden transition-all duration-300 hover:shadow-hover" data-recent-referrals>
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-file-export text-purple-600"></i> Recent Referrals
                </h4>
            </div>
            <div class="overflow-x-auto overflow-y-auto custom-scrollbar" style="max-height: 320px;">
                <table class="w-full text-left border-collapse relative">
                    <thead class="sticky top-0 bg-white z-10 shadow-sm">
                        <tr class="bg-white border-b border-gray-100">
                            <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Student</th>
                            <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Type</th>
                            <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider text-center">Priority</th>
                            <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider text-center">Status</th>
                            <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @forelse($recentReferrals as $referral)
                            <tr class="hover:bg-gray-50/50 transition">
                                <td class="px-5 py-2.5">
                                    <a href="{{ route('admin.referrals.show', $referral->id) }}" class="font-medium text-gray-900 hover:text-blue-600 hover:underline transition">
                                        {{ $referral->student->last_name }}, {{ $referral->student->first_name }}
                                    </a>
                                </td>
                                <td class="px-5 py-2.5 text-gray-600">{{ $referral->referral_type_label }}</td>
                                <td class="px-5 py-2.5 text-center">
                                    @php
                                        $priClass = match($referral->priority) {
                                            'high'     => 'bg-red-50 text-red-700',
                                            'moderate' => 'bg-amber-50 text-amber-700',
                                            default    => 'bg-green-50 text-green-700',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $priClass }}">
                                        {{ ucfirst($referral->priority) }}
                                    </span>
                                </td>
                                <td class="px-5 py-2.5 text-center">
                                    @php
                                        $stClass = match($referral->status) {
                                            'pending'     => 'bg-blue-50 text-blue-700',
                                            'in_progress' => 'bg-amber-50 text-amber-700',
                                            'resolved'    => 'bg-green-50 text-green-700',
                                            default       => 'bg-gray-100 text-gray-500',
                                        };
                                    @endphp
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $stClass }}">
                                        {{ ucwords(str_replace('_', ' ', $referral->status)) }}
                                    </span>
                                </td>
                                <td class="px-5 py-2.5 text-xs text-gray-500">{{ $referral->created_at->format('M d, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-8 text-center text-sm text-gray-400">No referrals filed.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Notices -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden transition-all duration-300 hover:shadow-hover flex-1 flex flex-col" data-recent-notices x-data="{}">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-messages text-indigo-600"></i> Recent Notices
                </h4>
                <span class="text-xs text-gray-400">Between guidance and {{ explode(' ', $teacher->name)[0] }}</span>
            </div>
            <div class="flex-1 overflow-y-auto custom-scrollbar divide-y divide-gray-50" style="max-height: 320px;">
                @forelse($recentNotices as $notice)
                    @php
                        $teacherIsSender = $notice->sender_id === $teacher->id;
                        $otherParty = $teacherIsSender ? $notice->receiver : $notice->sender;
                        // A third-party admin can only open a thread they're actually
                        // part of (Message::show() checks that) — in today's
                        // single-counselor setup that's always true, but this stays
                        // correct if a second counselor's notices ever show up here.
                        $canOpen = in_array(auth()->id(), [$notice->sender_id, $notice->receiver_id], true);
                    @endphp
                    <div class="px-6 py-3 flex items-start gap-3 {{ $canOpen ? 'hover:bg-gray-50 cursor-pointer transition' : '' }}"
                         @if($canOpen) @click="$dispatch('open-messages', { thread: {{ $notice->id }} })" @endif>
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold shrink-0 mt-0.5 {{ $teacherIsSender ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-500' }}">
                            {{ strtoupper(substr($otherParty->name ?? '?', 0, 1)) }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-medium text-gray-900 truncate">
                                    {{ $teacherIsSender ? 'To ' : 'From ' }}{{ $otherParty->name ?? 'Unknown' }}
                                </p>
                                <span class="text-[11px] text-gray-400 whitespace-nowrap">{{ $notice->created_at->diffForHumans() }}</span>
                            </div>
                            @if($notice->subject)
                                <p class="text-xs font-medium text-gray-600 truncate mt-0.5">{{ $notice->subject }}</p>
                            @endif
                            <p class="text-xs text-gray-500 truncate mt-0.5">{{ Str::limit($notice->content, 70) }}</p>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-sm text-gray-400">No notices sent yet.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@endsection
