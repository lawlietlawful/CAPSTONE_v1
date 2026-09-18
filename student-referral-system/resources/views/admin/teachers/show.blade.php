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
        <a href="{{ route('counselor.messages.index') }}" class="px-4 py-2 bg-indigo-600 border border-transparent text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition shadow-sm flex items-center gap-1.5">
            <i class="ti ti-send text-white"></i> Send Notice
        </a>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Left Column: Profile + Stats -->
    <div class="lg:col-span-1 space-y-6">

        <!-- Profile Card -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6 text-center transition-all duration-300 hover:shadow-hover">
            <div class="w-20 h-20 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-3xl mx-auto mb-4">
                {{ strtoupper(substr($teacher->name, 0, 1)) }}
            </div>
            <h3 class="text-xl font-bold text-gray-900">{{ $teacher->name }}</h3>
            <p class="text-sm text-gray-500 mt-0.5">{{ $teacher->email }}</p>
            @if($teacher->username)
                <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-gray-100 text-gray-700 text-xs font-mono mt-2 mb-2">
                    @<span>{{ $teacher->username }}</span>
                </span>
            @endif
            
            <div class="mt-3">
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
            </div>

            <p class="text-xs text-gray-400 mt-4">Joined {{ $teacher->created_at->format('F j, Y') }}</p>
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

            </div>
        </div>

        <!-- Course Assignments -->
        <div x-data='{
                showAssignmentsModal: {{ $errors->any() ? "true" : "false" }},
                assignments: {{ old("assignments") ? json_encode(old("assignments")) : $teacher->teacherAssignments->map(fn($a) => ["course" => $a->course, "grade_level" => $a->grade_level, "section" => $a->section])->values()->toJson() }},
                combos: @json($courseCombos),
                addAssignment() { this.assignments.push({ course: "", grade_level: "", section: "" }); },
                removeAssignment(i) { this.assignments.splice(i, 1); },
                coursesFor() { return [...new Set(this.combos.map(c => c.course))]; },
                gradesFor(course) { return [...new Set(this.combos.filter(c => c.course === course).map(c => c.grade_level))]; },
                sectionsFor(course, grade) { return [...new Set(this.combos.filter(c => c.course === course && (!grade || c.grade_level === grade)).map(c => c.section))]; }
            }'>

            <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6 transition-all duration-300 hover:shadow-hover">
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
                    <div x-show="showAssignmentsModal" x-transition.scale.origin.bottom class="inline-block w-full max-w-2xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl sm:p-8">
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

                            <template x-for="(a, index) in assignments" :key="index">
                                <div class="grid grid-cols-12 gap-2 mb-2 items-center">
                                    <div class="col-span-4">
                                        <select :name="'assignments[' + index + '][course]'"
                                            @change="a.course = $event.target.value; a.grade_level = ''; a.section = '';"
                                            class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition">
                                            <option value="">Select course...</option>
                                            <template x-for="c in coursesFor()" :key="c">
                                                <option :value="c" x-text="c" :selected="c === a.course"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div class="col-span-3">
                                        <select :name="'assignments[' + index + '][grade_level]'"
                                            @change="a.grade_level = $event.target.value; a.section = '';"
                                            class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition">
                                            <option value="">All year levels</option>
                                            <template x-for="g in gradesFor(a.course)" :key="g">
                                                <option :value="g" x-text="g" :selected="g === a.grade_level"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div class="col-span-4">
                                        <select :name="'assignments[' + index + '][section]'"
                                            @change="a.section = $event.target.value"
                                            class="w-full rounded-lg border-gray-300 text-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition">
                                            <option value="">All sections</option>
                                            <template x-for="s in sectionsFor(a.course, a.grade_level)" :key="s">
                                                <option :value="s" x-text="s" :selected="s === a.section"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div class="col-span-1 flex items-center justify-center">
                                        <button type="button" @click="removeAssignment(index)" class="text-red-400 hover:text-red-600">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>

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
    <div class="lg:col-span-2 space-y-6">


        <!-- Recent Behavioral Reports -->
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden transition-all duration-300 hover:shadow-hover">
            <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                <h4 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-message-report text-amber-600"></i> Recent Behavioral Reports
                </h4>
            </div>
            <div class="overflow-x-auto overflow-y-auto custom-scrollbar" style="max-height: 480px;">
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
    </div>
</div>

@endsection
