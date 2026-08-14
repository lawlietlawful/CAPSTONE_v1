@extends('layouts.counselor')

@section('title', 'Create Seminar')
@section('page-title', 'Create New Seminar')
@section('page-sub', 'Schedule an intervention seminar or workshop')

@section('content')

<div class="bg-white border border-gray-100 rounded-2xl shadow-premium max-w-4xl">
    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
        <h3 class="font-semibold text-gray-800 text-lg flex items-center gap-2">
            <i class="ti ti-calendar-plus text-blue-600"></i> Seminar Details
        </h3>
        <a href="{{ route('counselor.seminars.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <i class="ti ti-arrow-left"></i> Back
        </a>
    </div>

    <div class="p-6">
        <form action="{{ route('counselor.seminars.store') }}" method="POST">
            @csrf

            <div class="space-y-6">
                <!-- Title & Status -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2">
                        <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Seminar Title <span class="text-red-500">*</span></label>
                        <input type="text" name="title" id="title" value="{{ old('title') }}" required placeholder="e.g. Time Management Workshop"
                            class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('title') border-red-500 @enderror">
                        @error('title') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                        <select name="status" id="status" required
                            class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('status') border-red-500 @enderror">
                            <option value="upcoming" {{ old('status') === 'upcoming' ? 'selected' : '' }}>Upcoming</option>
                            <option value="ongoing" {{ old('status') === 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                            <option value="completed" {{ old('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                            <option value="cancelled" {{ old('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                        @error('status') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Schedule & Location -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="date" id="date" value="{{ old('date') }}" required
                            class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('date') border-red-500 @enderror">
                        @error('date') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="time" class="block text-sm font-medium text-gray-700 mb-1">Time <span class="text-red-500">*</span></label>
                        <input type="time" name="time" id="time" value="{{ old('time') }}" required
                            class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('time') border-red-500 @enderror">
                        @error('time') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="venue" class="block text-sm font-medium text-gray-700 mb-1">Venue / Room <span class="text-red-500">*</span></label>
                        <input type="text" name="venue" id="venue" value="{{ old('venue') }}" required placeholder="e.g. AVR 1"
                            class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('venue') border-red-500 @enderror">
                        @error('venue') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Target Audience -->
                <div class="bg-gray-50/50 p-4 rounded-xl border border-gray-100">
                    <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center gap-2"><i class="ti ti-target"></i> Target Audience & Details</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label for="target_course" class="block text-sm font-medium text-gray-700 mb-1">Target Course</label>
                            <input type="text" name="target_course" id="target_course" value="{{ old('target_course') }}" placeholder="e.g. BSIT (Optional)"
                                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('target_course') border-red-500 @enderror">
                        </div>
                        <div>
                            <label for="target_grade_level" class="block text-sm font-medium text-gray-700 mb-1">Target Year Level</label>
                            <select name="target_grade_level" id="target_grade_level"
                                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('target_grade_level') border-red-500 @enderror">
                                <option value="">All Years</option>
                                <option value="1st Year" {{ old('target_grade_level') == '1st Year' ? 'selected' : '' }}>1st Year</option>
                                <option value="2nd Year" {{ old('target_grade_level') == '2nd Year' ? 'selected' : '' }}>2nd Year</option>
                                <option value="3rd Year" {{ old('target_grade_level') == '3rd Year' ? 'selected' : '' }}>3rd Year</option>
                                <option value="4th Year" {{ old('target_grade_level') == '4th Year' ? 'selected' : '' }}>4th Year</option>
                                <option value="5th Year" {{ old('target_grade_level') == '5th Year' ? 'selected' : '' }}>5th Year</option>
                            </select>
                        </div>
                        <div>
                            <label for="max_participants" class="block text-sm font-medium text-gray-700 mb-1">Max Participants</label>
                            <input type="number" name="max_participants" id="max_participants" value="{{ old('max_participants') }}" placeholder="e.g. 50 (Optional)" min="1"
                                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('max_participants') border-red-500 @enderror">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="speaker" class="block text-sm font-medium text-gray-700 mb-1">Speaker / Facilitator</label>
                        <input type="text" name="speaker" id="speaker" value="{{ old('speaker') }}" placeholder="Optional"
                            class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('speaker') border-red-500 @enderror">
                        @error('speaker') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center pt-6">
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="hidden" name="is_required" value="0">
                            <input type="checkbox" name="is_required" value="1" class="sr-only peer" {{ old('is_required', true) ? 'checked' : '' }}>
                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            <span class="ml-3 text-sm font-medium text-gray-700">Mandatory Attendance</span>
                        </label>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Seminar Description</label>
                    <textarea name="description" id="description" rows="3" placeholder="Overview of what will be discussed..."
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('description') border-red-500 @enderror">{{ old('description') }}</textarea>
                    @error('description') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-8 flex items-center justify-end gap-3 pt-5 border-t border-gray-100">
                <a href="{{ route('counselor.seminars.index') }}" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                    <i class="ti ti-device-floppy"></i> Save Seminar
                </button>
            </div>
        </form>
    </div>
</div>

@endsection
