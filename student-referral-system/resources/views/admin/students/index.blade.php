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
    <a href="{{ route('admin.referrals.index') }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-blue-200 transition block cursor-pointer">
        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
            <i class="ti ti-file-text text-blue-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-blue-700 leading-none">{{ number_format($studentsWithReferrals) }}</div>
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">With Referrals</div>
        </div>
    </a>
    <a href="{{ route('admin.students.index') }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-red-200 transition block cursor-pointer">
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
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-4 flex flex-col lg:flex-row gap-3 items-end">
            <div class="flex-1 w-full relative">
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
                                    <a href="{{ route('admin.students.show', $student->id) }}" class="font-medium text-gray-900 hover:text-blue-600 hover:underline transition">
                                        {{ $student->first_name }} {{ $student->last_name }}
                                    </a>
                                    <p class="text-xs text-gray-500">{{ $student->gender }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex flex-col gap-1 items-start">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-50 text-indigo-700 border border-indigo-100">
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
                                    <button type="button" @click="$dispatch('open-confirm-modal', { 
                                            formId: 'delete-student-{{ $student->id }}', 
                                            title: 'Delete Student', 
                                            message: 'Are you sure you want to delete {{ addslashes($student->first_name) }}? This action cannot be undone.',
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
<div x-show="showImportModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div x-show="showImportModal" x-transition.opacity class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" aria-hidden="true" @click="showImportModal = false"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div x-show="showImportModal" x-transition.scale.origin.bottom class="inline-block w-full max-w-2xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl sm:p-8">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Import Students</h3>
                    <p class="text-sm text-gray-500 mt-1">Bulk-register students from a CSV file.</p>
                </div>
                <button type="button" @click="showImportModal = false" class="text-gray-400 hover:text-gray-600 transition bg-gray-50 hover:bg-gray-100 rounded-full p-2">
                    <i class="ti ti-x text-xl"></i>
                </button>
            </div>

            <div class="space-y-4 max-h-[70vh] overflow-y-auto pr-1 custom-scrollbar">

                <!-- Step 1: Template -->
                <div class="flex items-start gap-3 bg-blue-50/50 border border-blue-100 rounded-xl p-4">
                    <div class="w-7 h-7 rounded-full bg-blue-600 text-white flex items-center justify-center font-bold text-xs shrink-0">1</div>
                    <div class="flex-1">
                        <h4 class="font-medium text-gray-900 text-sm mb-1">Download the CSV template</h4>
                        <p class="text-xs text-gray-600 mb-2.5">
                            Fill it in Excel or Google Sheets. Each row becomes one student, with a Student Portal account created automatically.
                        </p>
                        <a href="{{ route('admin.students.import.template') }}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-white border border-gray-300 text-gray-700 text-xs font-medium rounded-lg hover:bg-gray-50 transition shadow-sm">
                            <i class="ti ti-download"></i> Download Template
                        </a>
                    </div>
                </div>

                <!-- Step 2: Course/Section reference -->
                <div class="flex items-start gap-3 bg-amber-50/50 border border-amber-100 rounded-xl p-4">
                    <div class="w-7 h-7 rounded-full bg-amber-500 text-white flex items-center justify-center font-bold text-xs shrink-0">2</div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-medium text-gray-900 text-sm mb-1">Use exact Course / Year / Section values</h4>
                        <p class="text-xs text-gray-600 mb-2.5">
                            Each row must match a combination that already exists in your
                            <a href="{{ route('admin.courses.index') }}" class="text-blue-600 hover:underline font-medium">Courses &amp; Sections</a> catalog, or it will be skipped and reported.
                        </p>
                        @if($courseCombos->isEmpty())
                            <p class="text-xs text-amber-700 flex items-center gap-1.5">
                                <i class="ti ti-alert-triangle"></i> No courses/sections defined yet — add at least one before importing.
                            </p>
                        @else
                            <div class="overflow-auto max-h-40 rounded-lg border border-amber-200 bg-white">
                                <table class="w-full text-left text-xs">
                                    <thead class="bg-amber-100/50 text-amber-800 uppercase tracking-wider sticky top-0">
                                        <tr>
                                            <th class="px-3 py-1.5">Course</th>
                                            <th class="px-3 py-1.5">Year</th>
                                            <th class="px-3 py-1.5">Section</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-amber-100">
                                        @foreach($courseCombos as $combo)
                                            <tr>
                                                <td class="px-3 py-1.5 text-gray-700">{{ $combo['course'] }}</td>
                                                <td class="px-3 py-1.5 text-gray-700">{{ $combo['grade_level'] }}</td>
                                                <td class="px-3 py-1.5 text-gray-700">{{ $combo['section'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Step 3: Upload -->
                <div class="flex items-start gap-3 bg-emerald-50/50 border border-emerald-100 rounded-xl p-4">
                    <div class="w-7 h-7 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-xs shrink-0">3</div>
                    <div class="flex-1">
                        <h4 class="font-medium text-gray-900 text-sm mb-1">Upload the completed CSV</h4>
                        <p class="text-xs text-gray-600 mb-2.5">Valid rows are imported immediately. Invalid rows are skipped and listed below with the reason.</p>

                        <form action="{{ route('admin.students.import') }}" method="POST" enctype="multipart/form-data" class="flex flex-col sm:flex-row sm:items-center gap-2.5">
                            @csrf
                            <input type="file" name="csv_file" accept=".csv,text/csv" required
                                class="block w-full text-xs text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 @error('csv_file') border border-red-500 rounded-lg @enderror">
                            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white text-xs font-medium rounded-lg hover:bg-emerald-700 transition shadow-sm flex items-center justify-center gap-1.5 whitespace-nowrap shrink-0">
                                <i class="ti ti-upload"></i> Import
                            </button>
                        </form>
                        @error('csv_file')
                            <p class="mt-2 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Results -->
                @if(session('import_results'))
                    @php $results = session('import_results'); @endphp
                    <div class="pt-3 border-t border-gray-100 space-y-2.5">
                        <h4 class="font-medium text-gray-900 text-sm">Import Results</h4>
                        <div class="flex items-center gap-2 text-xs font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-lg px-3.5 py-2.5">
                            <i class="ti ti-circle-check text-base"></i>
                            {{ $results['success_count'] }} student{{ $results['success_count'] === 1 ? '' : 's' }} imported successfully.
                        </div>

                        @if(!empty($results['errors']))
                            <div>
                                <p class="text-xs font-medium text-amber-700 mb-1.5 flex items-center gap-1.5">
                                    <i class="ti ti-alert-triangle"></i> {{ count($results['errors']) }} row{{ count($results['errors']) === 1 ? '' : 's' }} skipped:
                                </p>
                                <div class="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                                    @foreach($results['errors'] as $error)
                                        <div class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3.5 py-2">
                                            <span class="font-semibold">Row {{ $error['row'] }}:</span>
                                            {{ implode(' ', $error['messages']) }}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endif

            </div>

            <div class="mt-5 pt-4 border-t border-gray-100 flex items-center justify-end">
                <button type="button" @click="showImportModal = false" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

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
