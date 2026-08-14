@extends('layouts.admin')

@section('title', 'User Management')
@section('page-title', 'User Management')
@section('page-sub', 'Manage administrators, counselors, and teachers')

@section('content')

<div x-data="{ showAddUser: false }" class="space-y-0">

<div class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-premium">
    <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
        <h3 class="font-semibold text-gray-800 text-lg">System Users</h3>
        <button @click="showAddUser = true" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2 shadow-sm">
            <i class="ti ti-plus"></i> Add New User
        </button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm whitespace-nowrap">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-semibold tracking-wider">
                <tr>
                    <th class="px-6 py-4">Name</th>
                    <th class="px-6 py-4">Email</th>
                    <th class="px-6 py-4">Role</th>
                    <th class="px-6 py-4">Assigned To</th>
                    <th class="px-6 py-4">Joined</th>
                    <th class="px-6 py-4 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($users as $user)
                    <tr class="hover:bg-blue-50/50 transition" x-data="{ showEditModal: false }">
                        <td class="px-6 py-4 font-medium text-gray-800">{{ $user->name }}</td>
                        <td class="px-6 py-4 text-gray-500">{{ $user->email }}</td>
                        <td class="px-6 py-4">
                            @if($user->role === 'admin')
                                <span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs font-medium">Admin</span>
                            @elseif($user->role === 'guidance_counselor')
                                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-medium">Counselor</span>
                            @elseif($user->role === 'teacher')
                                <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-xs font-medium">Teacher</span>
                            @else
                                <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-xs font-medium">{{ ucfirst($user->role) }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($user->role === 'teacher')
                                @if($user->teacherAssignments->isEmpty())
                                    <span class="text-xs text-amber-600 flex items-center gap-1">
                                        <i class="ti ti-alert-triangle"></i> Unassigned
                                    </span>
                                @else
                                    <div class="flex flex-wrap gap-1 max-w-[220px]">
                                        @foreach($user->teacherAssignments as $a)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium bg-gray-100 text-gray-700">
                                                {{ $a->course }}{{ $a->grade_level ? ' · ' . $a->grade_level : '' }}{{ $a->section ? ' · ' . $a->section : '' }}
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-gray-500">{{ $user->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" @click="showEditModal = true" class="text-blue-600 hover:bg-blue-50 p-2 rounded-lg transition" title="Edit">
                                    <i class="ti ti-edit text-lg"></i>
                                </button>

                                {{-- ── Edit User Modal ─────────────────────────────────────── --}}
                                <div x-show="showEditModal" x-cloak
                                     class="fixed inset-0 z-50 flex items-center justify-center p-4 text-left"
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0"
                                     x-transition:enter-end="opacity-100"
                                     x-transition:leave="transition ease-in duration-150"
                                     x-transition:leave-start="opacity-100"
                                     x-transition:leave-end="opacity-0">

                                    {{-- Backdrop --}}
                                    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="showEditModal = false"></div>

                                    {{-- Modal Content --}}
                                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto border border-gray-100 custom-scrollbar"
                                         @click.away="showEditModal = false"
                                         x-transition:enter="transition ease-out duration-200"
                                         x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                         x-transition:leave="transition ease-in duration-150"
                                         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                         x-transition:leave-end="opacity-0 scale-95 translate-y-4">

                                        {{-- Header --}}
                                        <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between sticky top-0 z-10 rounded-t-2xl">
                                            <div>
                                                <h3 class="font-semibold text-gray-800 text-lg">Edit User</h3>
                                                <p class="text-xs text-gray-400 mt-0.5">Update details for {{ $user->name }}</p>
                                            </div>
                                            <button @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-2 rounded-lg transition">
                                                <i class="ti ti-x text-lg"></i>
                                            </button>
                                        </div>

                                        {{-- Form --}}
                                        <div class="p-6"
                                             x-data='{
                                                 role: "{{ old('role', $user->role) }}",
                                                 assignments: {{ old('assignments') ? json_encode(old('assignments')) : $user->teacherAssignments->map(fn($a) => ['course' => $a->course, 'grade_level' => $a->grade_level, 'section' => $a->section])->values()->toJson() }},
                                                 combos: @json($courseCombos),
                                                 addAssignment() { this.assignments.push({ course: "", grade_level: "", section: "" }); },
                                                 removeAssignment(i) { this.assignments.splice(i, 1); },
                                                 coursesFor() { return [...new Set(this.combos.map(c => c.course))]; },
                                                 gradesFor(course) { return [...new Set(this.combos.filter(c => c.course === course).map(c => c.grade_level))]; },
                                                 sectionsFor(course, grade) { return [...new Set(this.combos.filter(c => c.course === course && (!grade || c.grade_level === grade)).map(c => c.section))]; }
                                             }'>
                                            <form action="{{ route('admin.users.update', $user->id) }}" method="POST">
                                                @csrf
                                                @method('PUT')

                                                <div class="space-y-5">
                                                    <!-- Name -->
                                                    <div>
                                                        <label for="edit-name-{{ $user->id }}" class="block text-xs font-semibold text-gray-600 mb-1">Full Name <span class="text-red-500">*</span></label>
                                                        <input type="text" name="name" id="edit-name-{{ $user->id }}" value="{{ old('name', $user->name) }}" required
                                                            class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('name') border-red-500 @enderror">
                                                        @error('name')
                                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                                        @enderror
                                                    </div>

                                                    <!-- Email -->
                                                    <div>
                                                        <label for="edit-email-{{ $user->id }}" class="block text-xs font-semibold text-gray-600 mb-1">Email Address <span class="text-red-500">*</span></label>
                                                        <input type="email" name="email" id="edit-email-{{ $user->id }}" value="{{ old('email', $user->email) }}" required
                                                            class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('email') border-red-500 @enderror">
                                                        @error('email')
                                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                                        @enderror
                                                    </div>

                                                    <!-- Role -->
                                                    <div>
                                                        <label for="edit-role-{{ $user->id }}" class="block text-xs font-semibold text-gray-600 mb-1">System Role <span class="text-red-500">*</span></label>
                                                        <select name="role" id="edit-role-{{ $user->id }}" x-model="role" required {{ $user->role === 'student' ? 'disabled' : '' }}
                                                            class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('role') border-red-500 @enderror">
                                                            <option value="">Select a role...</option>
                                                            <option value="admin">Admin</option>
                                                            <option value="guidance_counselor">Guidance Counselor</option>
                                                            <option value="teacher">Teacher</option>
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

                                                    <!-- Password -->
                                                    <div>
                                                        <label for="edit-password-{{ $user->id }}" class="block text-xs font-semibold text-gray-600 mb-1">New Password</label>
                                                        <input type="password" name="password" id="edit-password-{{ $user->id }}"
                                                            class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('password') border-red-500 @enderror">
                                                        <p class="mt-1 text-xs text-gray-500">Minimum 8 characters. Leave blank to keep current password.</p>
                                                        @error('password')
                                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                                        @enderror
                                                    </div>

                                                    <!-- Course Assignments (Teacher only) -->
                                                    <div x-show="role === 'teacher'" x-cloak class="border-t border-gray-100 pt-5">
                                                        <div class="flex items-center justify-between mb-2">
                                                            <label class="block text-sm font-medium text-gray-700">Course Assignments</label>
                                                            <button type="button" @click="addAssignment()" class="text-xs font-medium text-blue-600 hover:text-blue-700 flex items-center gap-1">
                                                                <i class="ti ti-plus"></i> Add Assignment
                                                            </button>
                                                        </div>
                                                        <p class="text-xs text-gray-500 mb-3">
                                                            Assign this teacher as adviser of a course/program.
                                                        </p>

                                                        <template x-for="(a, index) in assignments" :key="index">
                                                            <div class="grid grid-cols-12 gap-2 mb-2 items-center">
                                                                <div class="col-span-4">
                                                                    <select :name="'assignments[' + index + '][course]'"
                                                                        @change="a.course = $event.target.value; a.grade_level = ''; a.section = '';"
                                                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                                                        <option value="">Select course...</option>
                                                                        <template x-for="c in coursesFor()" :key="c">
                                                                            <option :value="c" x-text="c" :selected="c === a.course"></option>
                                                                        </template>
                                                                    </select>
                                                                </div>
                                                                <div class="col-span-3">
                                                                    <select :name="'assignments[' + index + '][grade_level]'"
                                                                        @change="a.grade_level = $event.target.value; a.section = '';"
                                                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                                                        <option value="">All year levels</option>
                                                                        <template x-for="g in gradesFor(a.course)" :key="g">
                                                                            <option :value="g" x-text="g" :selected="g === a.grade_level"></option>
                                                                        </template>
                                                                    </select>
                                                                </div>
                                                                <div class="col-span-4">
                                                                    <select :name="'assignments[' + index + '][section]'"
                                                                        @change="a.section = $event.target.value"
                                                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
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
                                                </div>

                                                {{-- Footer --}}
                                                <div class="mt-6 flex items-center justify-end gap-3 pt-5 border-t border-gray-100">
                                                    <button type="button" @click="showEditModal = false" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                                                        Cancel
                                                    </button>
                                                    <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                                                        <i class="ti ti-device-floppy"></i> Update User
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                @if(auth()->id() !== $user->id)
                                    <form id="delete-user-{{ $user->id }}" action="{{ route('admin.users.destroy', $user->id) }}" method="POST" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button type="button" @click="$dispatch('open-confirm-modal', { 
                                                formId: 'delete-user-{{ $user->id }}', 
                                                title: 'Delete User', 
                                                message: 'Are you sure you want to delete {{ addslashes($user->name) }}? This action cannot be undone.',
                                                confirmText: 'Yes, Delete User'
                                            })" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete User">
                                            <i class="ti ti-trash"></i>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-400">
                            <i class="ti ti-users text-4xl mb-3 block opacity-20"></i>
                            No users found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    
    @if($users->hasPages())
        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50">
            {{ $users->links() }}
        </div>
    @endif
