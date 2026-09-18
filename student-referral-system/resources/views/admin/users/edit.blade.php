@extends('layouts.admin')

@section('title', 'Edit User')
@section('page-title', 'Edit User')
@section('page-sub', 'Update details for ' . $user->name)

@section('content')

<div class="bg-white border border-gray-100 rounded-2xl shadow-premium max-w-3xl">
    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
        <h3 class="font-semibold text-gray-800 text-lg">User Details</h3>
        <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <i class="ti ti-arrow-left"></i> Back to Users
        </a>
    </div>

    <div class="p-6">
        <form action="{{ route('admin.users.update', $user->id) }}" method="POST"
              x-data='{
                  role: "{{ old('role', $user->role) }}",
                  assignments: {{ old('assignments') ? json_encode(old('assignments')) : $existingAssignments->map(fn($a) => ['course' => $a->course, 'grade_level' => $a->grade_level, 'section' => $a->section])->values()->toJson() }},
                  combos: @json($courseCombos),
                  addAssignment() { this.assignments.push({ course: "", grade_level: "", section: "" }); },
                  removeAssignment(i) { this.assignments.splice(i, 1); },
                  coursesFor() { return [...new Set(this.combos.map(c => c.course))]; },
                  gradesFor(course) { return [...new Set(this.combos.filter(c => c.course === course).map(c => c.grade_level))]; },
                  sectionsFor(course, grade) { return [...new Set(this.combos.filter(c => c.course === course && (!grade || c.grade_level === grade)).map(c => c.section))]; }
              }'>
            @csrf
            @method('PUT')
            
            <div class="space-y-6">
                <!-- Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $user->name) }}" required
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('name') border-red-500 @enderror">
                    @error('name')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Username (Optional for non-students) -->
                <div>
                    <label for="username" class="block text-sm font-medium text-gray-700 mb-1">Username (Optional)</label>
                    <input type="text" name="username" id="username" value="{{ old('username', $user->username) }}"
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('username') border-red-500 @enderror">
                    @error('username')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <input type="email" name="email" id="email" value="{{ old('email', $user->email) }}" 
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('email') border-red-500 @enderror">
                    @error('email')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Role -->
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">System Role <span class="text-red-500">*</span></label>
                    <select name="role" id="role" x-model="role" required {{ $user->role === 'student' ? 'disabled' : '' }}
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('role') border-red-500 @enderror">
                        <option value="">Select a role...</option>
                        @if(auth()->user()->role === 'super_admin')
                            <option value="super_admin" {{ old('role', $user->role) === 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                            <option value="admin" {{ old('role', $user->role) === 'admin' ? 'selected' : '' }}>Admin (Counselor)</option>
                        @endif
                        <option value="teacher" {{ old('role', $user->role) === 'teacher' ? 'selected' : '' }}>Teacher</option>
                        @if($user->role === 'student')
                            <option value="student" selected>Student (Managed via Student Module)</option>
                        @endif
                    </select>
                    @if($user->role === 'student')
                        <input type="hidden" name="role" value="student">
                        <p class="mt-1 text-xs text-amber-600">Student roles cannot be changed here.</p>
                    @endif
                    @error('role')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

            </div>

            <!-- Course Assignments (Only show if role is teacher) -->
            <div x-show="role === 'teacher'" x-cloak class="mt-6 pt-6 border-t border-gray-100">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="font-medium text-gray-800">Course Assignments</h4>
                    <button type="button" @click="addAssignment()" class="text-xs font-medium text-blue-600 hover:text-blue-700 flex items-center gap-1">
                        <i class="ti ti-plus"></i> Add Assignment
                    </button>
                </div>
                <p class="text-xs text-gray-500 mb-3">
                    Assign this teacher as adviser of a course/program. Leave Year Level or Section blank to cover the whole course or whole year level. A teacher with no assignments will not see any students.
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

                <button type="button" x-show="assignments.length === 0" @click="addAssignment()" x-cloak
                    class="text-sm text-blue-600 hover:text-blue-700 flex items-center gap-1.5 mt-1">
                    <i class="ti ti-plus"></i> Add a course assignment
                </button>
            </div>

            <div class="mt-6 space-y-6">
                <!-- Password -->
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">New Password (Leave blank to keep current)</label>
                    <input type="password" name="password" id="password"
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('password') border-red-500 @enderror">
                    <p class="mt-1 text-xs text-gray-500">Minimum 8 characters. Leave blank if you don't want to change it.</p>
                    @error('password')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-8 flex items-center justify-end gap-3 pt-5 border-t border-gray-100">
                <a href="{{ route('admin.users.index') }}" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                    <i class="ti ti-device-floppy"></i> Update User
                </button>
            </div>
        </form>
    </div>
</div>

@endsection
