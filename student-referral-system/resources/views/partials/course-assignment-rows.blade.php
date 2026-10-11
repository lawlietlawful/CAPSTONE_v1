{{--
    The repeating Course / Year Level / Section rows of a teacher's course
    assignments. Shared by the Teacher Profile's "Course Assignments" modal
    and User Management's Add/Edit User modals, which each used to carry
    their own copy — and drifted (long course names clipped in two of the
    three, no duplicate button).

    Expects the surrounding Alpine x-data to provide: assignments,
    coursesFor(), gradesFor(course), sectionsFor(course, grade),
    duplicateAssignment(i), removeAssignment(i).
--}}
<div x-show="assignments.length > 0" class="grid grid-cols-12 gap-2 px-0.5 mb-1.5">
    <span class="col-span-5 text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Course</span>
    <span class="col-span-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Year Level</span>
    <span class="col-span-3 text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Section</span>
</div>

<template x-for="(a, index) in assignments" :key="index">
    <div class="grid grid-cols-12 gap-2 mb-2 items-center" data-assignment-row>
        <div class="col-span-5">
            <select :name="'assignments[' + index + '][course]'"
                :title="a.course"
                @change="a.course = $event.target.value; a.grade_level = ''; a.section = '';"
                class="w-full truncate rounded-lg border border-gray-300 bg-white pl-3 pr-8 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition">
                <option value="">Select course...</option>
                <template x-for="c in coursesFor()" :key="c">
                    <option :value="c" x-text="c" :selected="c === a.course"></option>
                </template>
            </select>
        </div>
        <div class="col-span-3">
            <select :name="'assignments[' + index + '][grade_level]'"
                @change="a.grade_level = $event.target.value; a.section = '';"
                class="w-full truncate rounded-lg border border-gray-300 bg-white pl-3 pr-8 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition">
                <option value="">All year levels</option>
                <template x-for="g in gradesFor(a.course)" :key="g">
                    <option :value="g" x-text="g" :selected="g === a.grade_level"></option>
                </template>
            </select>
        </div>
        <div class="col-span-3">
            <select :name="'assignments[' + index + '][section]'"
                @change="a.section = $event.target.value"
                class="w-full truncate rounded-lg border border-gray-300 bg-white pl-3 pr-8 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-200 transition">
                <option value="">All sections</option>
                <template x-for="s in sectionsFor(a.course, a.grade_level)" :key="s">
                    <option :value="s" x-text="s" :selected="s === a.section"></option>
                </template>
            </select>
        </div>
        <div class="col-span-1 flex items-center justify-center gap-2">
            <button type="button" @click="duplicateAssignment(index)" class="text-gray-400 hover:text-blue-600" title="Duplicate this row">
                <i class="ti ti-copy"></i>
            </button>
            <button type="button" @click="removeAssignment(index)" class="text-red-400 hover:text-red-600" title="Remove this row">
                <i class="ti ti-trash"></i>
            </button>
        </div>
    </div>
</template>
