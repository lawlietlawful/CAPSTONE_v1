@extends('layouts.admin')

@section('title', 'Students Management')
@section('page-title', 'Students')
@section('page-sub', 'Manage student records and profiles')

@section('content')

<div x-data='{
        showAddModal: {{ $errors->any() && !old('edit_student_id') ? 'true' : 'false' }},
        showImportModal: {{ (session('import_results') || $errors->has('csv_file')) ? 'true' : 'false' }},
        education_level: "{{ old("education_level", "College") }}",
        course: "{{ old('course') }}",
        grade_level: "{{ old('grade_level') }}",
        strand: "{{ old('strand') }}",
        section: "{{ old('section') }}",
        combos: @json($courseCombos),
        seniorHighGrades: {{ json_encode($seniorHighGrades) }},
        isBasicEd() { return this.education_level === "Basic Education"; },
        isSeniorHigh() { return this.isBasicEd() && this.seniorHighGrades.includes(this.grade_level); },
        coursesFor() { return [...new Set(this.combos.filter(c => c.education_level === this.education_level).map(c => c.course))]; },
        gradesFor(course) { return [...new Set(this.combos.filter(c => c.course === course && c.education_level === this.education_level).map(c => c.grade_level))]; },
        sectionsFor(course, grade) { return [...new Set(this.combos.filter(c => c.course === course && c.education_level === this.education_level && (!grade || c.grade_level === grade)).map(c => c.section))]; }
    }'>