</div>

{{-- ── Add New User Modal ─────────────────────────────────────── --}}
<div x-show="showAddUser" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">

    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="showAddUser = false"></div>

    {{-- Modal Content --}}
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto border border-gray-100"
         @click.away="showAddUser = false"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-4"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
         x-transition:leave-end="opacity-0 scale-95 translate-y-4">

        {{-- Header --}}
        <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between sticky top-0 z-10 rounded-t-2xl">
            <div>
                <h3 class="font-semibold text-gray-800 text-lg">Add New User</h3>
                <p class="text-xs text-gray-400 mt-0.5">Create a new admin, counselor, or teacher account</p>
            </div>
            <button @click="showAddUser = false" class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-2 rounded-lg transition">
                <i class="ti ti-x text-lg"></i>
            </button>
        </div>

        {{-- Form --}}
        <div class="p-6"
             x-data='{
                 role: "",
                 assignments: [],
                 combos: @json($courseCombos),
                 addAssignment() { this.assignments.push({ course: "", grade_level: "", section: "" }); },
                 removeAssignment(i) { this.assignments.splice(i, 1); },
                 coursesFor() { return [...new Set(this.combos.map(c => c.course))]; },
                 gradesFor(course) { return [...new Set(this.combos.filter(c => c.course === course).map(c => c.grade_level))]; },
                 sectionsFor(course, grade) { return [...new Set(this.combos.filter(c => c.course === course && (!grade || c.grade_level === grade)).map(c => c.section))]; }
             }'>
            <form action="{{ route('admin.users.store') }}" method="POST">
                @csrf

                <div class="space-y-5">
                    <!-- Name -->
                    <div>
                        <label for="modal-name" class="block text-xs font-semibold text-gray-600 mb-1">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="modal-name" value="{{ old('name') }}" required
                            class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('name') border-red-500 @enderror">
                        @error('name')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="modal-email" class="block text-xs font-semibold text-gray-600 mb-1">Email Address <span class="text-red-500">*</span></label>
                        <input type="email" name="email" id="modal-email" value="{{ old('email') }}" required
                            class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('email') border-red-500 @enderror">
                        @error('email')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Role -->
                    <div>
                        <label for="modal-role" class="block text-xs font-semibold text-gray-600 mb-1">System Role <span class="text-red-500">*</span></label>
                        <select name="role" id="modal-role" x-model="role" required
                            class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('role') border-red-500 @enderror">
                            <option value="">Select a role...</option>
                            <option value="admin">Admin</option>
                            <option value="guidance_counselor">Guidance Counselor</option>
                            <option value="teacher">Teacher</option>
                        </select>
                        @error('role')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Employee ID (Teacher only) — the teacher's mobile login -->
                    <div x-show="role === 'teacher'" x-cloak>
                        <label for="modal-username" class="block text-xs font-semibold text-gray-600 mb-1">Employee ID <span class="text-red-500">*</span></label>
                        <input type="text" name="username" id="modal-username" value="{{ old('username') }}"
                            :required="role === 'teacher'" placeholder="e.g. 2025-003"
                            class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('username') border-red-500 @enderror">
                        <p class="mt-1 text-xs text-gray-500">The teacher signs in to the mobile app with this ID, then activates with a one-time code.</p>
                        @error('username')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Activation notice (Teacher only) — no admin-set password -->
                    <div x-show="role === 'teacher'" x-cloak class="rounded-xl border border-indigo-100 bg-indigo-50/60 p-3 flex gap-2.5">
                        <i class="ti ti-key text-indigo-600 mt-0.5"></i>
                        <p class="text-xs text-indigo-900/90 leading-relaxed">
                            No password needed. When you save, the system generates a <span class="font-semibold">one-time activation code</span> — hand it to the teacher, and they set their own password on first login.
                        </p>
                    </div>

                    <!-- Password (Admin / Counselor only) -->
                    <div x-show="role !== 'teacher'" x-cloak>
                        <label for="modal-password" class="block text-xs font-semibold text-gray-600 mb-1">Password <span class="text-red-500">*</span></label>
                        <input type="password" name="password" id="modal-password" :required="role !== 'teacher'"
                            class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('password') border-red-500 @enderror">
                        <p class="mt-1 text-xs text-gray-500">Minimum 8 characters.</p>
                        @error('password')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Course Assignments (Teacher only) -->
                    <div x-show="role === 'teacher'" x-cloak class="border-t border-gray-100 pt-5">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm font-medium text-gray-700">Course Assignments</label>
                            <button type="button" @click="addAssignment()" class="text-xs font-medium text-blue-600 hover:text-blue-700 flex items-center gap-1">
                                <i class="ti ti-plus"></i> Add Assignment
                            </button>
                        </div>
                        <p class="text-xs text-gray-500 mb-3">
                            Assign this teacher as adviser of a course/program.
                        </p>

                        <template x-for="(a, index) in assignments" :key="index">
                            <div class="grid grid-cols-12 gap-2 mb-2 items-center">
                                <div class="col-span-4">
                                    <select :name="'assignments[' + index + '][course]'"
                                        @change="a.course = $event.target.value; a.grade_level = ''; a.section = '';"
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        <option value="">Select course...</option>
                                        <template x-for="c in coursesFor()" :key="c">
                                            <option :value="c" x-text="c" :selected="c === a.course"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="col-span-3">
                                    <select :name="'assignments[' + index + '][grade_level]'"
                                        @change="a.grade_level = $event.target.value; a.section = '';"
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        <option value="">All year levels</option>
                                        <template x-for="g in gradesFor(a.course)" :key="g">
                                            <option :value="g" x-text="g" :selected="g === a.grade_level"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="col-span-4">
                                    <select :name="'assignments[' + index + '][section]'"
                                        @change="a.section = $event.target.value"
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
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
                </div>

                {{-- Footer --}}
                <div class="mt-6 flex items-center justify-end gap-3 pt-5 border-t border-gray-100">
                    <button type="button" @click="showAddUser = false" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                        <i class="ti ti-device-floppy"></i> Save User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

</div>

@if($errors->any())
<script>
    document.addEventListener('alpine:init', () => {
        setTimeout(() => {
            document.querySelector('[x-data]').__x.$data.showAddUser = true;
        }, 100);
    });
</script>
@endif

@endsection
