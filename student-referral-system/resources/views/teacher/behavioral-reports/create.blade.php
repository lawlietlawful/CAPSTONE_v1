@extends('layouts.teacher')

@section('title', 'Log Incident or Grade')
@section('page-title', 'Log Behavioral/Academic Report')
@section('page-sub', 'Record a failing grade or behavioral incident for a student')

@section('content')

<div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden max-w-4xl transition-all hover:shadow-hover">
    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
        <h3 class="font-bold text-gray-800 text-lg flex items-center gap-2">
            <div class="h-8 w-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">
                <i class="ti ti-file-description text-lg"></i>
            </div>
            New Report Form
        </h3>
        <a href="{{ route('teacher.behavioral-reports.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-800 flex items-center gap-1 transition-colors">
            <i class="ti ti-arrow-left"></i> Back to Reports
        </a>
    </div>

    <div class="p-6">
        <form action="{{ route('teacher.behavioral-reports.store') }}" method="POST">
            @csrf
            
            <div class="space-y-6">
                <!-- Student Selection -->
                <div class="bg-gray-50/50 p-5 rounded-xl border border-gray-100">
                    <label for="student_id" class="block text-sm font-semibold text-gray-800 mb-2">Select Student <span class="text-red-500">*</span></label>
                    <select name="student_id" id="student_id" required
                        class="w-full rounded-xl border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('student_id') border-red-500 @enderror">
                        <option value="">Choose a student...</option>
                        @foreach($students as $student)
                            <option value="{{ $student->id }}" {{ old('student_id') == $student->id ? 'selected' : '' }}>
                                {{ $student->last_name }}, {{ $student->first_name }} ({{ $student->course ?? $student->grade_level }} - {{ $student->section }})
                            </option>
                        @endforeach
                    </select>
                    @error('student_id')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Incident Type -->
                    <div>
                        <label for="incident_type" class="block text-sm font-semibold text-gray-800 mb-1">Report Type <span class="text-red-500">*</span></label>
                        <select name="incident_type" id="incident_type" required
                            class="w-full rounded-xl border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('incident_type') border-red-500 @enderror">
                            <option value="">Select type...</option>
                            @foreach(\App\Models\BehavioralReport::INCIDENT_TYPES as $value => $label)
                                <option value="{{ $value }}" {{ old('incident_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('incident_type')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{--
                        There used to be a required "Severity Level" dropdown here.
                        The controller never read it — severity is assessed by the ML
                        engine — so the teacher's choice was silently discarded, and
                        the "(Triggers SMS to Parent)" labels were untrue. Replaced
                        with an explanation, matching the mobile app.
                    --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-800 mb-1">Severity Level</label>
                        <div class="w-full rounded-xl border border-blue-100 bg-blue-50/60 px-4 py-3">
                            <p class="text-sm text-blue-900 font-medium flex items-center gap-1.5">
                                <i class="ti ti-sparkles"></i> Assessed automatically
                            </p>
                            <p class="text-xs text-blue-700/80 mt-1 leading-relaxed">
                                The AI grades this incident from the student's history and your
                                description. Serious incidents are escalated to Guidance and the
                                parent is notified by SMS.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Date -->
                    <div>
                        <label for="incident_date" class="block text-sm font-semibold text-gray-800 mb-1">Date of Incident / Grade <span class="text-red-500">*</span></label>
                        <input type="date" name="incident_date" id="incident_date" value="{{ old('incident_date', date('Y-m-d')) }}" required
                            class="w-full rounded-xl border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('incident_date') border-red-500 @enderror">
                        @error('incident_date')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Location -->
                    <div>
                        <label for="location" class="block text-sm font-semibold text-gray-800 mb-1">Location / Subject (Optional)</label>
                        <input type="text" name="location" id="location" value="{{ old('location') }}" placeholder="e.g., Room 101 or IT102"
                            class="w-full rounded-xl border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('location') border-red-500 @enderror">
                        @error('location')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-sm font-semibold text-gray-800 mb-1">Description <span class="text-red-500">*</span></label>
                    <textarea name="description" id="description" rows="4" required placeholder="Provide details of the failing grade or incident..."
                        class="w-full rounded-xl border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('description') border-red-500 @enderror">{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div class="mt-8 flex items-center justify-end gap-3 pt-5 border-t border-gray-100">
                <a href="{{ route('teacher.behavioral-reports.index') }}" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition shadow-sm">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm hover:shadow-md flex items-center gap-2">
                    <i class="ti ti-send text-lg"></i> Submit Report
                </button>
            </div>
        </form>
    </div>
</div>

@endsection
