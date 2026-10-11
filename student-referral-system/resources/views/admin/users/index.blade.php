@extends('layouts.admin')

@section('title', 'User Management')
@section('page-title', 'User Management')
@section('page-sub', 'Manage administrators, counselors, and teachers')

@section('content')

@php
    $isSuper = auth()->user()->role === 'super_admin';

    // Which form a validation failure came from — every form posts a hidden
    // `_form` ("add" or "edit-{id}"), so the right modal reopens with its own
    // errors. This page used to reopen "Add New User" for every failure (and
    // even that threw a JS error), so a rejected edit looked like nothing
    // happened at all.
    $failedForm = $errors->any() ? old('_form') : null;
    $addFailed = $failedForm === 'add';

    // The Alpine functions every course-assignment picker needs
    // (partials/course-assignment-rows.blade.php).
    $assignmentFns = '
        addAssignment() { this.assignments.push({ course: "", grade_level: "", section: "" }); },
        removeAssignment(i) { this.assignments.splice(i, 1); },
        duplicateAssignment(i) { this.assignments.splice(i + 1, 0, { ...this.assignments[i] }); },
        coursesFor() { return [...new Set(this.combos.map(c => c.course))]; },
        gradesFor(course) { return [...new Set(this.combos.filter(c => c.course === course).map(c => c.grade_level))]; },
        sectionsFor(course, grade) { return [...new Set(this.combos.filter(c => c.course === course && (!grade || c.grade_level === grade)).map(c => c.section))]; }
    ';
@endphp

<div x-data="{ showAddUser: {{ $addFailed ? 'true' : 'false' }} }">

{{-- A validation failure that didn't come from any form on this page (no
     _form marker) still has to be visible somewhere. --}}
@if($errors->any() && ! $failedForm)
    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" data-generic-errors>
        <p class="font-semibold mb-1 flex items-center gap-1.5"><i class="ti ti-alert-circle"></i> Please fix the following:</p>
        <ul class="list-disc pl-5 space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- ── Search / filter ────────────────────────────────────────── --}}
