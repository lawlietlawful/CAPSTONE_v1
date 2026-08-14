@extends('layouts.admin')

@section('title', 'Courses & Sections')
@section('page-title', 'Courses & Sections')
@section('page-sub', 'Manage the course/program and section catalog used by student records and teacher assignments.')

@section('content')

{{-- ── Stat Cards ──────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-5">

    <div class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
            <i class="ti ti-books text-blue-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-blue-700 leading-none">{{ number_format($totalCourses) }}</div>
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">Total Courses</div>
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
            <i class="ti ti-layout-grid text-emerald-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-emerald-700 leading-none">{{ number_format($totalSections) }}</div>
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">Total Sections</div>
        </div>
    </div>

</div>

{{-- ── Add Course ─────────────────────────────────────────── --}}
<div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-4 mb-5">
    <form method="POST" action="{{ route('admin.courses.store') }}" class="flex flex-wrap gap-3 items-end">
        @csrf
        <div class="w-full sm:w-56">
            <label class="block text-xs font-medium text-gray-500 mb-1">Education Level</label>
            <select name="education_level" required class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3 @error('education_level') border-red-500 @enderror">
                <option value="College" {{ old('education_level') === 'College' ? 'selected' : '' }}>College</option>
                <option value="Basic Education" {{ old('education_level') === 'Basic Education' ? 'selected' : '' }}>Basic Education</option>
            </select>
            @error('education_level')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>
        <div class="flex-1 min-w-[220px]">
            <label class="block text-xs font-medium text-gray-500 mb-1">Course / Program Name</label>
            <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. BSIT, or Basic Education" required
                class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3 @error('name') border-red-500 @enderror">
            @error('name')
                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
            @enderror
        </div>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-1.5 h-[38px]">
            <i class="ti ti-plus"></i> Add Course
        </button>
    </form>
</div>

{{-- ── Courses List ───────────────────────────────────────── --}}
@forelse($courses as $course)
    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden mb-5"
         x-data="{
            addingSection: false,
            editingCourse: false,
            educationLevel: '{{ $course->education_level }}',
            isBasicEd() { return this.educationLevel === 'Basic Education'; },
            isSeniorHigh(grade) { return {{ json_encode(\App\Models\Course::SENIOR_HIGH_GRADES) }}.includes(grade); }
         }">
        <div class="px-6 py-5 border-b border-gray-100/60 bg-gray-50/50 flex items-center justify-between gap-3">
            <h2 class="text-[15px] font-semibold text-gray-800 flex items-center gap-2 flex-1 min-w-0" x-show="!editingCourse">
                <i class="ti ti-books text-gray-400 shrink-0"></i>
                <span class="truncate">{{ $course->name }}</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium whitespace-nowrap
                    {{ $course->education_level === 'Basic Education' ? 'bg-purple-50 text-purple-700 border border-purple-100' : 'bg-blue-50 text-blue-700 border border-blue-100' }}">
                    {{ $course->education_level }}
                </span>
                <span class="text-xs font-normal text-gray-400 whitespace-nowrap">
                    ({{ $course->sections->count() }} section{{ $course->sections->count() === 1 ? '' : 's' }}
                    @if($course->total_students > 0)
                        · {{ number_format($course->total_students) }} student{{ $course->total_students === 1 ? '' : 's' }}
                    @endif)
                </span>
            </h2>

            <form x-show="editingCourse" x-cloak action="{{ route('admin.courses.update', $course->id) }}" method="POST" class="flex-1 flex items-center gap-2">
                @csrf
                @method('PUT')
                <input type="text" name="name" value="{{ $course->name }}" required
                    class="block w-full max-w-xs border border-gray-300 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-1.5 px-3">
                <button type="submit" class="text-xs font-semibold text-blue-600 hover:text-blue-700 px-2 py-1.5">Save</button>
                <button type="button" @click="editingCourse = false" class="text-xs font-medium text-gray-500 hover:text-gray-700 px-2 py-1.5">Cancel</button>
            </form>

            <div class="flex items-center gap-2 shrink-0" x-show="!editingCourse">
                <button type="button" @click="editingCourse = true" class="text-xs font-medium text-gray-500 hover:text-gray-700 flex items-center gap-1">
                    <i class="ti ti-pencil"></i> Rename
                </button>
                <button type="button" @click="addingSection = !addingSection" class="text-xs font-medium text-blue-600 hover:text-blue-700 flex items-center gap-1">
                    <i class="ti ti-plus"></i> Add Section
                </button>
                <form id="delete-course-{{ $course->id }}" action="{{ route('admin.courses.destroy', $course->id) }}" method="POST" class="inline-block">
                    @csrf
                    @method('DELETE')
                    <button type="button" @click="$dispatch('open-confirm-modal', {
                            formId: 'delete-course-{{ $course->id }}',
                            title: 'Delete Course',
                            message: 'Are you sure you want to delete &quot;{{ addslashes($course->name) }}&quot;? This also removes all of its sections.',
                            confirmText: 'Yes, Delete Course'
                        })" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete Course">
                        <i class="ti ti-trash"></i>
                    </button>
                </form>
            </div>
        </div>

        {{-- Add Section (collapsible) --}}
        <div x-show="addingSection" x-cloak class="px-6 py-4 border-b border-gray-100 bg-blue-50/30"
             x-data="{ gradeLevel: '' }">
            <form method="POST" action="{{ route('admin.courses.sections.store', $course->id) }}" class="flex flex-wrap gap-3 items-end">
                @csrf
                <div class="w-full sm:w-44">
                    <label class="block text-xs font-medium text-gray-500 mb-1" x-text="isBasicEd() ? 'Grade Level' : 'Year Level'"></label>
                    <select name="grade_level" x-model="gradeLevel" required class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                        <option value="">Select...</option>
                        <template x-if="isBasicEd()">
                            <template x-for="gl in {{ json_encode($basicEdGradeLevels) }}" :key="gl">
                                <option :value="gl" x-text="gl"></option>
                            </template>
                        </template>
                        <template x-if="!isBasicEd()">
                            <template x-for="gl in {{ json_encode($collegeYearLevels) }}" :key="gl">
                                <option :value="gl" x-text="gl"></option>
                            </template>
                        </template>
                    </select>
                </div>
                <div class="w-full sm:w-40" x-show="isBasicEd() && isSeniorHigh(gradeLevel)" x-cloak>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Strand</label>
                    <select name="strand" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                        <option value="">Select...</option>
                        @foreach($strands as $strand)
                            <option value="{{ $strand }}">{{ $strand }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-full sm:w-44">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Section</label>
                    <input type="text" name="section" placeholder="e.g. Block 1" required
                        class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm h-[38px]">
                    Save Section
                </button>
            </form>
        </div>

        {{-- Sections --}}
        @if($course->sections->isEmpty())
            <div class="px-6 py-8 text-center">
                <p class="text-sm text-gray-500">No sections defined yet for this course.</p>
            </div>
        @else
            <div class="divide-y divide-gray-50">
                @foreach($course->sections->sortBy(['grade_level', 'section']) as $section)
                    <div class="px-6 py-3 flex items-center justify-between gap-3 hover:bg-gray-50/50 transition" x-data="{ editingSection: false, editGradeLevel: '{{ $section->grade_level }}' }">
                        <div class="flex items-center gap-3 flex-1 min-w-0" x-show="!editingSection">
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-gray-100 text-gray-700">
                                    {{ $section->grade_level }}
                                </span>
                                @if($section->strand)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-purple-50 text-purple-700 border border-purple-100">
                                        {{ $section->strand }}
                                    </span>
                                @endif
                                <span class="text-sm font-medium text-gray-800">{{ $section->section }}</span>
                            </div>
                            <div class="flex items-center gap-3 text-xs text-gray-400">
                                @if($section->student_count > 0)
                                    <span class="inline-flex items-center gap-1">
                                        <i class="ti ti-users"></i> {{ number_format($section->student_count) }} student{{ $section->student_count === 1 ? '' : 's' }}
                                    </span>
                                @endif
                                @if($section->teacher_count > 0)
                                    <span class="inline-flex items-center gap-1">
                                        <i class="ti ti-user-check"></i> {{ number_format($section->teacher_count) }} teacher{{ $section->teacher_count === 1 ? '' : 's' }}
                                    </span>
                                @endif
                                @if($section->student_count === 0 && $section->teacher_count === 0)
                                    <span>No students yet</span>
                                @endif
                            </div>
                        </div>

                        <form x-show="editingSection" x-cloak action="{{ route('admin.courses.sections.update', $section->id) }}" method="POST" class="flex-1 flex items-center gap-2">
                            @csrf
                            @method('PUT')
                            <select name="grade_level" x-model="editGradeLevel" required class="block border border-gray-300 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-1.5 px-2 w-36 shrink-0">
                                <template x-if="isBasicEd()">
                                    <template x-for="gl in {{ json_encode($basicEdGradeLevels) }}" :key="gl">
                                        <option :value="gl" x-text="gl" :selected="gl === editGradeLevel"></option>
                                    </template>
                                </template>
                                <template x-if="!isBasicEd()">
                                    <template x-for="gl in {{ json_encode($collegeYearLevels) }}" :key="gl">
                                        <option :value="gl" x-text="gl" :selected="gl === editGradeLevel"></option>
                                    </template>
                                </template>
                            </select>
                            <select name="strand" x-show="isBasicEd() && isSeniorHigh(editGradeLevel)" x-cloak class="block border border-gray-300 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-1.5 px-2 w-32 shrink-0">
                                <option value="">Strand...</option>
                                @foreach($strands as $strand)
                                    <option value="{{ $strand }}" {{ $section->strand === $strand ? 'selected' : '' }}>{{ $strand }}</option>
                                @endforeach
                            </select>
                            <input type="text" name="section" value="{{ $section->section }}" required
                                class="block w-full max-w-xs border border-gray-300 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-1.5 px-2">
                            <button type="submit" class="text-xs font-semibold text-blue-600 hover:text-blue-700 px-1.5 shrink-0">Save</button>
                            <button type="button" @click="editingSection = false" class="text-xs font-medium text-gray-500 hover:text-gray-700 px-1.5 shrink-0">Cancel</button>
                        </form>

                        <div class="flex items-center gap-1 shrink-0" x-show="!editingSection">
                            <button type="button" @click="editingSection = true" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Rename Section">
                                <i class="ti ti-pencil text-sm"></i>
                            </button>
                            <form id="delete-section-{{ $section->id }}" action="{{ route('admin.courses.sections.destroy', $section->id) }}" method="POST" class="inline-block">
                                @csrf
                                @method('DELETE')
                                <button type="button" @click="$dispatch('open-confirm-modal', {
                                        formId: 'delete-section-{{ $section->id }}',
                                        title: 'Delete Section',
                                        message: 'Are you sure you want to delete &quot;{{ addslashes($section->grade_level . ' - ' . $section->section) }}&quot;?',
                                        confirmText: 'Yes, Delete Section'
                                    })" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete Section">
                                    <i class="ti ti-trash text-sm"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@empty
    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
        <div class="flex flex-col items-center justify-center py-16">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-50 mb-3">
                <i class="ti ti-books text-xl text-gray-400"></i>
            </div>
            <p class="text-sm font-medium text-gray-900 mb-1">No courses yet</p>
            <p class="text-xs text-gray-500 mt-1">Add a course above to start building the catalog.</p>
        </div>
    </div>
@endforelse

@endsection