{{-- ── Summary Cards ─────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-5">
    <a href="{{ route('admin.students.index') }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-gray-300 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center shrink-0">
            <i class="ti ti-users text-gray-500 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-gray-900 leading-none">{{ number_format($totalStudents) }}</div>
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">Total Students</div>
        </div>
    </a>
    <a href="{{ route('admin.students.index', ['status' => 'Active']) }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-emerald-200 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
            <i class="ti ti-user-check text-emerald-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-emerald-700 leading-none">{{ number_format($activeStudents) }}</div>
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">Active Students</div>
        </div>
    </a>
    <a href="{{ route('admin.students.index', ['has_referrals' => 1]) }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-blue-200 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
            <i class="ti ti-file-text text-blue-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-blue-700 leading-none">{{ number_format($studentsWithReferrals) }}</div>
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">With Referrals</div>
        </div>
    </a>
    <a href="{{ route('admin.students.index', ['risk_level' => 'at_risk']) }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-red-200 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-red-50 flex items-center justify-center shrink-0">
            <i class="ti ti-alert-triangle text-red-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-red-700 leading-none">{{ number_format($atRiskStudents) }}</div>
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">At-Risk Students</div>
        </div>
    </a>
</div>

<div class="mb-6">
    <form action="{{ route('admin.students.index') }}" method="GET" class="w-full" id="filterForm">
        {{-- Not exposed as visible controls — set only via the summary cards
             above — but carried forward here so touching any dropdown or the
             search box (both auto-submit this form) doesn't silently drop
             the card's filter. --}}
        <input type="hidden" name="risk_level" value="{{ $riskFilter }}">
        <input type="hidden" name="has_referrals" value="{{ $hasReferrals ? 1 : '' }}">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-4 flex flex-col lg:flex-row lg:flex-wrap gap-3 items-end">
            <div class="flex-1 w-full min-w-[220px] relative">
                <label class="block text-xs font-medium text-gray-500 mb-1">Search Student</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="ti ti-search text-gray-400"></i>
                    </div>
                    <input type="text" id="searchInput" name="search" value="{{ $search }}" placeholder="Search by name or ID..."
                        class="block w-full pl-10 pr-3 py-2 border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition" autocomplete="off">
                </div>
            </div>

            <div class="w-full lg:w-48">
                <label class="block text-xs font-medium text-gray-500 mb-1">Course</label>
                <select name="course" onchange="document.getElementById('filterForm').submit();" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                    <option value="">All Courses</option>
                    @foreach($courses as $c)
                        <option value="{{ $c }}" {{ $course === $c ? 'selected' : '' }}>{{ $c }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-full lg:w-40">
                <label class="block text-xs font-medium text-gray-500 mb-1">Education Level</label>
                <select name="education_level" onchange="document.getElementById('filterForm').submit();" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                    <option value="">All Levels</option>
                    <option value="College" {{ ($educationLevel ?? '') === 'College' ? 'selected' : '' }}>College</option>
                    <option value="Basic Education" {{ ($educationLevel ?? '') === 'Basic Education' ? 'selected' : '' }}>Basic Education</option>
                </select>
            </div>

            <div class="w-full lg:w-40">
                <label class="block text-xs font-medium text-gray-500 mb-1">Grade/Year Level</label>
                <select name="grade_level" onchange="document.getElementById('filterForm').submit();" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                    <option value="">All Grades/Years</option>
                    <optgroup label="College">
                        @foreach(\App\Models\Course::COLLEGE_YEAR_LEVELS as $gl)
                            <option value="{{ $gl }}" {{ $grade_level === $gl ? 'selected' : '' }}>{{ $gl }}</option>
                        @endforeach
                    </optgroup>
                    <optgroup label="Basic Education">
                        @foreach(\App\Models\Course::BASIC_ED_GRADE_LEVELS as $gl)
                            <option value="{{ $gl }}" {{ $grade_level === $gl ? 'selected' : '' }}>{{ $gl }}</option>
                        @endforeach
                    </optgroup>
                </select>
            </div>

            <div class="w-full lg:w-40">
                <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select name="status" onchange="document.getElementById('filterForm').submit();" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                    <option value="">All Statuses</option>
                    <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    <option value="transferred" {{ $status === 'transferred' ? 'selected' : '' }}>Transferred</option>
                    <option value="graduated" {{ $status === 'graduated' ? 'selected' : '' }}>Graduated</option>
                </select>
            </div>

            <div class="flex gap-2 w-full lg:w-auto mt-3 lg:mt-0">
                <a href="{{ route('admin.students.index') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                    <i class="ti ti-x"></i> Reset
                </a>
                <button type="button" @click="showImportModal = true" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition shadow-sm flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                    <i class="ti ti-upload"></i> Import Students
                </button>
                <button type="button" @click="showAddModal = true" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                    <i class="ti ti-plus"></i> Add Student
                </button>
            </div>
        </div>
    </form>
</div>

<div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50/50 border-b border-gray-100">
                    <th class="px-6 py-4 font-semibold text-gray-600 text-xs uppercase tracking-wider">Student ID</th>
                    <th class="px-6 py-4 font-semibold text-gray-600 text-xs uppercase tracking-wider">Name</th>
                    <th class="px-6 py-4 font-semibold text-gray-600 text-xs uppercase tracking-wider">Course</th>
                    <th class="px-6 py-4 font-semibold text-gray-600 text-xs uppercase tracking-wider">Grade/Section</th>
                    <th class="px-6 py-4 font-semibold text-gray-600 text-xs uppercase tracking-wider">Status</th>
                    <th class="px-6 py-4 font-semibold text-gray-600 text-xs uppercase tracking-wider text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm text-gray-700">
                @forelse($students as $student)
                    <tr class="hover:bg-gray-50/50 transition">
                        <td class="px-6 py-4 font-medium text-gray-900">{{ $student->student_id_number }}</td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs">
                                    {{ substr($student->first_name, 0, 1) }}{{ substr($student->last_name, 0, 1) }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <a href="{{ route('admin.students.show', $student->id) }}" class="font-medium text-gray-900 hover:text-blue-600 hover:underline transition">
                                            {{ $student->first_name }} {{ $student->last_name }}
                                        </a>
                                        @if($student->latestRiskAssessment && in_array($student->latestRiskAssessment->risk_level, ['high', 'moderate']))
                                            @php
                                                $riskBadgeClass = $student->latestRiskAssessment->risk_level === 'high'
                                                    ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800';
                                            @endphp
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold {{ $riskBadgeClass }}">
                                                <i class="ti ti-brain text-[10px]"></i> {{ ucfirst($student->latestRiskAssessment->risk_level) }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-gray-500">{{ $student->gender }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col gap-1 items-start max-w-[220px]">
                                <span class="inline-block px-2 py-1 rounded-md text-xs font-medium leading-snug bg-indigo-50 text-indigo-700 border border-indigo-100">
                                    {{ $student->course ?? 'N/A' }}
                                </span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-medium
                                    {{ $student->education_level === 'Basic Education' ? 'bg-purple-50 text-purple-700 border border-purple-100' : 'bg-blue-50 text-blue-700 border border-blue-100' }}">
                                    {{ $student->education_level }}
                                </span>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <p class="font-medium text-gray-700">{{ $student->grade_level }}@if($student->strand) · {{ $student->strand }} @endif</p>
                            <p class="text-xs text-gray-500">{{ $student->section }}</p>
                        </td>
                        <td class="px-6 py-4">
                            @if($student->status === 'active')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200">Active</span>
                            @elseif($student->status === 'inactive')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">Inactive</span>
                            @elseif($student->status === 'transferred')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">Transferred</span>
                            @elseif($student->status === 'graduated')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">Graduated</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('admin.students.show', $student->id) }}" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View Profile">
                                    <i class="ti ti-eye"></i>
                                </a>
                                <button type="button" @click="$dispatch('open-edit-modal-{{ $student->id }}')" class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition" title="Edit Student">
                                    <i class="ti ti-pencil"></i>
                                </button>
                                <form id="delete-student-{{ $student->id }}" action="{{ route('admin.students.destroy', $student->id) }}" method="POST" class="inline-block">
                                    @csrf
                                    @method('DELETE')
                                    @php
                                        // Deleting a student cascades to permanently erase all of
                                        // this — the confirmation needs to say so, not just "are
                                        // you sure?", since there's no undo once it's gone.
                                        $recordParts = [];
                                        if ($student->referrals_count > 0) {
                                            $recordParts[] = $student->referrals_count . ' referral' . ($student->referrals_count === 1 ? '' : 's');
                                        }
                                        if ($student->behavioral_reports_count > 0) {
                                            $recordParts[] = $student->behavioral_reports_count . ' behavioral report' . ($student->behavioral_reports_count === 1 ? '' : 's');
                                        }
                                        if ($student->risk_assessments_count > 0) {
                                            $recordParts[] = $student->risk_assessments_count . ' risk assessment' . ($student->risk_assessments_count === 1 ? '' : 's');
                                        }
                                        $deleteMessage = count($recordParts) > 0
                                            ? 'Deleting ' . $student->first_name . ' will also permanently erase ' . implode(', ', $recordParts) . ' on file for them — this cannot be undone. If you just need to remove them from active rosters, consider changing their status to Inactive/Transferred/Graduated instead.'
                                            : 'Are you sure you want to delete ' . $student->first_name . '? This action cannot be undone.';
                                    @endphp
                                    <button type="button" @click="$dispatch('open-confirm-modal', {
                                            formId: 'delete-student-{{ $student->id }}',
                                            title: 'Delete Student',
                                            message: '{{ addslashes($deleteMessage) }}',
                                            confirmText: 'Yes, Delete Student'
                                        })" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete Student">
                                        <i class="ti ti-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-10 text-center text-gray-500">
                            <div class="flex flex-col items-center justify-center">
                                <i class="ti ti-users-group text-4xl text-gray-300 mb-2"></i>
                                <p>No students found.</p>
                                @if($search)
                                    <p class="text-sm mt-1">Try adjusting your search query.</p>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if($students->hasPages())
        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/30 flex justify-end">
            {{ $students->appends(request()->query())->links() }}
        </div>
    @endif
</div>

<!-- Add Student Modal -->
<div x-show="showAddModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div x-show="showAddModal" x-transition.opacity class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" aria-hidden="true" @click="showAddModal = false"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div x-show="showAddModal" x-transition.scale.origin.bottom class="inline-block w-full max-w-4xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl sm:p-8">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Add New Student</h3>
                    <p class="text-sm text-gray-500 mt-1">Register a new student and automatically create their portal account.</p>
                </div>
                <button type="button" @click="showAddModal = false" class="text-gray-400 hover:text-gray-600 transition bg-gray-50 hover:bg-gray-100 rounded-full p-2">
                    <i class="ti ti-x text-xl"></i>
                </button>
            </div>

            <form action="{{ route('admin.students.store') }}" method="POST">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                    
                    <!-- Left Column: Personal Details -->
                    <div class="space-y-4">
                        <h4 class="font-semibold text-gray-800 border-b border-gray-100 pb-2 flex items-center gap-2 text-sm uppercase tracking-wider">
                            <i class="ti ti-user-edit text-blue-600"></i> Personal Details
                        </h4>
                        
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Student ID Number <span class="text-red-500">*</span></label>
                            <input type="text" name="student_id_number" value="{{ old('student_id_number') }}" required
                                class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('student_id_number') border-red-500 @enderror">
                            @error('student_id_number') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">First Name <span class="text-red-500">*</span></label>
                                <input type="text" name="first_name" value="{{ old('first_name') }}" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('first_name') border-red-500 @enderror">
                                @error('first_name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Last Name <span class="text-red-500">*</span></label>
                                <input type="text" name="last_name" value="{{ old('last_name') }}" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('last_name') border-red-500 @enderror">
                                @error('last_name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Middle Name</label>
                                <input type="text" name="middle_name" value="{{ old('middle_name') }}"
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('middle_name') border-red-500 @enderror">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Gender <span class="text-red-500">*</span></label>
                                <select name="gender" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('gender') border-red-500 @enderror">
                                    <option value="">Select...</option>
                                    <option value="Male" {{ old('gender') === 'Male' ? 'selected' : '' }}>Male</option>
                                    <option value="Female" {{ old('gender') === 'Female' ? 'selected' : '' }}>Female</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Birthdate <span class="text-red-500">*</span></label>
                                <input type="date" name="birthdate" value="{{ old('birthdate') }}" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('birthdate') border-red-500 @enderror">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Student Contact</label>
                                <input type="text" name="student_contact" value="{{ old('student_contact') }}" placeholder="e.g. 09123456789"
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('student_contact') border-red-500 @enderror">
                            </div>
                        </div>
                        <div class="mt-4">
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Home Address <span class="text-red-500">*</span></label>
                            <input type="text" name="address" value="{{ old('address') }}" required
                                class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('address') border-red-500 @enderror">
                        </div>
                    </div>

                    <!-- Right Column: Academic & Parent Details -->
                    <div class="space-y-4">
                        <h4 class="font-semibold text-gray-800 border-b border-gray-100 pb-2 flex items-center gap-2 text-sm uppercase tracking-wider">
                            <i class="ti ti-school text-amber-600"></i> Academic Details
                        </h4>
                        
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Education Level <span class="text-red-500">*</span></label>
                            <select name="education_level" x-model="education_level" @change="course = ''; grade_level = ''; strand = ''; section = '';" required
                                class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('education_level') border-red-500 @enderror">
                                <option value="College" {{ old('education_level', 'College') === 'College' ? 'selected' : '' }}>College</option>
                                <option value="Basic Education" {{ old('education_level') === 'Basic Education' ? 'selected' : '' }}>Basic Education</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Course / Program <span class="text-red-500">*</span></label>
                            <select name="course" x-model="course" @change="grade_level = ''; strand = ''; section = '';" required
                                class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('course') border-red-500 @enderror">
                                <option value="">Select course...</option>
                                <template x-for="c in coursesFor()" :key="c">
                                    <option :value="c" x-text="c" :selected="c === course"></option>
                                </template>
                            </select>
                            <p class="text-[11px] text-gray-500 mt-1">
                                No options? <a href="{{ route('admin.courses.index') }}" class="text-blue-600 hover:underline">Add a course</a> first.
                            </p>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div class="col-span-1">
                                <label class="block text-xs font-semibold text-gray-600 mb-1" x-text="isBasicEd() ? 'Grade *' : 'Year *'"></label>
                                <select name="grade_level" x-model="grade_level" @change="strand = ''; section = '';" required class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                    <option value="">Select</option>
                                    <template x-for="g in gradesFor(course)" :key="g">
                                        <option :value="g" x-text="g" :selected="g === grade_level"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="col-span-1">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Section <span class="text-red-500">*</span></label>
                                <select name="section" x-model="section" required class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                    <option value="">Select...</option>
                                    <template x-for="s in sectionsFor(course, grade_level)" :key="s">
                                        <option :value="s" x-text="s" :selected="s === section"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="col-span-1">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Status <span class="text-red-500">*</span></label>
                                <select name="status" required class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                    <option value="active" {{ old('status') == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                    <option value="transferred" {{ old('status') == 'transferred' ? 'selected' : '' }}>Transferred</option>
                                    <option value="graduated" {{ old('status') == 'graduated' ? 'selected' : '' }}>Graduated</option>
                                </select>
                            </div>
                        </div>

                        <div x-show="isSeniorHigh()" x-cloak>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Strand <span class="text-red-500">*</span></label>
                            <select name="strand" x-model="strand" :required="isSeniorHigh()" class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('strand') border-red-500 @enderror">
                                <option value="">Select strand...</option>
                                @foreach(\App\Models\Course::STRANDS as $strandOption)
                                    <option value="{{ $strandOption }}">{{ $strandOption }}</option>
                                @endforeach
                            </select>
                        </div>

                        <h4 class="font-semibold text-gray-800 border-b border-gray-100 pb-2 mt-6 flex items-center gap-2 text-sm uppercase tracking-wider">
                            <i class="ti ti-users text-green-600"></i> Parent / Guardian
                        </h4>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Parent Name <span class="text-red-500">*</span></label>
                            <input type="text" name="parent_name" value="{{ old('parent_name') }}" required
                                class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Parent Contact <span class="text-red-500">*</span></label>
                            <input type="text" name="parent_contact" value="{{ old('parent_contact') }}" required placeholder="e.g. 09123456789"
                                class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                        </div>

                        <input type="hidden" name="school_year" value="2025-2026">
                    </div>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3 pt-5 border-t border-gray-100 bg-gray-50 -mx-8 -mb-8 px-8 py-4 rounded-b-2xl">
                    <button type="button" @click="showAddModal = false" class="px-5 py-2.5 text-sm font-semibold text-gray-600 hover:text-gray-900 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm shadow-blue-200 flex items-center gap-2">
                        <i class="ti ti-device-floppy"></i> Save Student
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Import Students Modal -->
<div x-data="{ 
        step: 1, 
        importId: null,
        summary: { valid: 0, invalid: 0, duplicate: 0, total: 0 },
        previewErrors: [],
        duplicateStrategy: 'skip',
        isUploading: false,
        progress: 0,
        codesGenerated: 0,

        reset() {
            this.step = 1;
            this.importId = null;
            this.summary = { valid: 0, invalid: 0, duplicate: 0, total: 0 };
            this.previewErrors = [];
            this.duplicateStrategy = 'skip';
            this.progress = 0;
            this.codesGenerated = 0;
            if (this.$refs.csvInput) this.$refs.csvInput.value = '';
        },

        uploadFile(e) {
            if(!this.$refs.csvInput.files[0]) return;
            this.isUploading = true;
            let formData = new FormData();
            formData.append('csv_file', this.$refs.csvInput.files[0]);
            formData.append('_token', '{{ csrf_token() }}');
            
            fetch('{{ route('admin.students.import.preview') }}', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            }).then(async res => {
                let data = await res.json();
                this.isUploading = false;
                if(!res.ok || data.error) {
                    alert(data.error || data.message || 'Upload failed.');
                    return;
                }
                this.importId = data.import_id;
                this.summary = data.summary;
                this.previewErrors = data.invalid_preview;
                this.step = 2;
            }).catch(err => {
                this.isUploading = false;
                alert('Upload failed due to network error.');
            });
        },

        commitImport() {
            if(this.summary.valid === 0 && (this.duplicateStrategy === 'skip' || this.summary.duplicate === 0)) {
                alert('No valid rows to import.');
                return;
            }
            this.step = 3;
            this.progress = 0;
            this.processChunk(1);
        },

        processChunk(page) {
            let formData = new FormData();
            formData.append('import_id', this.importId);
            formData.append('duplicate_strategy', this.duplicateStrategy);
            formData.append('page', page);
            formData.append('_token', '{{ csrf_token() }}');

            fetch('{{ route('admin.students.import.commit') }}', {
                method: 'POST',
                body: formData,
                headers: { 'Accept': 'application/json' }
            }).then(async res => {
                let data = await res.json();
                if(!res.ok || data.error) {
                    alert(data.error || 'Import failed.');
                    return;
                }
                this.progress = data.progress;
                this.codesGenerated = data.codes_generated ?? this.codesGenerated;
                if(data.current_page < data.total_pages) {
                    this.processChunk(data.current_page + 1);
                } else if (this.codesGenerated === 0) {
                    // Nothing to download — safe to close this out automatically.
                    setTimeout(() => window.location.reload(), 1000);
                }
                // else: stay on this screen so the admin can download the
                // one-time activation codes before the modal closes.
            }).catch(err => {
                alert('Import interrupted due to network error.');
            });
        }
    }" 
    @open-import-modal.window="showImportModal = true; reset()"
    x-show="showImportModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div x-show="showImportModal" x-transition.opacity class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" aria-hidden="true" @click="if(step !== 3) showImportModal = false"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div x-show="showImportModal" x-transition.scale.origin.bottom class="inline-block w-full max-w-2xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl sm:p-8">
            
            <div class="flex items-center justify-between mb-5 border-b border-gray-100 pb-4">
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Import Students</h3>
                    <p class="text-sm text-gray-500 mt-1" x-text="step === 1 ? 'Step 1: Upload CSV File' : (step === 2 ? 'Step 2: Preview & Confirm' : 'Step 3: Importing...')"></p>
                </div>
                <button type="button" x-show="step !== 3" @click="showImportModal = false" class="text-gray-400 hover:text-gray-600 transition bg-gray-50 hover:bg-gray-100 rounded-full p-2">
                    <i class="ti ti-x text-xl"></i>
                </button>
            </div>

            <!-- STEP 1: UPLOAD -->
            <div x-show="step === 1" class="space-y-4 max-h-[70vh] overflow-y-auto pr-1 custom-scrollbar" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform translate-x-4" x-transition:enter-end="opacity-100 transform translate-x-0">
                <div class="flex items-start gap-3 bg-blue-50/50 border border-blue-100 rounded-xl p-4">
                    <div class="w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-xs shrink-0">1</div>
                    <div class="flex-1">
                        <h4 class="font-medium text-gray-900 text-sm mb-1">Download the CSV template</h4>
                        <p class="text-xs text-gray-600 mb-2.5">Fill it in Excel or Google Sheets. Each row becomes one student, with a Student Portal account created automatically.</p>
                        <a href="{{ route('admin.students.import.template') }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-white border border-gray-300 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-50 transition shadow-sm">
                            <i class="ti ti-download"></i> Download Template
                        </a>
                    </div>
                </div>

                <div class="flex items-start gap-3 bg-emerald-50/50 border border-emerald-100 rounded-xl p-4">
                    <div class="w-7 h-7 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-xs shrink-0">2</div>
                    <div class="flex-1">
                        <h4 class="font-medium text-gray-900 text-sm mb-1">Upload the completed CSV</h4>
                        <p class="text-xs text-gray-600 mb-2.5">We will validate the file first. Nothing will be imported until you confirm.</p>

                        <form @submit.prevent="uploadFile" class="flex flex-col sm:flex-row sm:items-center gap-2.5">
                            <input type="file" x-ref="csvInput" accept=".csv,text/csv" required
                                class="block w-full text-xs text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                            <button type="submit" :disabled="isUploading" class="px-4 py-2 bg-emerald-600 text-white text-xs font-medium rounded-lg hover:bg-emerald-700 transition shadow-sm flex items-center justify-center gap-1.5 whitespace-nowrap shrink-0 disabled:opacity-50">
                                <i class="ti ti-upload" x-show="!isUploading"></i>
                                <i class="ti ti-loader animate-spin" x-show="isUploading" x-cloak></i>
                                <span x-text="isUploading ? 'Validating...' : 'Next Step'"></span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- STEP 2: PREVIEW -->
            <div x-show="step === 2" x-cloak class="space-y-4 max-h-[70vh] overflow-y-auto pr-1 custom-scrollbar" x-transition:enter="transition ease-out duration-300 delay-150" x-transition:enter-start="opacity-0 transform translate-x-4" x-transition:enter-end="opacity-100 transform translate-x-0">
                
                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div class="bg-emerald-50 rounded-xl border border-emerald-100 p-4 text-center">
                        <div class="text-2xl font-bold text-emerald-600 mb-1" x-text="summary.valid"></div>
                        <div class="text-xs font-medium text-emerald-800 uppercase tracking-wide">Valid Rows</div>
                    </div>
                    <div class="bg-blue-50 rounded-xl border border-blue-100 p-4 text-center">
                        <div class="text-2xl font-bold text-blue-600 mb-1" x-text="summary.duplicate"></div>
                        <div class="text-xs font-medium text-blue-800 uppercase tracking-wide">Duplicates</div>
                    </div>
                    <div class="bg-red-50 rounded-xl border border-red-100 p-4 text-center">
                        <div class="text-2xl font-bold text-red-600 mb-1" x-text="summary.invalid"></div>
                        <div class="text-xs font-medium text-red-800 uppercase tracking-wide">Invalid Rows</div>
                    </div>
                </div>

                <div x-show="summary.duplicate > 0" class="bg-blue-50/50 border border-blue-100 rounded-xl p-4">
                    <h4 class="font-medium text-blue-900 text-sm mb-2 flex items-center gap-2">
                        <i class="ti ti-copy text-blue-600 text-lg"></i> Duplicate Handling
                    </h4>
                    <p class="text-xs text-blue-800 mb-3">We found <span class="font-bold" x-text="summary.duplicate"></span> rows with Student IDs that already exist. How do you want to handle them?</p>
                    <div class="space-y-2">
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="radio" x-model="duplicateStrategy" value="skip" class="text-blue-600 focus:ring-blue-500 w-4 h-4">
                            <span><strong class="text-gray-900">Skip Existing</strong> (Ignore duplicates, keep old data)</span>
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="radio" x-model="duplicateStrategy" value="update" class="text-blue-600 focus:ring-blue-500 w-4 h-4">
                            <span><strong class="text-gray-900">Update Existing</strong> (Overwrite their course/section/details)</span>
                        </label>
                    </div>
                </div>

                <div x-show="summary.invalid > 0" class="bg-red-50/50 border border-red-100 rounded-xl p-4">
                    <div class="flex items-start justify-between mb-2">
                        <h4 class="font-medium text-red-900 text-sm flex items-center gap-2">
                            <i class="ti ti-alert-triangle text-red-600 text-lg"></i> Invalid Rows Detected
                        </h4>
                        <a :href="`/admin/students/import/errors/${importId}`" class="inline-flex items-center gap-1.5 px-3 py-1 bg-white border border-red-200 text-red-700 text-xs font-medium rounded-lg hover:bg-red-50 transition shadow-sm">
                            <i class="ti ti-download"></i> Download Error Report
                        </a>
                    </div>
                    <p class="text-xs text-red-800 mb-3"><span class="font-bold" x-text="summary.invalid"></span> rows will be skipped due to errors. Download the report to see all errors and fix them.</p>
                    
                    <div class="space-y-1.5 max-h-32 overflow-y-auto pr-1 text-xs">
                        <template x-for="error in previewErrors" :key="error._row">
                            <div class="text-red-800 bg-white border border-red-100 rounded-lg px-3 py-2 flex items-start gap-2">
                                <span class="font-bold shrink-0 mt-0.5">Row <span x-text="error._row"></span>:</span>
                                <ul class="list-disc list-inside">
                                    <template x-for="msg in error._errors" :key="msg">
                                        <li x-text="msg"></li>
                                    </template>
                                </ul>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="mt-5 pt-4 border-t border-gray-100 flex items-center justify-between">
                    <button type="button" @click="step = 1" class="px-5 py-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition">
                        Back
                    </button>
                    <button type="button" @click="commitImport" class="px-6 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm shadow-blue-200 flex items-center gap-2">
                        <i class="ti ti-check"></i> Confirm Import
                    </button>
                </div>
            </div>

            <!-- STEP 3: PROGRESS -->
            <div x-show="step === 3" x-cloak class="py-8 text-center" x-transition:enter="transition ease-out duration-300 delay-150" x-transition:enter-start="opacity-0 transform translate-y-4" x-transition:enter-end="opacity-100 transform translate-y-0">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-50 text-blue-600 mb-4">
                    <i class="ti text-3xl" :class="progress === 100 ? 'ti-circle-check text-emerald-500' : 'ti-loader animate-spin'"></i>
                </div>
                <h3 class="text-lg font-bold text-gray-900 mb-2" x-text="progress === 100 ? 'Import Complete!' : 'Importing Students...'"></h3>
                <p class="text-sm text-gray-500 mb-6" x-text="progress < 100 ? 'Please do not close this window.' : (codesGenerated > 0 ? 'Download the activation codes below.' : 'Refreshing page...')"></p>

                <div class="w-full bg-gray-100 rounded-full h-3 mb-2 overflow-hidden border border-gray-200">
                    <div class="bg-blue-600 h-3 rounded-full transition-all duration-300 ease-out relative overflow-hidden" :style="'width: ' + progress + '%'">
                        <div class="absolute inset-0 bg-white/20 w-full h-full" style="background-image: linear-gradient(45deg, rgba(255,255,255,.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,.15) 50%, rgba(255,255,255,.15) 75%, transparent 75%, transparent); background-size: 1rem 1rem; animation: progress-stripes 1s linear infinite;"></div>
                    </div>
                </div>
                <div class="text-xs font-semibold text-gray-600" x-text="progress + '%'"></div>

                <div x-show="progress === 100 && codesGenerated > 0" class="mt-6 pt-5 border-t border-gray-100 text-left bg-indigo-50/50 border border-indigo-100 rounded-xl p-4">
                    <h4 class="font-medium text-indigo-900 text-sm flex items-center gap-2">
                        <i class="ti ti-key text-indigo-600 text-lg"></i> Activation Codes Generated
                    </h4>
                    <p class="text-xs text-indigo-800 mt-1">
                        <span class="font-bold" x-text="codesGenerated"></span> new student account(s) need their one-time activation code to sign in. Download it now — it won't be shown again.
                    </p>
                    <div class="mt-3 flex items-center gap-2">
                        <a :href="`/admin/students/import/codes/${importId}`" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-white border border-indigo-200 text-indigo-700 text-xs font-medium rounded-lg hover:bg-indigo-50 transition shadow-sm">
                            <i class="ti ti-download"></i> Download Activation Codes
                        </a>
                        <button type="button" @click="window.location.reload()" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-900 text-white text-xs font-medium rounded-lg hover:bg-gray-800 transition">
                            Done
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<style>
@keyframes progress-stripes {
    0% { background-position: 1rem 0; }
    100% { background-position: 0 0; }
}
</style>

<!-- Edit Student Modals -->
@foreach($students as $student)
<div x-data='{
        showEditModal: {{ $errors->any() && old('edit_student_id') == $student->id ? 'true' : 'false' }},
        education_level: "{{ old('edit_student_id') == $student->id ? old('education_level', $student->education_level) : $student->education_level }}",
        course: "{{ old('edit_student_id') == $student->id ? old('course', $student->course) : $student->course }}",
        grade_level: "{{ old('edit_student_id') == $student->id ? old('grade_level', $student->grade_level) : $student->grade_level }}",
        strand: "{{ old('edit_student_id') == $student->id ? old('strand', $student->strand) : $student->strand }}",
        section: "{{ old('edit_student_id') == $student->id ? old('section', $student->section) : $student->section }}",
        combos: @json($courseCombos),
        seniorHighGrades: {{ json_encode($seniorHighGrades) }},
        isBasicEd() { return this.education_level === "Basic Education"; },
        isSeniorHigh() { return this.isBasicEd() && this.seniorHighGrades.includes(this.grade_level); },
        coursesFor() { return [...new Set(this.combos.filter(c => c.education_level === this.education_level).map(c => c.course))]; },
        gradesFor(course) { return [...new Set(this.combos.filter(c => c.course === course && c.education_level === this.education_level).map(c => c.grade_level))]; },
        sectionsFor(course, grade) { return [...new Set(this.combos.filter(c => c.course === course && c.education_level === this.education_level && (!grade || c.grade_level === grade)).map(c => c.section))]; }
    }' @open-edit-modal-{{ $student->id }}.window="showEditModal = true">
    <div x-show="showEditModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showEditModal" x-transition.opacity class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" aria-hidden="true" @click="showEditModal = false"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="showEditModal" x-transition.scale.origin.bottom class="inline-block w-full max-w-4xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl sm:p-8">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">Edit Student</h3>
                        <p class="text-sm text-gray-500 mt-1">Update the student's profile information.</p>
                    </div>
                    <button type="button" @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 transition bg-gray-50 hover:bg-gray-100 rounded-full p-2">
                        <i class="ti ti-x text-xl"></i>
                    </button>
                </div>

                <form action="{{ route('admin.students.update', $student->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="edit_student_id" value="{{ $student->id }}">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                        
                        <!-- Left Column: Personal Details -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-800 border-b border-gray-100 pb-2 flex items-center gap-2 text-sm uppercase tracking-wider">
                                <i class="ti ti-user-edit text-blue-600"></i> Personal Details
                            </h4>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Student ID Number <span class="text-red-500">*</span></label>
                                <input type="text" name="student_id_number" value="{{ old('student_id_number', $student->student_id_number) }}" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_student_id') == $student->id) @error('student_id_number') border-red-500 @enderror @endif">
                                @if(old('edit_student_id') == $student->id) @error('student_id_number') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror @endif
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">First Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="first_name" value="{{ old('first_name', $student->first_name) }}" required
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_student_id') == $student->id) @error('first_name') border-red-500 @enderror @endif">
                                    @if(old('edit_student_id') == $student->id) @error('first_name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror @endif
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Last Name <span class="text-red-500">*</span></label>
                                    <input type="text" name="last_name" value="{{ old('last_name', $student->last_name) }}" required
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_student_id') == $student->id) @error('last_name') border-red-500 @enderror @endif">
                                    @if(old('edit_student_id') == $student->id) @error('last_name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror @endif
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Middle Name</label>
                                    <input type="text" name="middle_name" value="{{ old('middle_name', $student->middle_name) }}"
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_student_id') == $student->id) @error('middle_name') border-red-500 @enderror @endif">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Gender <span class="text-red-500">*</span></label>
                                    <select name="gender" required
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_student_id') == $student->id) @error('gender') border-red-500 @enderror @endif">
                                        <option value="">Select...</option>
                                        <option value="Male" {{ old('gender', $student->gender) === 'Male' ? 'selected' : '' }}>Male</option>
                                        <option value="Female" {{ old('gender', $student->gender) === 'Female' ? 'selected' : '' }}>Female</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Birthdate <span class="text-red-500">*</span></label>
                                    <input type="date" name="birthdate" value="{{ old('birthdate', $student->birthdate ? $student->birthdate->format('Y-m-d') : '') }}" required
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_student_id') == $student->id) @error('birthdate') border-red-500 @enderror @endif">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Student Contact</label>
                                    <input type="text" name="student_contact" value="{{ old('student_contact', $student->student_contact) }}"
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                </div>
                            </div>
                            <div class="mt-4">
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Home Address <span class="text-red-500">*</span></label>
                                <input type="text" name="address" value="{{ old('address', $student->address) }}" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_student_id') == $student->id) @error('address') border-red-500 @enderror @endif">
                            </div>
                        </div>

                        <!-- Right Column: Academic & Parent Details -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-800 border-b border-gray-100 pb-2 flex items-center gap-2 text-sm uppercase tracking-wider">
                                <i class="ti ti-school text-amber-600"></i> Academic Details
                            </h4>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Education Level <span class="text-red-500">*</span></label>
                                <select name="education_level" x-model="education_level" @change="course = ''; grade_level = ''; strand = ''; section = '';" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_student_id') == $student->id) @error('education_level') border-red-500 @enderror @endif">
                                    <option value="College" {{ old('education_level', $student->education_level) === 'College' ? 'selected' : '' }}>College</option>
                                    <option value="Basic Education" {{ old('education_level', $student->education_level) === 'Basic Education' ? 'selected' : '' }}>Basic Education</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Course / Program <span class="text-red-500">*</span></label>
                                <select name="course" x-model="course" @change="grade_level = ''; strand = ''; section = '';" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_student_id') == $student->id) @error('course') border-red-500 @enderror @endif">
                                    <option value="">Select course...</option>
                                    <template x-for="c in coursesFor()" :key="c">
                                        <option :value="c" x-text="c" :selected="c === course"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="grid grid-cols-3 gap-4">
                                <div class="col-span-1">
                                    <label class="block text-xs font-semibold text-gray-600 mb-1" x-text="isBasicEd() ? 'Grade *' : 'Year *'"></label>
                                    <select name="grade_level" x-model="grade_level" @change="strand = ''; section = '';" required class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        <option value="">Select</option>
                                        <template x-for="g in gradesFor(course)" :key="g">
                                            <option :value="g" x-text="g" :selected="g === grade_level"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Section <span class="text-red-500">*</span></label>
                                    <select name="section" x-model="section" required class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        <option value="">Select...</option>
                                        <template x-for="s in sectionsFor(course, grade_level)" :key="s">
                                            <option :value="s" x-text="s" :selected="s === section"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="col-span-1">
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Status <span class="text-red-500">*</span></label>
                                    <select name="status" required class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        <option value="active" {{ old('status', $student->status) == 'active' ? 'selected' : '' }}>Active</option>
                                        <option value="inactive" {{ old('status', $student->status) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                        <option value="transferred" {{ old('status', $student->status) == 'transferred' ? 'selected' : '' }}>Transferred</option>
                                        <option value="graduated" {{ old('status', $student->status) == 'graduated' ? 'selected' : '' }}>Graduated</option>
                                    </select>
                                </div>
                            </div>

                            <div x-show="isSeniorHigh()" x-cloak>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Strand <span class="text-red-500">*</span></label>
                                <select name="strand" x-model="strand" :required="isSeniorHigh()" class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                    <option value="">Select strand...</option>
                                    @foreach(\App\Models\Course::STRANDS as $strandOption)
                                        <option value="{{ $strandOption }}">{{ $strandOption }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <h4 class="font-semibold text-gray-800 border-b border-gray-100 pb-2 mt-6 flex items-center gap-2 text-sm uppercase tracking-wider">
                                <i class="ti ti-users text-green-600"></i> Parent / Guardian
                            </h4>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Parent Name <span class="text-red-500">*</span></label>
                                <input type="text" name="parent_name" value="{{ old('parent_name', $student->parent_name) }}" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Parent Contact <span class="text-red-500">*</span></label>
                                <input type="text" name="parent_contact" value="{{ old('parent_contact', $student->parent_contact) }}" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                            </div>

                            <input type="hidden" name="school_year" value="{{ $student->school_year }}">
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3 pt-5 border-t border-gray-100 bg-gray-50 -mx-8 -mb-8 px-8 py-4 rounded-b-2xl">
                        <button type="button" @click="showEditModal = false" class="px-5 py-2.5 text-sm font-semibold text-gray-600 hover:text-gray-900 transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm shadow-blue-200 flex items-center gap-2">
                            <i class="ti ti-device-floppy"></i> Update Student
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endforeach

</div>

@endsection

@push('scripts')
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<style>
    [x-cloak] { display: none !important; }
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: transparent;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background-color: rgba(0, 0, 0, 0.1);
        border-radius: 20px;
    }
</style>
<script>
    let searchTimeout = null;
    const searchInput = document.getElementById('searchInput');
    const filterForm = document.getElementById('filterForm');

    if (searchInput && filterForm) {
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const val = e.target.value.trim();
            
            // Auto submit if cleared or if length >= 2
            if (val.length === 0 || val.length >= 2) {
                searchTimeout = setTimeout(() => {
                    filterForm.submit();
                }, 500); // Wait 500ms after user stops typing
            }
        });
        
        // Put cursor at the end of text when page reloads with search value
        if (searchInput.value) {
            const length = searchInput.value.length;
            searchInput.focus();
            searchInput.setSelectionRange(length, length);
        }
    }
</script>
@endpush