<div class="mb-6">
    <form method="GET" action="{{ route('admin.users.index') }}" id="filterForm" class="w-full">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-4 flex flex-col lg:flex-row gap-3 items-end">
            <div class="flex-1 w-full">
                <label class="block text-xs font-medium text-gray-500 mb-1">Search User</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="ti ti-search text-gray-400"></i>
                    </div>
                    <input type="text" id="searchInput" name="search" value="{{ request('search') }}" placeholder="Name, email, or Employee ID..."
                        class="block w-full pl-10 pr-3 py-2 border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition" autocomplete="off">
                </div>
            </div>

            @if($isSuper)
                <div class="w-full lg:w-48">
                    <label class="block text-xs font-medium text-gray-500 mb-1">Role</label>
                    <select name="role" onchange="document.getElementById('filterForm').submit();" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                        <option value="" {{ request('role') ? '' : 'selected' }}>All Roles</option>
                        <option value="super_admin" {{ request('role') === 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                        <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin (Counselor)</option>
                        <option value="teacher" {{ request('role') === 'teacher' ? 'selected' : '' }}>Teacher</option>
                    </select>
                </div>
            @endif

            <div class="flex gap-2 w-full lg:w-auto">
                <a href="{{ route('admin.users.index') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                    <i class="ti ti-x"></i> Reset
                </a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                    <i class="ti ti-search"></i> Search
                </button>
            </div>
        </div>
    </form>
</div>

<div class="bg-white border border-gray-100 rounded-2xl overflow-hidden shadow-premium">
    <div class="px-6 py-5 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
        <h3 class="font-semibold text-gray-800 text-lg">System Users</h3>
        <button @click="showAddUser = true" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition flex items-center gap-2 shadow-sm">
            <i class="ti ti-plus"></i> Add New User
        </button>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-gray-500 uppercase text-xs font-semibold tracking-wider">
                <tr>
                    <th class="px-6 py-4 whitespace-nowrap">Name</th>
                    <th class="px-6 py-4 whitespace-nowrap">Email</th>
                    <th class="px-6 py-4 whitespace-nowrap">Role</th>
                    <th class="px-6 py-4 whitespace-nowrap">Assigned To</th>
                    <th class="px-6 py-4 whitespace-nowrap">Joined</th>
                    <th class="px-6 py-4 text-right whitespace-nowrap">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($users as $user)
                    @php
                        $editFailed = $failedForm === 'edit-' . $user->id;

                        // Deleting a user is ON DELETE CASCADE for everything
                        // they filed or logged — the confirmation has to say
                        // what goes with them, the way the Students page does.
                        $erased = [];
                        if ($user->behavioral_reports_count > 0) {
                            $erased[] = $user->behavioral_reports_count . ' behavioral report' . ($user->behavioral_reports_count === 1 ? '' : 's');
                        }
                        if ($user->referrals_referred_count > 0) {
                            $erased[] = $user->referrals_referred_count . ' referral' . ($user->referrals_referred_count === 1 ? '' : 's');
                        }
                        if ($user->interventions_count > 0) {
                            $erased[] = $user->interventions_count . ' intervention record' . ($user->interventions_count === 1 ? '' : 's');
                        }
                        $deleteMessage = count($erased) > 0
                            ? 'Deleting ' . $user->name . ' will also permanently erase ' . implode(', ', $erased) . ' they filed — part of the students\' case history. This cannot be undone.'
                            : 'Are you sure you want to delete ' . $user->name . '? This action cannot be undone.';
                    @endphp
                    <tr class="hover:bg-blue-50/50 transition" x-data="{ showEditModal: {{ $editFailed ? 'true' : 'false' }} }">
                        <td class="px-6 py-4 font-medium text-gray-800 whitespace-nowrap">
                            @if($user->role === 'teacher')
                                <a href="{{ route('admin.teachers.show', $user->id) }}" class="hover:text-blue-600 hover:underline transition" title="Open teacher profile">{{ $user->name }}</a>
                            @else
                                {{ $user->name }}
                            @endif
                        </td>
                        <td class="px-6 py-4 text-gray-500 whitespace-nowrap">{{ $user->email ?: '—' }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            @if($user->role === 'super_admin')
                                <span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-xs font-medium">Super Admin</span>
                            @elseif($user->role === 'admin')
                                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-medium">Admin</span>
                            @elseif($user->role === 'teacher')
                                <span class="bg-emerald-100 text-emerald-700 px-3 py-1 rounded-full text-xs font-medium">Teacher</span>
                            @else
                                <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-xs font-medium">{{ ucfirst(str_replace('_', ' ', $user->role)) }}</span>
                            @endif
                        </td>
                        <td class="px-6 py-4" data-assigned-cell>
                            @if($user->role === 'teacher')
                                @if($user->teacherAssignments->isEmpty())
                                    <span class="text-xs text-amber-600 flex items-center gap-1 whitespace-nowrap">
                                        <i class="ti ti-alert-triangle"></i> Unassigned
                                    </span>
                                @else
                                    {{-- Chips wrap and truncate inside the column instead of
                                         running into Joined (the table used to be nowrap
                                         end to end). Full text on hover. --}}
                                    <div class="flex flex-wrap gap-1 max-w-[260px]">
                                        @foreach($user->teacherAssignments as $a)
                                            @php $label = $a->course . ($a->grade_level ? ' · ' . $a->grade_level : '') . ($a->section ? ' · ' . $a->section : ''); @endphp
                                            <span class="inline-block max-w-full truncate px-2 py-0.5 rounded-md text-[11px] font-medium bg-gray-100 text-gray-700" title="{{ $label }}">{{ $label }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-gray-500 whitespace-nowrap">{{ $user->created_at->format('M d, Y') }}</td>
                        <td class="px-6 py-4 text-right whitespace-nowrap">
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" @click="showEditModal = true" class="text-blue-600 hover:bg-blue-50 p-2 rounded-lg transition" title="Edit">
                                    <i class="ti ti-edit text-lg"></i>
                                </button>

                                {{-- ── Edit User Modal ─────────────────────────────────────── --}}
                                <div x-show="showEditModal" x-cloak
                                     class="fixed inset-0 z-50 flex items-center justify-center p-4 text-left whitespace-normal"
                                     x-transition:enter="transition ease-out duration-200"
                                     x-transition:enter-start="opacity-0"
                                     x-transition:enter-end="opacity-100"
                                     x-transition:leave="transition ease-in duration-150"
                                     x-transition:leave-start="opacity-100"
                                     x-transition:leave-end="opacity-0">

                                    {{-- Backdrop --}}
                                    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="showEditModal = false"></div>

                                    {{-- Modal Content --}}
                                    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto border border-gray-100 custom-scrollbar"
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
                                            <button type="button" @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-2 rounded-lg transition">
                                                <i class="ti ti-x text-lg"></i>
                                            </button>
                                        </div>

                                        {{-- Form --}}
                                        <div class="p-6"
                                             x-data='{
                                                 role: @json($editFailed ? old('role', $user->role) : $user->role),
                                                 assignments: {{ $editFailed && old('assignments') ? json_encode(old('assignments')) : $user->teacherAssignments->map(fn($a) => ['course' => $a->course, 'grade_level' => $a->grade_level, 'section' => $a->section])->values()->toJson() }},
                                                 combos: @json($courseCombos),
                                                 {!! $assignmentFns !!}
                                             }'>
                                            <form action="{{ route('admin.users.update', $user->id) }}" method="POST" data-edit-form="{{ $user->id }}">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="_form" value="edit-{{ $user->id }}">

                                                @if($editFailed)
                                                    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" data-edit-errors>
                                                        <p class="font-semibold flex items-center gap-1.5"><i class="ti ti-alert-circle"></i> Changes weren't saved — please fix the highlighted fields.</p>
                                                    </div>
                                                @endif

                                                <div class="space-y-5">
                                                    <!-- Name -->
                                                    <div>
                                                        <label for="edit-name-{{ $user->id }}" class="block text-xs font-semibold text-gray-600 mb-1">Full Name <span class="text-red-500">*</span></label>
                                                        <input type="text" name="name" id="edit-name-{{ $user->id }}" value="{{ $editFailed ? old('name', $user->name) : $user->name }}" required
                                                            class="w-full rounded-xl border bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm {{ $editFailed && $errors->has('name') ? 'border-red-500' : 'border-gray-300' }}">
                                                        @if($editFailed) @error('name') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror @endif
                                                    </div>

                                                    <!-- Email -->
                                                    <div>
                                                        <label for="edit-email-{{ $user->id }}" class="block text-xs font-semibold text-gray-600 mb-1">Email Address <span class="text-red-500">*</span></label>
                                                        <input type="email" name="email" id="edit-email-{{ $user->id }}" value="{{ $editFailed ? old('email', $user->email) : $user->email }}" required
                                                            class="w-full rounded-xl border bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm {{ $editFailed && $errors->has('email') ? 'border-red-500' : 'border-gray-300' }}">
                                                        @if($editFailed) @error('email') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror @endif
                                                    </div>

                                                    <!-- Role — only a super admin can change it; a counselor
                                                         manages teacher accounts only (UpdateUserRequest
                                                         rejects anything else), so they aren't offered
                                                         options that would always fail. -->
                                                    <div>
                                                        <label for="edit-role-{{ $user->id }}" class="block text-xs font-semibold text-gray-600 mb-1">System Role <span class="text-red-500">*</span></label>
                                                        @if($isSuper)
                                                            <select name="role" id="edit-role-{{ $user->id }}" x-model="role" required
                                                                class="w-full rounded-xl border bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm {{ $editFailed && $errors->has('role') ? 'border-red-500' : 'border-gray-300' }}">
                                                                <option value="super_admin">Super Admin</option>
                                                                <option value="admin">Admin (Counselor)</option>
                                                                <option value="teacher">Teacher</option>
                                                            </select>
                                                        @else
                                                            <input type="hidden" name="role" value="teacher">
                                                            <div class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-600" data-role-locked>Teacher</div>
                                                        @endif
                                                        @if($editFailed) @error('role') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror @endif
                                                    </div>

                                                    <!-- Password -->
                                                    <div>
                                                        <label for="edit-password-{{ $user->id }}" class="block text-xs font-semibold text-gray-600 mb-1">New Password</label>
                                                        <input type="password" name="password" id="edit-password-{{ $user->id }}"
                                                            class="w-full rounded-xl border bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm {{ $editFailed && $errors->has('password') ? 'border-red-500' : 'border-gray-300' }}">
                                                        <p class="mt-1 text-xs text-gray-500">Minimum 8 characters. Leave blank to keep current password.</p>
                                                        @if($editFailed) @error('password') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror @endif
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
                                                            Leave Year Level or Section blank to cover the whole course or whole year level.
                                                        </p>

                                                        @include('partials.course-assignment-rows')

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
                                                message: @js($deleteMessage),
                                                confirmText: 'Yes, Delete User'
                                            })" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition" title="Delete User" data-delete-user="{{ $user->id }}">
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
                            {{ request('search') || request('role') ? 'No users match your search.' : 'No users found.' }}
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
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[90vh] overflow-y-auto border border-gray-100"
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
                <p class="text-xs text-gray-400 mt-0.5">{{ $isSuper ? 'Create a new admin, counselor, or teacher account' : 'Create a new teacher account' }}</p>
            </div>
            <button type="button" @click="showAddUser = false" class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 p-2 rounded-lg transition">
                <i class="ti ti-x text-lg"></i>
            </button>
        </div>

        {{-- Form --}}
        <div class="p-6"
             x-data='{
                 role: @json($addFailed ? old('role', $isSuper ? '' : 'teacher') : ($isSuper ? '' : 'teacher')),
                 assignments: {{ $addFailed && old('assignments') ? json_encode(old('assignments')) : '[]' }},
                 combos: @json($courseCombos),
                 {!! $assignmentFns !!}
             }'>
            <form action="{{ route('admin.users.store') }}" method="POST" data-add-form>
                @csrf
                <input type="hidden" name="_form" value="add">

                @if($addFailed)
                    <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" data-add-errors>
                        <p class="font-semibold flex items-center gap-1.5"><i class="ti ti-alert-circle"></i> The account wasn't created — please fix the highlighted fields.</p>
                    </div>
                @endif

                <div class="space-y-5">
                    <!-- Name -->
                    <div>
                        <label for="modal-name" class="block text-xs font-semibold text-gray-600 mb-1">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" id="modal-name" value="{{ $addFailed ? old('name') : '' }}" required
                            class="w-full rounded-xl border bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm {{ $addFailed && $errors->has('name') ? 'border-red-500' : 'border-gray-300' }}">
                        @if($addFailed) @error('name') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror @endif
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="modal-email" class="block text-xs font-semibold text-gray-600 mb-1">Email Address <span class="text-red-500">*</span></label>
                        <input type="email" name="email" id="modal-email" value="{{ $addFailed ? old('email') : '' }}" required
                            class="w-full rounded-xl border bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm {{ $addFailed && $errors->has('email') ? 'border-red-500' : 'border-gray-300' }}">
                        @if($addFailed) @error('email') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror @endif
                    </div>

                    <!-- Role -->
                    <div>
                        <label for="modal-role" class="block text-xs font-semibold text-gray-600 mb-1">System Role <span class="text-red-500">*</span></label>
                        @if($isSuper)
                            <select name="role" id="modal-role" x-model="role" required
                                class="w-full rounded-xl border bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm {{ $addFailed && $errors->has('role') ? 'border-red-500' : 'border-gray-300' }}">
                                <option value="">Select a role...</option>
                                <option value="super_admin">Super Admin</option>
                                <option value="admin">Admin (Counselor)</option>
                                <option value="teacher">Teacher</option>
                            </select>
                        @else
                            <input type="hidden" name="role" value="teacher">
                            <div class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm text-gray-600" data-role-locked>Teacher</div>
                        @endif
                        @if($addFailed) @error('role') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror @endif
                    </div>

                    <!-- Employee ID (Teacher only) — the teacher's mobile login -->
                    <div x-show="role === 'teacher'" x-cloak>
                        <label for="modal-username" class="block text-xs font-semibold text-gray-600 mb-1">Employee ID <span class="text-red-500">*</span></label>
                        <input type="text" name="username" id="modal-username" value="{{ $addFailed ? old('username') : '' }}"
                            :required="role === 'teacher'" placeholder="e.g. 2025-003"
                            class="w-full rounded-xl border bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm {{ $addFailed && $errors->has('username') ? 'border-red-500' : 'border-gray-300' }}">
                        <p class="mt-1 text-xs text-gray-500">The teacher signs in to the mobile app with this ID, then activates with a one-time code.</p>
                        @if($addFailed) @error('username') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror @endif
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
                            class="w-full rounded-xl border bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm {{ $addFailed && $errors->has('password') ? 'border-red-500' : 'border-gray-300' }}">
                        <p class="mt-1 text-xs text-gray-500">Minimum 8 characters.</p>
                        @if($addFailed) @error('password') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror @endif
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
                            Leave Year Level or Section blank to cover the whole course or whole year level.
                        </p>

                        @include('partials.course-assignment-rows')

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

@endsection

@push('scripts')
<script>
    (function () {
        let searchTimeout = null;
        const searchInput = document.getElementById('searchInput');
        const filterForm = document.getElementById('filterForm');
        if (!searchInput || !filterForm) return;

        searchInput.addEventListener('input', function (e) {
            clearTimeout(searchTimeout);
            const val = e.target.value.trim();
            // Auto-submit once cleared or at 2+ characters, 500ms after typing stops.
            if (val.length === 0 || val.length >= 2) {
                searchTimeout = setTimeout(() => filterForm.submit(), 500);
            }
        });

        if (searchInput.value) {
            const length = searchInput.value.length;
            searchInput.focus();
            searchInput.setSelectionRange(length, length);
        }
    })();
</script>
@endpush
