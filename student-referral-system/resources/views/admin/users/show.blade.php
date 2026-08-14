@extends('layouts.admin')

@section('title', 'User Profile')
@section('page-title', 'User Profile')
@section('page-sub', 'Viewing details for ' . $user->name)

@section('content')

<div class="bg-white border border-gray-100 rounded-2xl shadow-premium max-w-4xl">
    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
        <h3 class="font-semibold text-gray-800 text-lg flex items-center gap-2">
            <i class="ti ti-user-circle text-blue-600"></i> Profile Information
        </h3>
        <a href="{{ route('admin.users.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <i class="ti ti-arrow-left"></i> Back to Users
        </a>
    </div>

    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-8">
        <div class="space-y-6">
            <!-- Name -->
            <div>
                <span class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Full Name</span>
                <p class="text-gray-900 font-medium">{{ $user->name }}</p>
            </div>

            <!-- Username -->
            <div>
                <span class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Username</span>
                <p class="text-gray-900 font-medium">{{ $user->username ?? 'N/A' }}</p>
            </div>

            <!-- Email -->
            <div>
                <span class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Email Address</span>
                <p class="text-gray-900 font-medium">
                    @if($user->email)
                        <a href="mailto:{{ $user->email }}" class="text-blue-600 hover:underline">{{ $user->email }}</a>
                    @else
                        <span class="text-gray-400">Not provided</span>
                    @endif
                </p>
            </div>

            <!-- Role -->
            <div>
                <span class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">System Role</span>
                @if($user->role === 'admin')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700">
                        Admin
                    </span>
                @elseif($user->role === 'guidance_counselor' || $user->role === 'counselor')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700">
                        Guidance Counselor
                    </span>
                @elseif($user->role === 'teacher')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-700">
                        Teacher
                    </span>
                @elseif($user->role === 'student')
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-50 text-green-700">
                        Student
                    </span>
                @endif
            </div>
            
            <!-- Created At -->
            <div>
                <span class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Account Created</span>
                <p class="text-gray-900">{{ $user->created_at->format('F j, Y g:i A') }}</p>
            </div>

            <!-- Course Assignments (Teacher only) -->
            @if($user->role === 'teacher')
                <div>
                    <span class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Course Assignments</span>
                    @if($user->teacherAssignments->isEmpty())
                        <p class="text-sm text-amber-600 flex items-center gap-1.5">
                            <i class="ti ti-alert-triangle"></i> Not assigned to any course yet — this teacher cannot see any students.
                        </p>
                    @else
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($user->teacherAssignments as $a)
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700">
                                    {{ $a->course }}{{ $a->grade_level ? ' · ' . $a->grade_level : '' }}{{ $a->section ? ' · ' . $a->section : '' }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>

        <div class="bg-gray-50 rounded-xl p-5 border border-gray-100 flex flex-col justify-center items-center text-center space-y-4">
            <div class="w-24 h-24 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-4xl font-bold">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            
            <div>
                <h4 class="font-semibold text-gray-900 text-lg">{{ $user->name }}</h4>
                <p class="text-gray-500 text-sm">{{ ucfirst(str_replace('_', ' ', $user->role)) }}</p>
            </div>

            <div class="pt-4 mt-2 w-full border-t border-gray-200 flex flex-col gap-2">
                <a href="{{ route('admin.users.edit', $user->id) }}" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition shadow-sm text-sm font-medium">
                    Edit User Profile
                </a>

                <form action="{{ route('admin.users.reset-password', $user->id) }}" method="POST" class="w-full">
                    @csrf
                    <button type="submit" onclick="return confirm('Are you sure you want to reset the password for this user to default?')" class="w-full px-4 py-2 bg-white text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition shadow-sm text-sm font-medium">
                        Reset Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
